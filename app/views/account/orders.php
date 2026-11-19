<?php /** @var array $orders */ ?>
<h1>Meus pedidos</h1>

<?php if (!$orders): ?>
    <div class="empty">
        <p>Você ainda não fez nenhum pedido.</p>
        <a class="btn" href="<?= e(url('/catalogo')) ?>">Ver catálogo</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Pedido</th>
                <th scope="col">Data</th>
                <th scope="col">Livros</th>
                <th scope="col">Total</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Ação</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td data-label="Pedido">nº <?= (int) $o['id'] ?></td>
                    <td data-label="Data"><?= e(format_date($o['created_at'], false)) ?></td>
                    <td data-label="Livros"><?= (int) $o['items_count'] ?></td>
                    <td data-label="Total"><?= e(money($o['total'])) ?></td>
                    <td data-label="Status"><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                    <td><a class="btn btn-outline" href="<?= e(url('/pedidos/' . (int) $o['id'])) ?>">Ver detalhes</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
