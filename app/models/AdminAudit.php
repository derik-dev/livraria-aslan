<?php
declare(strict_types=1);

final class AdminAudit
{
    public static function record(string $action, string $entity, ?int $id = null, array $details = []): void
    {
        if (!AdminSchema::has('audit_logs')) {
            return;
        }
        db_run('INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)', [
            $_SESSION['user_id'] ?? null, $action, $entity, $id,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            self::ip(),
        ]);
    }

    public static function ip(): string
    {
        // Não confia em X-Forwarded-For enviado pelo visitante.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function transaction(callable $action): mixed
    {
        $pdo = db();
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $result = $action();
            if ($owns) $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
