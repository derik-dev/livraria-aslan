<?php
declare(strict_types=1);

final class AdminSchema
{
    public static function has(string $table, ?string $column = null): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $column === null
                ? (bool) db_value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])
                : (bool) db_value('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column]);
        }
        return $cache[$key];
    }

    public static function core(): bool
    {
        return self::has('users', 'active') && self::has('books', 'min_stock') && self::has('orders', 'admin_note') && self::has('audit_logs');
    }
}
