<?php
declare(strict_types=1);

// Servidor embutido do PHP (php -S localhost:8000 -t public): entrega arquivos estáticos direto.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($requested) && $requested !== '/' && is_file(__DIR__ . $requested)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();
require APP_ROOT . '/app/routes.php';
$router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), request_path());
