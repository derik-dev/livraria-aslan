<?php /** @var string $token @var ?string $error */ ?>
<div class="narrow">
    <h1>Criar senha nova</h1>
    <?php if ($error !== null): ?>
        <div class="alert alert-error" role="alert"><strong>Atenção:</strong> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/redefinir-senha')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label for="password">Senha nova (mínimo de 8 caracteres)</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
        </div>
        <div class="field">
            <label for="password_confirm">Repita a senha nova</label>
            <input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="8" maxlength="72" required>
        </div>
        <button class="btn btn-block" type="submit">Salvar senha nova</button>
    </form>
</div>
