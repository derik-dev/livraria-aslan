<?php
declare(strict_types=1);

final class AdminReports
{
    public static function period(string $start, string $end): array
    {
        $today = new DateTimeImmutable('today');
        $a = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $b = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$a || !$b || $a->format('Y-m-d') !== $start || $b->format('Y-m-d') !== $end || $a > $b || $a->diff($b)->days > 366) {
            throw new DomainException('Escolha um período válido de até 367 dias.');
        }
        return [$a->format('Y-m-d H:i:s'), $b->modify('+1 day')->format('Y-m-d H:i:s')];
    }

    public static function sales(string $start, string $end): array
    {
        [$from,$until] = self::period($start,$end);
        $where = "o.status IN ('pago','enviado','entregue') AND o.created_at>=? AND o.created_at<?";
        $daily = db_all("SELECT DATE(o.created_at) AS day,COUNT(*) AS orders_count,SUM(o.total) AS revenue FROM orders o WHERE $where GROUP BY DATE(o.created_at) ORDER BY day", [$from,$until]);
        $top = db_all("SELECT i.book_id,i.title,SUM(i.quantity) AS units_sold,SUM(i.line_total) AS revenue FROM order_items i JOIN orders o ON o.id=i.order_id WHERE $where GROUP BY i.book_id,i.title ORDER BY units_sold DESC,revenue DESC,i.title LIMIT 10", [$from,$until]);
        $revenue = 0; $count = 0;
        foreach ($daily as $row) { $revenue += to_cents($row['revenue']); $count += (int) $row['orders_count']; }
        return compact('daily','top','revenue','count');
    }

    public static function attention(): array
    {
        $items = [];
        foreach ([['reviews','pendente','Avaliações a aprovar','avaliacoes'],['contact_messages','nova','Mensagens novas','mensagens'],['returns','solicitada','Devoluções solicitadas','devolucoes']] as [$table,$status,$label,$path]) {
            if (AdminSchema::has($table)) $items[] = ['label' => $label, 'count' => (int) db_value("SELECT COUNT(*) FROM `$table` WHERE status=?", [$status]), 'path' => '/admin/' . $path . '?status=' . $status];
        }
        if (AdminSchema::has('orders','cancel_requested_at')) $items[] = ['label' => 'Pedidos de cancelamento', 'count' => (int) db_value("SELECT COUNT(*) FROM orders WHERE cancel_requested_at IS NOT NULL AND status NOT IN ('cancelado','entregue')"), 'path' => '/admin/pedidos?cancelamento=1'];
        return $items;
    }
}
