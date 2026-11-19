<?php
// Modelo de configuração. Copie para app/config.php e preencha.

return [
    'app_name'  => 'Livraria Aslan',
    // Endereço público da loja, sem barra no final. Usado nos links enviados por e-mail.
    'app_url'   => 'https://SEU-DOMINIO.com.br',
    // Deixe '' se a loja fica na raiz do domínio. Se ficar em subpasta, ex.: '/loja'.
    'base_path' => '',
    // true mostra detalhes de erro e o link de recuperação de senha na tela. Use false em produção.
    'debug'     => false,
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
        'shipping_flat'      => 15.00,  // frete fixo em reais
        'free_shipping_from' => 150.00, // frete grátis a partir deste subtotal (0 desliga)
        'low_stock'          => 5,      // painel avisa quando estoque <= este número
        'per_page'           => 12,
        'max_qty_per_item'   => 99,
    ],

    'upload' => [
        'max_bytes' => 2 * 1024 * 1024, // 2 MB
    ],

    'payment' => [
        // Formas de pagamento ativas no checkout (códigos das classes em app/payment).
        'enabled' => ['pix_manual'],
        'pix' => [
            'key'         => 'TROQUE-PELA-SUA-CHAVE-PIX',
            'key_type'    => 'E-mail',
            'beneficiary' => 'Livraria Aslan',
            'bank'        => '',
        ],
        // Quando integrar o Mercado Pago:
        // 'mercadopago' => ['access_token' => '', 'public_key' => '', 'webhook_secret' => ''],
    ],
];
