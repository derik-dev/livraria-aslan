<?php
/** @var array $values @var array $errors */
$v = static fn (string $k): string => (string) ($values[$k] ?? '');
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
};
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
?>
<div class="form-grid">
    <div class="field">
        <label for="cep">CEP</label>
        <input id="cep" name="cep" value="<?= e($v('cep')) ?>" inputmode="numeric" autocomplete="postal-code" maxlength="9" required<?= $aria('cep') ?>>
        <?= $err('cep') ?>
    </div>
    <div class="field field-wide">
        <label for="street">Rua</label>
        <input id="street" name="street" value="<?= e($v('street')) ?>" autocomplete="address-line1" maxlength="150" required<?= $aria('street') ?>>
        <?= $err('street') ?>
    </div>
    <div class="field">
        <label for="number">Número</label>
        <input id="number" name="number" value="<?= e($v('number')) ?>" maxlength="20" required<?= $aria('number') ?>>
        <?= $err('number') ?>
    </div>
    <div class="field">
        <label for="complement">Complemento (opcional)</label>
        <input id="complement" name="complement" value="<?= e($v('complement')) ?>" autocomplete="address-line2" maxlength="100">
    </div>
    <div class="field">
        <label for="district">Bairro</label>
        <input id="district" name="district" value="<?= e($v('district')) ?>" maxlength="100" required<?= $aria('district') ?>>
        <?= $err('district') ?>
    </div>
    <div class="field">
        <label for="city">Cidade</label>
        <input id="city" name="city" value="<?= e($v('city')) ?>" autocomplete="address-level2" maxlength="100" required<?= $aria('city') ?>>
        <?= $err('city') ?>
    </div>
    <div class="field">
        <label for="state">Estado</label>
        <select id="state" name="state" required<?= $aria('state') ?>>
            <option value="">Escolha</option>
            <?php foreach (BR_STATES as $uf): ?>
                <option value="<?= e($uf) ?>"<?= $v('state') === $uf ? ' selected' : '' ?>><?= e($uf) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $err('state') ?>
    </div>
</div>
