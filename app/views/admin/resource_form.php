<p class="breadcrumb"><a href="<?= e(url('/admin/' . $key)) ?>"><?= e($definition['title']) ?></a> / <?= $row ? 'Editar' : 'Cadastrar' ?></p>
<h1><?= $row ? 'Editar' : 'Cadastrar' ?> · <?= e($definition['title']) ?></h1>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?= e($errors['_form'] ?? 'Confira os campos marcados abaixo.') ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/' . $key . ($row ? '/' . (int) $row['id'] : ''))) ?>" class="card form-stack" enctype="multipart/form-data">
<?= csrf_field() ?>
<?php foreach ($definition['fields'] as $name => $field):
    [$label, $type] = $field; $value = $values[$name] ?? ''; $error = $errors[$name] ?? null;
    $aria = $error ? ' aria-invalid="true" aria-describedby="err-' . e($name) . '"' : '';
    if ($type === 'datetime' && $value) $value = str_replace(' ', 'T', substr((string) $value, 0, 16));
?>
<div class="field">
<?php if ($type === 'checkbox'): ?>
    <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= (int) $value === 1 ? ' checked' : '' ?>><?= e($label) ?></label>
<?php else: ?>
    <label for="<?= e($name) ?>"><?= e($label) ?></label>
    <?php if ($type === 'select'): ?><select id="<?= e($name) ?>" name="<?= e($name) ?>"<?= $aria ?>><?php foreach ($field[3] as $option => $text): ?><option value="<?= e($option) ?>"<?= (string) $option === (string) $value ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?></select>
    <?php elseif ($type === 'textarea'): ?><textarea id="<?= e($name) ?>" name="<?= e($name) ?>" maxlength="<?= (int) ($field[3] ?? 5000) ?>" rows="4"<?= $aria ?>><?= e($value) ?></textarea>
    <?php else: ?><input id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" type="<?= $type === 'datetime' ? 'datetime-local' : ($type === 'integer' ? 'number' : 'text') ?>"<?= $type === 'money' ? ' inputmode="decimal"' : '' ?><?= $type === 'integer' ? ' min="0" max="' . (int) ($field[3] ?? 100000) . '" step="1"' : '' ?><?= !empty($field[2]) ? ' required' : '' ?><?= $aria ?>><?php endif; ?>
<?php endif; ?>
<?php if ($error): ?><p class="field-error" id="err-<?= e($name) ?>"><?= e($error) ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (isset($definition['image'])): ?><div class="field"><label for="image">Imagem (JPG, PNG ou WEBP, até 2 MB)</label><?php if (!empty($row['image'])): ?><img class="thumb-lg" src="<?= e(book_image($row['image'])) ?>" alt="Imagem atual"><?php endif; ?><input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"><?php if (isset($errors['image'])): ?><p class="field-error"><?= e($errors['image']) ?></p><?php endif; ?></div><?php endif; ?>
<div class="actions"><button class="btn" type="submit">Salvar</button><a class="btn btn-outline" href="<?= e(url('/admin/' . $key)) ?>">Voltar</a></div>
</form>
<?php if ($row && $definition['delete']): ?><form method="post" class="danger-zone" action="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/excluir')) ?>" data-confirm="Excluir este registro? Esta ação não pode ser desfeita."><?= csrf_field() ?><button class="btn btn-outline">Excluir</button></form><?php endif; ?>
<?php if ($key === 'cupons' && $row): ?><section class="section"><h2>Uso do cupom</h2><p><?= (int) $row['used_count'] ?> usos registrados. Pedidos recentes:</p><?php if (!$extra): ?><p>Nenhum pedido com este cupom.</p><?php else: ?><ul><?php foreach ($extra as $order): ?><li><a href="<?= e(url('/admin/pedidos/' . (int) $order['id'])) ?>">Pedido nº <?= (int) $order['id'] ?></a> · <?= e($order['customer_name']) ?> · <?= e(money($order['discount'])) ?> de desconto · <?= e(status_label($order['status'])) ?></li><?php endforeach; ?></ul><?php endif; ?></section><?php endif; ?>
