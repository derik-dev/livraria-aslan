<?php
declare(strict_types=1);

final class PasswordReset
{
    private const TTL_SECONDS = 3600;

    /** Cria um token novo e devolve o token em texto puro (só ele vai no e-mail). */
    public static function create(int $userId): ?string
    {
        // Evita spam: no máximo um pedido por minuto por conta.
        $recent = db_value(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND used_at IS NULL AND created_at > ?',
            [$userId, date('Y-m-d H:i:s', time() - 60)]
        );
        if ($recent > 0) {
            return null;
        }
        db_run('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        $token = bin2hex(random_bytes(32));
        db_run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TTL_SECONDS), date('Y-m-d H:i:s')]
        );
        return $token;
    }

    /** Retorna o registro se o token for válido, não usado e não expirado. */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = db_one(
            'SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ?',
            [hash('sha256', $token)]
        );
        if ($row === null || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return $row;
    }

    public static function consume(array $reset, string $newPassword): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            User::updatePassword((int) $reset['user_id'], $newPassword);
            db_run('DELETE FROM password_resets WHERE user_id = ?', [(int) $reset['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
