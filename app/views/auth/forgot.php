<?php /** @var string $email @var ?string $error */ ?>
<div class="narrow">
    <h1>Recuperar senha</h1>
    <p>Informe o e-mail da sua conta. Vamos enviar um link para você criar uma senha nova. O link vale por 1 hora.</p>
    <?php if ($error !== null): ?>
        <div class="alert alert-error" role="alert"><strong>Atenção:</strong> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/recuperar-senha')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" maxlength="190" required>
        </div>
        <button class="btn btn-block" type="submit">Enviar link</button>
        <p><a href="<?= e(url('/login')) ?>">Voltar para entrar</a></p>
    </form>
</div>
