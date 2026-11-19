<?php
declare(strict_types=1);

/**
 * Pedido de devolução ou troca feito pelo cliente em "Meus pedidos".
 * Regra: só depois de entregue e dentro do prazo (settings.return_window_days, padrão 7 dias)
 * contado a partir da data de entrega. Depois disso, não é mais possível pedir.
 */
final class CustomerReturn
{
    public const TYPES = [
        'devolucao' => 'Devolução (quero o dinheiro de volta)',
        'troca'     => 'Troca por outro exemplar',
    ];
    public const REASONS = [
        'defeito'        => 'O livro veio com defeito ou danificado',
        'item_errado'    => 'Recebi o livro errado',
        'arrependimento' => 'Desisti da compra',
        'outro'          => 'Outro motivo',
    ];
    public const STATUS = [
        'solicitada' => 'Recebida, aguardando análise da loja',
        'aprovada'   => 'Aprovada: envie o livro para a loja',
        'recusada'   => 'Recusada',
        'recebida'   => 'Livro recebido pela loja',
        'concluida'  => 'Concluída',
    ];

    public static function enabled(): bool
    {
        return AdminSchema::has('returns') && AdminSchema::has('return_items');
    }

    public static function windowDays(): int
    {
        return max(1, (int) setting('return_window_days', '7'));
    }

    /** Data da entrega: coluna delivered_at, senão o histórico, senão a última atualização. */
    public static function deliveredAt(array $order): ?DateTimeImmutable
    {
        $raw = $order['delivered_at'] ?? null;
        if (!$raw && AdminSchema::has('order_status_history')) {
            $raw = db_value("SELECT MIN(created_at) FROM order_status_history WHERE order_id = ? AND new_status = 'entregue'", [(int) $order['id']]);
        }
        if (!$raw) {
            $raw = $order['updated_at'] ?? null;
        }
        if (!$raw) {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $raw);
        } catch (Exception) {
            return null;
        }
    }

    /** @return array{ok:bool, message:string, deadline:?DateTimeImmutable} */
    public static function eligibility(array $order): array
    {
        if ($order['status'] !== 'entregue') {
            return ['ok' => false, 'deadline' => null,
                'message' => 'A devolução ou troca pode ser pedida depois que o pedido for entregue, em até ' . self::windowDays() . ' dias.'];
        }
        $delivered = self::deliveredAt($order);
        if ($delivered === null) {
            return ['ok' => false, 'deadline' => null, 'message' => 'Não encontramos a data de entrega deste pedido. Fale com a loja.'];
        }
        $deadline = $delivered->modify('+' . self::windowDays() . ' days');
        if (new DateTimeImmutable() > $deadline) {
            return ['ok' => false, 'deadline' => $deadline,
                'message' => 'O prazo de ' . self::windowDays() . ' dias para devolução ou troca terminou em ' . $deadline->format('d/m/Y \à\s H:i') . '. Este pedido não pode mais ser devolvido.'];
        }
        return ['ok' => true, 'deadline' => $deadline,
            'message' => 'Você pode pedir devolução ou troca até ' . $deadline->format('d/m/Y \à\s H:i') . ' (' . self::windowDays() . ' dias após a entrega).'];
    }

    /** Itens do pedido com quanto ainda pode ser devolvido (desconta pedidos não recusados). */
    public static function items(int $orderId): array
    {
        $rows = db_all(
            "SELECT oi.id, oi.title, oi.quantity,
                    COALESCE((SELECT SUM(ri.quantity) FROM return_items ri JOIN `returns` r ON r.id = ri.return_id
                              WHERE ri.order_item_id = oi.id AND r.status <> 'recusada'), 0) AS requested
             FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id",
            [$orderId]
        );
        foreach ($rows as &$r) {
            $r['available'] = max(0, (int) $r['quantity'] - (int) $r['requested']);
        }
        unset($r);
        return $rows;
    }

    public static function forOrder(int $orderId, int $userId): array
    {
        $returns = db_all('SELECT * FROM `returns` WHERE order_id = ? AND user_id = ? ORDER BY id DESC', [$orderId, $userId]);
        foreach ($returns as &$r) {
            $r['items'] = db_all('SELECT ri.quantity, oi.title FROM return_items ri JOIN order_items oi ON oi.id = ri.order_item_id WHERE ri.return_id = ?', [(int) $r['id']]);
        }
        unset($r);
        return $returns;
    }

    /**
     * Cria a solicitação. Confere tudo de novo dentro da transação (prazo, dono, quantidades).
     * @param array<int,int> $quantities [order_item_id => quantidade]
     */
    public static function create(int $orderId, int $userId, string $type, string $reason, string $description, array $quantities): int
    {
        if (!isset(self::TYPES[$type])) throw new DomainException('Escolha se é devolução ou troca.');
        if (!isset(self::REASONS[$reason])) throw new DomainException('Escolha o motivo.');
        if ($reason === 'outro' && text_length($description) < 5) throw new DomainException('Conte em poucas palavras o motivo.');
        $quantities = array_filter($quantities, static fn (int $q): bool => $q > 0);
        if (!$quantities) throw new DomainException('Escolha pelo menos 1 livro e a quantidade.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $order = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE', [$orderId, $userId]);
            if ($order === null) throw new DomainException('Pedido não encontrado.');
            $check = self::eligibility($order);
            if (!$check['ok']) throw new DomainException($check['message']);

            $items = [];
            foreach (self::items($orderId) as $it) {
                $items[(int) $it['id']] = $it;
            }
            foreach ($quantities as $itemId => $qty) {
                if (!isset($items[$itemId])) throw new DomainException('Um livro escolhido não é deste pedido.');
                if ($qty > $items[$itemId]['available']) {
                    throw new DomainException('"' . $items[$itemId]['title'] . '": você pode devolver no máximo ' . $items[$itemId]['available'] . ' unidade(s).');
                }
            }
            db_run('INSERT INTO `returns` (order_id, user_id, type, reason, description) VALUES (?, ?, ?, ?, ?)',
                [$orderId, $userId, $type, $reason, $description === '' ? null : $description]);
            $returnId = (int) $pdo->lastInsertId();
            foreach ($quantities as $itemId => $qty) {
                db_run('INSERT INTO return_items (return_id, order_item_id, quantity) VALUES (?, ?, ?)', [$returnId, $itemId, $qty]);
            }
            $pdo->commit();
            return $returnId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
