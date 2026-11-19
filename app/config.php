<?php
// Configuração real da loja. Este arquivo é versionado (repositório privado).
// Preencha os campos marcados com TROQUE / SEU-DOMINIO antes de publicar.

return [
    'app_name'  => 'Livraria Aslan',
    // Endereço público da loja, sem barra no final. Usado nos links enviados por e-mail.
    'app_url'   => 'https://SEU-DOMINIO.com.br',
    // Deixe '' se a loja fica na raiz do domínio. Se ficar em subpasta, ex.: '/loja'.
    'base_path' => '',
    // true mostra detalhes de erro e o link de recuperação de senha na tela. Use false em produção.
    'debug'     => true,
    'timezone'  => 'America/Sao_Paulo',

    // Na Hostinger: hPanel > Bancos de dados > Bancos de dados MySQL.
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'u000000000_livraria',
        'user'    => 'u000000000_livraria',
        'pass'    => 'TROQUE_A_SENHA',
        'charset' => 'utf8mb4',
    ],

    // Use um e-mail do seu próprio domínio (crie em hPanel > E-mails).
    'mail' => [
        'from'      => 'nao-responda@SEU-DOMINIO.com.br',
        'from_name' => 'Livraria Aslan',
    ],

    'shop' => [
        'shipping_flat'      => 15.00,
        'free_shipping_from' => 150.00,
        'low_stock'          => 5,
        'per_page'           => 12,
        'max_qty_per_item'   => 99,
    ],

    'upload' => [
        'max_bytes' => 2 * 1024 * 1024,
    ],

    'payment' => [
        'enabled' => ['pix_manual'],
        'pix' => [
            'key'         => 'TROQUE-PELA-SUA-CHAVE-PIX',
            'key_type'    => 'E-mail',
            'beneficiary' => 'Livraria Aslan',
            'bank'        => '',
        ],
    ],
];
