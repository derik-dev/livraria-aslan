<?php
declare(strict_types=1);

final class User
{
    public static function find(int $id): ?array
    {
        $extra = AdminSchema::has('users', 'active') ? ', active, last_login_at' : '';
        return db_one('SELECT id, name, email, phone, role, created_at' . $extra . ' FROM users WHERE id = ?', [$id]);
    }

    /** Inclui password_hash: use só para autenticação. */
    public static function findByEmailWithHash(string $email): ?array
    {
        $extra = AdminSchema::has('users', 'active') ? ', active' : '';
        return db_one('SELECT id, name, email, password_hash, role' . $extra . ' FROM users WHERE email = ?', [$email]);
    }

    public static function passwordHash(int $id): ?string
    {
        $v = db_value('SELECT password_hash FROM users WHERE id = ?', [$id]);
        return $v === null ? null : (string) $v;
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            return db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0;
        }
        return db_value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $exceptId]) > 0;
    }

    public static function create(string $name, string $email, string $password, ?string $phone = null): int
    {
        db_run(
            'INSERT INTO users (name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, 'customer']
        );
        return (int) db()->lastInsertId();
    }

    public static function updateProfile(int $id, string $name, string $email, ?string $phone): void
    {
        db_run('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function rehashIfNeeded(int $id, string $password, string $hash): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            self::updatePassword($id, $password);
        }
    }

    public static function customers(string $search = ''): array
    {
        $sql = 'SELECT u.id, u.name, u.email, u.phone, u.created_at,
                       COUNT(o.id) AS orders_count,
                       COALESCE(SUM(CASE WHEN o.status IN (\'pago\',\'enviado\',\'entregue\') THEN o.total ELSE 0 END), 0) AS orders_total
                FROM users u
                LEFT JOIN orders o ON o.user_id = u.id
                WHERE u.role = \'customer\'';
        $params = [];
        if ($search !== '') {
            $like = '%' . like_escape($search) . '%';
            $sql .= " AND (u.name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
            $params = [$like, $like];
        }
        $sql .= ' GROUP BY u.id, u.name, u.email, u.phone, u.created_at ORDER BY u.created_at DESC LIMIT 500';
        return db_all($sql, $params);
    }
}
