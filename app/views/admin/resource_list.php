<div class="section-head"><h1><?= e($definition['title']) ?></h1><a class="btn" href="<?= e(url('/admin/' . $key . '/novo')) ?>">Cadastrar</a></div>
<form class="search-form" method="get" action="<?= e(url('/admin/' . $key)) ?>" role="search">
    <label for="busca">Buscar</label><div class="search-row"><input id="busca" name="q" type="search" maxlength="100" value="<?= e($q) ?>"><button class="btn">Buscar</button></div>
</form>
<?php if (!$rows): ?><p class="empty">Nenhum registro encontrado.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr>
    <?php foreach ($definition['columns'] as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?><th scope="col">Ações</th>
</tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
    <?php foreach ($definition['columns'] as $column => $label): ?>
        <td data-label="<?= e($label) ?>"><?php
            $value = $row[$column];
            if ($column === 'active') $value = (int) $value === 1 ? 'Sim' : 'Não';
            elseif ($column === 'type') $value = $value === 'percent' ? 'Porcentagem' : 'Valor em reais';
            elseif (str_ends_with($column, '_at')) $value = $value ? format_date($value) : 'Sem prazo';
            elseif (in_array($column, ['price', 'free_from', 'min_order'], true)) $value = $value === null ? '—' : money($value);
            elseif ($column === 'value') $value = $row['type'] === 'percent' ? number_format((float) $value, 2, ',', '.') . '%' : money($value);
            echo e($value ?? '—');
        ?></td>
    <?php endforeach; ?>
    <td><a class="btn btn-outline" href="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/editar')) ?>">Editar</a></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?= render_template('admin/_pagination', compact('page', 'pages', 'total') + ['path' => '/admin/' . $key, 'filters' => ['q' => $q]]) ?>
