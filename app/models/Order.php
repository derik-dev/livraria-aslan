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
     */
    public static function create(int $userId, array $cart, array $ship, string $paymentMethod): int
    {
        if (!$cart) {
            throw new DomainException('Seu carrinho está vazio.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lines = [];
            $subtotal = 0;
            ksort($cart); // ordem fixa evita deadlock entre pedidos simultâneos
            foreach ($cart as $bookId => $qty) {
                $book = db_one('SELECT id, title, author, price, stock FROM books WHERE id = ? FOR UPDATE', [$bookId]);
                if ($book === null) {
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
                $lines[] = ['book' => $book, 'qty' => $qty, 'price' => $price, 'line' => $price * $qty];
                $subtotal += $price * $qty;
            }
            $shipping = shipping_cents($subtotal);

            db_run(
                'INSERT INTO orders (user_id, status, subtotal, shipping, total, payment_method,
                    ship_name, ship_phone, ship_cep, ship_street, ship_number, ship_complement, ship_district, ship_city, ship_state)
                 VALUES (?, \'pendente\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId, cents_to_decimal($subtotal), cents_to_decimal($shipping), cents_to_decimal($subtotal + $shipping),
                    $paymentMethod, $ship['name'], $ship['phone'] === '' ? null : $ship['phone'],
                    $ship['cep'], $ship['street'], $ship['number'], $ship['complement'] === '' ? null : $ship['complement'],
                    $ship['district'], $ship['city'], $ship['state'],
                ]
            );
            $orderId = (int) $pdo->lastInsertId();

            foreach ($lines as $l) {
                db_run(
                    'INSERT INTO order_items (order_id, book_id, title, author, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$orderId, (int) $l['book']['id'], $l['book']['title'], $l['book']['author'], cents_to_decimal($l['price']), $l['qty'], cents_to_decimal($l['line'])]
                );
                db_run('UPDATE books SET stock = stock - ? WHERE id = ?', [$l['qty'], (int) $l['book']['id']]);
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
        return db_all('SELECT book_id, title, author, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function forUser(int $userId): array
    {
        return db_all(
            'SELECT o.id, o.status, o.total, o.created_at, COALESCE(SUM(i.quantity), 0) AS items_count
             FROM orders o LEFT JOIN order_items i ON i.order_id = o.id
             WHERE o.user_id = ?
             GROUP BY o.id, o.status, o.total, o.created_at
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
    public static function changeStatus(int $id, string $newStatus): void
    {
        if (!in_array($newStatus, ORDER_STATUSES, true)) {
            throw new DomainException('Status inválido.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = db_one('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [$id]);
            if ($order === null) {
                throw new DomainException('Pedido não encontrado.');
            }
            $old = $order['status'];
            if ($old === $newStatus) {
                $pdo->rollBack();
                return;
            }
            if ($old === 'cancelado') {
                throw new DomainException('Este pedido já foi cancelado e não pode mudar de status.');
            }
            if ($newStatus === 'cancelado') {
                foreach (self::items($id) as $item) {
                    if ($item['book_id'] !== null) {
                        db_run('UPDATE books SET stock = stock + ? WHERE id = ?', [(int) $item['quantity'], (int) $item['book_id']]);
                    }
                }
            }
            db_run('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
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
