<?php
declare(strict_types=1);

final class AdminLogin
{
    public static function authenticate(string $email, string $password, bool $adminOnly = false): array
    {
        $tracked = AdminSchema::has('login_attempts');
        $ip = AdminAudit::ip();
        if ($tracked) {
            $since = date('Y-m-d H:i:s', time() - 900);
            $emailFailures = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE email=? AND success=0 AND created_at>=?', [$email, $since]);
            $ipFailures = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND success=0 AND created_at>=?', [$ip, $since]);
            if ($emailFailures >= 5 || $ipFailures >= 20) return ['user' => null, 'blocked' => true];
        }
        $user = valid_email($email) ? User::findByEmailWithHash($email) : null;
        // Hash fixo evita omitir o custo de verificação quando o e-mail não existe.
        $verified = password_verify($password, $user['password_hash'] ?? '$2y$12$2tX9FrTFwXnBN0luCOTLZ.sIsM3xhWLqgZe0/7MHcno9oLF93P96a');
        $success = $user !== null && $verified && (int) ($user['active'] ?? 1) === 1 && (!$adminOnly || $user['role'] === 'admin');
        if ($tracked) db_run('INSERT INTO login_attempts(email,ip,success) VALUES (?,?,?)', [$email, $ip, $success ? 1 : 0]);
        if (!$success) return ['user' => null, 'blocked' => false];
        User::rehashIfNeeded((int) $user['id'], $password, (string) $user['password_hash']);
        if (AdminSchema::has('users', 'last_login_at')) db_run('UPDATE users SET last_login_at=NOW() WHERE id=?', [(int) $user['id']]);
        return ['user' => $user, 'blocked' => false];
    }
}
