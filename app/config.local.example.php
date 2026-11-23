<?php
// Copie para app/config.local.php (esse arquivo NÃO vai para o Git).
// Só o que estiver aqui substitui o app/config.php, e só na sua máquina.
return [
    'app_url' => 'http://localhost:8000',
    'debug'   => true,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'livraria',
        'user' => 'root',
        'pass' => '',
    ],
];
