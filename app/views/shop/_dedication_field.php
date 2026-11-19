<?php /** @var string $id @var string $name @var string $value @var int $max @var ?string $form */ ?>
<div class="field dedication-field">
    <label for="<?= e($id) ?>">Dedicatória (opcional)</label>
    <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="4" maxlength="<?= (int) $max ?>"<?= $form ? ' form="' . e($form) . '"' : '' ?>
              data-counter="<?= e($id) ?>-count" aria-describedby="<?= e($id) ?>-count"><?= e($value) ?></textarea>
    <p class="counter" id="<?= e($id) ?>-count" data-max="<?= (int) $max ?>" aria-live="polite"><?= text_length($value) ?> de <?= (int) $max ?> caracteres</p>
</div>
