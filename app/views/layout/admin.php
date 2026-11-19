<?php
/** @var string $content */
$pageTitle = (isset($title) && $title !== '' ? $title . ' | ' : '') . 'Painel ' . config('app_name');
$isAdmin = is_admin();
$flashes = take_flashes();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= e(url('/admin')) ?>">Painel</a>
        <nav class="main-nav" aria-label="Menu do painel">
            <?php if ($isAdmin): ?>
                <a href="<?= e(url('/admin')) ?>">Resumo</a>
                <a href="<?= e(url('/admin/livros')) ?>">Livros</a>
                <a href="<?= e(url('/admin/pedidos')) ?>">Pedidos</a>
                <a href="<?= e(url('/admin/clientes')) ?>">Clientes</a>
                <a href="<?= e(url('/')) ?>">Ver loja</a>
                <form method="post" action="<?= e(url('/admin/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-button">Sair</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>">Voltar para a loja</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="conteudo" class="container main">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= $f['type'] === 'error' ? '<strong>Atenção:</strong> ' : '' ?><?= e($f['message']) ?>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
