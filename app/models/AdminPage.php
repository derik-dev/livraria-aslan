<?php
declare(strict_types=1);

final class AdminPage
{
    public static function fetch(string $select, string $from, array $params = [], string $order = 'id DESC'): array
    {
        $total = (int) db_value('SELECT COUNT(*) ' . $from, $params);
        $pages = max(1, (int) ceil($total / 25));
        $page = min(max(1, input_int('pagina', $_GET) ?? 1), $pages);
        $rows = db_all($select . ' ' . $from . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', [...$params, 25, ($page - 1) * 25]);
        return compact('rows','total','pages','page');
    }
}
