<?php /** @var string $status @var array $orders @var array $counts */ ?>
<h1>Pedidos</h1>

<nav class="filter-tabs" aria-label="Filtrar por status">
    <a class="btn <?= $status === '' ? '' : 'btn-outline' ?>" href="<?= e(url('/admin/pedidos')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>Todos (<?= (int) array_sum($counts) ?>)</a>
    <?php foreach (ORDER_STATUSES as $s): ?>
        <a class="btn <?= $status === $s ? '' : 'btn-outline' ?>" href="<?= e(url('/admin/pedidos', ['status' => $s])) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>>
            <?= e(status_label($s)) ?> (<?= (int) $counts[$s] ?>)
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$orders): ?>
    <div class="empty"><p>Nenhum pedido<?= $status !== '' ? ' com status "' . e(status_label($status)) . '"' : '' ?>.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Pedido</th>
                <th scope="col">Cliente</th>
                <th scope="col">Data</th>
                <th scope="col">Total</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Ação</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td data-label="Pedido">nº <?= (int) $o['id'] ?></td>
                    <td data-label="Cliente"><?= e($o['customer_name']) ?><br><?= e($o['customer_email']) ?></td>
                    <td data-label="Data"><?= e(format_date($o['created_at'])) ?></td>
                    <td data-label="Total"><?= e(money($o['total'])) ?></td>
                    <td data-label="Status"><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                    <td><a class="btn btn-outline" href="<?= e(url('/admin/pedidos/' . (int) $o['id'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
