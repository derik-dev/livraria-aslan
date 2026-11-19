<?php
/** @var array $profile @var array $address @var array $profile_errors @var array $address_errors @var ?string $password_error */
$pe = $profile_errors;
?>
<h1>Meu perfil</h1>
<p><a href="<?= e(url('/pedidos')) ?>">Ver meus pedidos</a></p>

<div class="profile-grid">
    <section class="card">
        <h2>Dados pessoais</h2>
        <?php if ($pe): ?><div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados.</div><?php endif; ?>
        <form method="post" action="<?= e(url('/perfil/dados')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="p-name">Nome completo</label>
                <input id="p-name" name="name" value="<?= e($profile['name'] ?? '') ?>" autocomplete="name" maxlength="100" required>
                <?php if (isset($pe['name'])): ?><p class="field-error"><?= e($pe['name']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="p-email">E-mail</label>
                <input id="p-email" type="email" name="email" value="<?= e($profile['email'] ?? '') ?>" autocomplete="email" maxlength="190" required>
                <?php if (isset($pe['email'])): ?><p class="field-error"><?= e($pe['email']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="p-phone">Telefone com DDD (opcional)</label>
                <input id="p-phone" name="phone" value="<?= e($profile['phone'] ?? '') ?>" inputmode="tel" autocomplete="tel" maxlength="20">
                <?php if (isset($pe['phone'])): ?><p class="field-error"><?= e($pe['phone']) ?></p><?php endif; ?>
            </div>
            <button class="btn" type="submit">Salvar dados</button>
        </form>
    </section>

    <section class="card">
        <h2>Endereço de entrega</h2>
        <?php if ($address_errors): ?><div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados.</div><?php endif; ?>
        <?php if (!$address && !$address_errors): ?><p>Você ainda não cadastrou um endereço.</p><?php endif; ?>
        <form method="post" action="<?= e(url('/perfil/endereco')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <?= render_template('shop/_address_fields', ['values' => $address, 'errors' => $address_errors]) ?>
            <button class="btn" type="submit">Salvar endereço</button>
        </form>
    </section>

    <section class="card">
        <h2>Alterar senha</h2>
        <?php if ($password_error !== null): ?><div class="alert alert-error" role="alert"><strong>Atenção:</strong> <?= e($password_error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('/perfil/senha')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="current_password">Senha atual</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" maxlength="72" required>
            </div>
            <div class="field">
                <label for="new_password">Senha nova (mínimo de 8 caracteres)</label>
                <input id="new_password" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
            </div>
            <div class="field">
                <label for="new_password_confirm">Repita a senha nova</label>
                <input id="new_password_confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="8" maxlength="72" required>
            </div>
            <button class="btn" type="submit">Alterar senha</button>
        </form>
    </section>
</div>
