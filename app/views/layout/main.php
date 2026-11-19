<?php
/** @var string $content */
$pageTitle = isset($title) && $title !== '' ? $title . ' | ' . config('app_name') : config('app_name');
$me = current_user();
$cartCount = cart_count();
$flashes = take_flashes();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= e(url('/')) ?>"><?= e(config('app_name')) ?></a>
        <nav class="main-nav" aria-label="Menu principal">
            <a href="<?= e(url('/catalogo')) ?>">Catálogo</a>
            <a href="<?= e(url('/carrinho')) ?>">Carrinho (<?= $cartCount ?>)</a>
            <?php if ($me !== null): ?>
                <a href="<?= e(url('/pedidos')) ?>">Meus pedidos</a>
                <a href="<?= e(url('/perfil')) ?>">Perfil</a>
                <?php if ($me['role'] === 'admin'): ?>
                    <a href="<?= e(url('/admin')) ?>">Painel</a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-button">Sair</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>">Entrar</a>
                <a href="<?= e(url('/cadastro')) ?>">Criar conta</a>
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

<footer class="site-footer">
    <div class="container">
        <p><?= e(config('app_name')) ?> &middot; <?= date('Y') ?></p>
        <p>Pagamento por Pix. Dúvidas sobre um pedido? Veja em <a href="<?= e(url('/pedidos')) ?>">Meus pedidos</a>.</p>
    </div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
