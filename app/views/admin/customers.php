<?php /** @var string $q @var array $customers */ ?>
<h1>Clientes</h1>

<form class="search-form" method="get" action="<?= e(url('/admin/clientes')) ?>" role="search">
    <label for="busca">Buscar por nome ou e-mail</label>
    <div class="search-row">
        <input id="busca" type="search" name="q" value="<?= e($q) ?>" maxlength="100">
        <button class="btn" type="submit">Buscar</button>
    </div>
</form>

<?php if (!$customers): ?>
    <div class="empty"><p><?= $q === '' ? 'Nenhum cliente cadastrado.' : 'Nenhum cliente encontrado para "' . e($q) . '".' ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Nome</th>
                <th scope="col">E-mail</th>
                <th scope="col">Telefone</th>
                <th scope="col">Cliente desde</th>
                <th scope="col">Pedidos</th>
                <th scope="col">Total comprado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td data-label="Nome"><a href="<?= e(url('/admin/clientes/' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a><?php if (isset($c['active']) && !(int)$c['active']): ?><br><strong>Bloqueado</strong><?php endif; ?></td>
                    <td data-label="E-mail"><?= e($c['email']) ?></td>
                    <td data-label="Telefone"><?= e($c['phone'] ?? '') ?: '-' ?></td>
                    <td data-label="Cliente desde"><?= e(format_date($c['created_at'], false)) ?></td>
                    <td data-label="Pedidos"><?= (int) $c['orders_count'] ?></td>
                    <td data-label="Total comprado"><?= e(money($c['orders_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?= render_template('admin/_pagination',compact('page','pages','total')+['path'=>'/admin/clientes','filters'=>['q'=>$q]]) ?>
