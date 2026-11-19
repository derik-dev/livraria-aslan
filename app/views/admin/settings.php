<h1>Configurações da loja</h1>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Confira os campos marcados.</div><?php endif; ?>
<form method="post" class="card form-stack" action="<?= e(url('/admin/configuracoes')) ?>"><?= csrf_field() ?>
<?php foreach ($fields as $key => $field): ?><div class="field">
<?php if ($field[1] === 'checkbox'): ?><label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= (int) ($values[$key] ?? 0) === 1 ? ' checked' : '' ?>><?= e($field[0]) ?></label>
<?php else: ?><label for="<?= e($key) ?>"><?= e($field[0]) ?></label><input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($values[$key] ?? '') ?>" type="<?= $field[1] === 'integer' ? 'number' : ($field[1] === 'email' ? 'email' : 'text') ?>"<?= $field[1] === 'integer' ? ' min="0" step="1"' : '' ?><?= isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '' ?>><?php endif; ?>
<?php if (isset($errors[$key])): ?><p class="field-error" id="err-<?= e($key) ?>"><?= e($errors[$key]) ?></p><?php endif; ?></div><?php endforeach; ?>
<button class="btn">Salvar configurações</button></form>
