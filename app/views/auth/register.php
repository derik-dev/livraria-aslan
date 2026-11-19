<?php /** @var array $form @var array $errors */
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '';
?>
<div class="narrow">
    <h1>Criar conta</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados abaixo.</div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/cadastro')) ?>" class="card form-stack" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="name">Nome completo</label>
            <input id="name" name="name" value="<?= e($form['name'] ?? '') ?>" autocomplete="name" maxlength="100" required<?= $aria('name') ?>>
            <?= $err('name') ?>
        </div>
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= e($form['email'] ?? '') ?>" autocomplete="email" maxlength="190" required<?= $aria('email') ?>>
            <?= $err('email') ?>
        </div>
        <div class="field">
            <label for="phone">Telefone com DDD (opcional)</label>
            <input id="phone" name="phone" value="<?= e($form['phone'] ?? '') ?>" inputmode="tel" autocomplete="tel" maxlength="20"<?= $aria('phone') ?>>
            <?= $err('phone') ?>
        </div>
        <div class="field">
            <label for="password">Senha (mínimo de 8 caracteres)</label>
            <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required<?= $aria('password') ?>>
            <?= $err('password') ?>
        </div>
        <div class="field">
            <label for="password_confirm">Repita a senha</label>
            <input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="8" maxlength="72" required>
        </div>
        <button class="btn btn-block" type="submit">Criar conta</button>
        <p>Já tem conta? <a href="<?= e(url('/login')) ?>">Entrar</a></p>
    </form>
</div>
