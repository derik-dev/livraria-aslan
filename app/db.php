<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = (array) config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) ($c['host'] ?? 'localhost'),
        (int) ($c['port'] ?? 3306),
        (string) ($c['name'] ?? ''),
        (string) ($c['charset'] ?? 'utf8mb4')
    );

    $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Mantém NOW()/CURRENT_TIMESTAMP no mesmo fuso do PHP.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}

/** Executa uma query preparada. Parâmetros sempre posicionais (?). */
function db_run(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    foreach (array_values($params) as $i => $value) {
        if (is_bool($value)) {
            $value = (int) $value;
        }
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $st->bindValue($i + 1, $value === null ? null : (is_int($value) ? $value : (string) $value), $type);
    }
    $st->execute();
    return $st;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = []): mixed
{
    $v = db_run($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
