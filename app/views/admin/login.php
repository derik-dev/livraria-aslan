<?php /** @var string $email @var ?string $error */ ?>
<div class="narrow">
    <h1>Entrar no painel</h1>
    <p>Acesso só para administradores da loja.</p>
    <?php if ($error !== null): ?>
        <div class="alert alert-error" role="alert"><strong>Atenção:</strong> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/login')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required>
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input id="password" type="password" name="password" autocomplete="current-password" maxlength="72" required>
        </div>
        <button class="btn btn-block" type="submit">Entrar no painel</button>
    </form>
</div>
