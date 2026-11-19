<?php
declare(strict_types=1);

final class Order
{
    /**
     * Cria o pedido numa transação: trava os livros, confere estoque,
     * grava pedido e itens e baixa o estoque. Lança DomainException
     * com mensagem amigável se algo mudou desde que o cliente montou o carrinho.
     *
     * @param array<int,int> $cart  [book_id => quantidade]
     * @param array{dedications?:array<int,string>, invoice?:array{nif:string,name:string}} $extra
     */
    public static function create(int $userId, array $cart, array $ship, string $paymentMethod, array $extra = []): int
    {
        if (!$cart) {
            throw new DomainException('Seu carrinho está vazio.');
        }
        $dedications = $extra['dedications'] ?? [];
        $useDedication = Dedication::enabled();
        $invoice = $extra['invoice'] ?? ['nif' => '', 'name' => ''];
        if ($invoice['nif'] !== '' && !Nif::valid($invoice['nif'])) {
            throw new DomainException('O NIF informado não é válido.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lines = [];
            $subtotal = 0;
            ksort($cart); // ordem fixa evita deadlock entre pedidos simultâneos
            foreach ($cart as $bookId => $qty) {
                $book = db_one('SELECT * FROM books WHERE id = ? FOR UPDATE', [$bookId]);
                if ($book === null || (int) ($book['active'] ?? 1) !== 1 || $qty < 1 || $qty > (int) config('shop.max_qty_per_item', 99)) {
                    throw new DomainException('Um livro do seu carrinho não está mais disponível. Revise o carrinho.');
                }
                if ((int) $book['stock'] < $qty) {
                    throw new DomainException(sprintf(
                        'Estoque insuficiente para "%s". Disponível: %d. Revise o carrinho.',
                        $book['title'],
                        (int) $book['stock']
                    ));
                }
                $price = to_cents($book['price']);
                // Dedicatória só vale se o livro ainda permite; o tamanho é conferido de novo aqui.
                $dedication = null;
                if ($useDedication && (int) ($book['allows_dedication'] ?? 0) === 1 && ($dedications[$bookId] ?? '') !== '') {
                    [$dedication, $dedError] = Dedication::read($dedications[$bookId]);
                    if ($dedError !== null) {
                        throw new DomainException('"' . $book['title'] . '": ' . $dedError);
                    }
                    $dedication = $dedication === '' ? null : $dedication;
                }
                $lines[] = ['book' => $book, 'qty' => $qty, 'price' => $price, 'line' => $price * $qty, 'dedication' => $dedication];
                $subtotal += $price * $qty;
            }
            $quote = Shipping::quote($subtotal, $ship['cep'] ?? null, $ship['state'] ?? null);
            $shipping = $quote['shipping_cents'];

            $columns = ['user_id', 'status', 'subtotal', 'shipping', 'total', 'payment_method',
                'ship_name', 'ship_phone', 'ship_cep', 'ship_street', 'ship_number', 'ship_complement', 'ship_district', 'ship_city', 'ship_state'];
            $values = [
                $userId, 'pendente', cents_to_decimal($subtotal), cents_to_decimal($shipping), cents_to_decimal($subtotal + $shipping),
                $paymentMethod, $ship['name'], $ship['phone'] === '' ? null : $ship['phone'],
                $ship['cep'], $ship['street'], $ship['number'], $ship['complement'] === '' ? null : $ship['complement'],
                $ship['district'], $ship['city'], $ship['state'],
            ];
            if (AdminSchema::has('orders', 'shipping_label')) {
                array_push($columns, 'shipping_label', 'shipping_days');
                array_push($values, $quote['label'], $quote['days']);
            }
            if (Nif::enabled()) {
                array_push($columns, 'invoice_nif', 'invoice_name');
                array_push($values, $invoice['nif'] === '' ? null : $invoice['nif'], $invoice['name'] === '' ? null : $invoice['name']);
            }
            db_run(
                'INSERT INTO orders (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')',
                $values
            );
            $orderId = (int) $pdo->lastInsertId();

            $withDedication = AdminSchema::has('order_items', 'dedication');
            foreach ($lines as $l) {
                db_run(
                    'INSERT INTO order_items (order_id, book_id, title, author, unit_price, quantity, line_total' . ($withDedication ? ', dedication' : '') . ')
                     VALUES (?, ?, ?, ?, ?, ?, ?' . ($withDedication ? ', ?' : '') . ')',
                    array_merge(
                        [$orderId, (int) $l['book']['id'], $l['book']['title'], $l['book']['author'], cents_to_decimal($l['price']), $l['qty'], cents_to_decimal($l['line'])],
                        $withDedication ? [$l['dedication']] : []
                    )
                );
                db_run('UPDATE books SET stock = stock - ? WHERE id = ?', [$l['qty'], (int) $l['book']['id']]);
                AdminStock::record((int) $l['book']['id'], 'venda', -$l['qty'], (int) $l['book']['stock'] - $l['qty'], 'Pedido nº ' . $orderId);
            }

            if (AdminSchema::has('order_status_history')) db_run('INSERT INTO order_status_history(order_id,user_id,new_status,note) VALUES (?,?,?,?)', [$orderId, $userId, 'pendente', 'Pedido criado']);
            if (AdminSchema::has('payments')) {
                $expiry=date('Y-m-d H:i:s',time()+max(1,(int)(StoreSettings::all()['pix_expires_minutes']??60))*60);
                db_run('INSERT INTO payments(order_id,method,gateway,amount,pix_expires_at) VALUES (?,?,?,?,?)',[$orderId,$paymentMethod==='pix_manual'?'pix':'outro','manual',cents_to_decimal($subtotal+$shipping),$paymentMethod==='pix_manual'?$expiry:null]);
            }

            $pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        return db_one(
            'SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?',
            [$id]
        );
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        return db_one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function items(int $orderId): array
    {
        $extra = AdminSchema::has('order_items', 'dedication') ? ', dedication' : '';
        return db_all('SELECT book_id, title, author, unit_price, quantity, line_total' . $extra . ' FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    /** Grava os dados da fatura emitida no programa certificado. */
    public static function setInvoice(int $id, ?string $number, ?string $url, ?string $issuedAt): void
    {
        db_run('UPDATE orders SET invoice_number = ?, invoice_url = ?, invoice_issued_at = ? WHERE id = ?', [$number, $url, $issuedAt, $id]);
    }

    public static function forUser(int $userId): array
    {
        $invoice = AdminSchema::has('orders', 'invoice_url') ? ', o.invoice_url' : '';
        return db_all(
            'SELECT o.id, o.status, o.total, o.created_at' . $invoice . ', COALESCE(SUM(i.quantity), 0) AS items_count
             FROM orders o LEFT JOIN order_items i ON i.order_id = o.id
             WHERE o.user_id = ?
             GROUP BY o.id, o.status, o.total, o.created_at' . $invoice . '
             ORDER BY o.created_at DESC, o.id DESC',
            [$userId]
        );
    }

    public static function all(?string $status = null): array
    {
        $sql = 'SELECT o.id, o.status, o.total, o.payment_method, o.created_at, u.name AS customer_name, u.email AS customer_email
                FROM orders o JOIN users u ON u.id = o.user_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE o.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 500';
        return db_all($sql, $params);
    }

    /** @return array<string,int> */
    public static function countByStatus(): array
    {
        $out = array_fill_keys(ORDER_STATUSES, 0);
        foreach (db_all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Muda o status. Cancelar devolve os livros ao estoque.
     * Pedido cancelado não pode voltar a outro status.
     */
    public static function changeStatus(int $id, string $newStatus, string $note = '', ?string $expectedStatus = null): void
    {
        if (!in_array($newStatus, ORDER_STATUSES, true)) {
            throw new DomainException('Status inválido.');
        }
        $pdo = db();
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $order = db_one('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [$id]);
            if ($order === null) {
                throw new DomainException('Pedido não encontrado.');
            }
            $old = $order['status'];
            if ($expectedStatus !== null && $old !== $expectedStatus) throw new DomainException('O status mudou enquanto você estava nesta página. Recarregue antes de salvar.');
            if ($old === $newStatus) {
                if ($owns) $pdo->commit();
                return;
            }
            if ($old === 'cancelado') {
                throw new DomainException('Este pedido já foi cancelado e não pode mudar de status.');
            }
            $allowed = ['pendente' => ['pago','cancelado'], 'pago' => ['enviado','cancelado'], 'enviado' => ['entregue','cancelado'], 'entregue' => []];
            if (!in_array($newStatus, $allowed[$old] ?? [], true)) throw new DomainException('Essa mudança de status não é permitida. Siga a sequência de pagamento, envio e entrega.');
            if ($newStatus === 'cancelado') {
                $items = self::items($id);
                usort($items, static fn ($a, $b) => (int) $a['book_id'] <=> (int) $b['book_id']);
                foreach ($items as $item) {
                    if ($item['book_id'] !== null) {
                        $book = db_one('SELECT id,stock FROM books WHERE id=? FOR UPDATE', [(int) $item['book_id']]);
                        db_run('UPDATE books SET stock = stock + ? WHERE id = ?', [(int) $item['quantity'], (int) $item['book_id']]);
                        if ($book) AdminStock::record((int) $item['book_id'], 'cancelamento', (int) $item['quantity'], (int) $book['stock'] + (int) $item['quantity'], 'Cancelamento do pedido nº ' . $id);
                    }
                }
            }
            $timestamp = ['pago' => 'paid_at', 'enviado' => 'shipped_at', 'entregue' => 'delivered_at', 'cancelado' => 'cancelled_at'][$newStatus];
            $set = AdminSchema::has('orders', $timestamp) ? ", $timestamp=COALESCE($timestamp,NOW())" : '';
            db_run("UPDATE orders SET status = ?$set WHERE id = ?", [$newStatus, $id]);
            if (AdminSchema::has('order_status_history')) db_run('INSERT INTO order_status_history(order_id,user_id,old_status,new_status,note) VALUES (?,?,?,?,?)', [$id, $_SESSION['user_id'] ?? null, $old, $newStatus, $note ?: null]);
            AdminAudit::record('status', 'orders', $id, ['old' => $old, 'new' => $newStatus, 'note' => $note]);
            if ($owns) $pdo->commit();
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function setPaymentReference(int $id, ?string $reference): void
    {
        db_run('UPDATE orders SET payment_reference = ? WHERE id = ?', [$reference, $id]);
    }
}
