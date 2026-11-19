<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Arquivo app/config.php não encontrado. Copie app/config.example.php para app/config.php e preencha.');
}
$GLOBALS['__config'] = require $configFile;

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/Router.php';

spl_autoload_register(static function (string $class): void {
    foreach (['controllers', 'controllers/admin', 'models', 'payment'] as $dir) {
        $file = __DIR__ . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

date_default_timezone_set((string) config('timezone', 'America/Sao_Paulo'));

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(APP_ROOT . '/storage/logs') && is_writable(APP_ROOT . '/storage/logs')) {
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

// Qualquer warning/notice vira exceção: nada de página "meio quebrada".
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);
    render_error(500, $e);
});

start_session();
