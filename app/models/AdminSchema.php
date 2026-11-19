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

    /** Tipo e tamanho de uma coluna (para não estourar o banco). @return array{type:string,length:?int}|null */
    public static function column(string $table, string $column): ?array
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $row = db_one('SELECT DATA_TYPE AS t, CHARACTER_MAXIMUM_LENGTH AS l FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column]);
            $cache[$key] = $row === null ? null : ['type' => strtolower((string) $row['t']), 'length' => $row['l'] === null ? null : (int) $row['l']];
        }
        return $cache[$key];
    }

    public static function core(): bool
    {
        return self::has('users', 'active') && self::has('books', 'min_stock') && self::has('orders', 'admin_note') && self::has('audit_logs');
    }
}
