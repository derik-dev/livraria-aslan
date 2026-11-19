<?php
/** @var array $counts @var array $pending @var array $lowStock @var int $threshold @var int $booksTotal @var int $customersTotal */
?>
<h1>Resumo</h1>

<div class="stats">
    <a class="stat stat-strong" href="<?= e(url('/admin/pedidos', ['status' => 'pendente'])) ?>">
        <span class="stat-number"><?= (int) $counts['pendente'] ?></span>
        <span>pedidos pendentes</span>
    </a>
    <a class="stat" href="<?= e(url('/admin/pedidos', ['status' => 'pago'])) ?>">
        <span class="stat-number"><?= (int) $counts['pago'] ?></span>
        <span>pagos para enviar</span>
    </a>
    <a class="stat" href="<?= e(url('/admin/livros')) ?>">
        <span class="stat-number"><?= count($lowStock) ?></span>
        <span>livros com estoque baixo</span>
    </a>
    <a class="stat" href="<?= e(url('/admin/clientes')) ?>">
        <span class="stat-number"><?= (int) $customersTotal ?></span>
        <span>clientes</span>
    </a>
</div>

<section class="section">
    <div class="section-head">
        <h2>Pedidos pendentes</h2>
        <a href="<?= e(url('/admin/pedidos')) ?>">Ver todos os pedidos</a>
    </div>
    <?php if (!$pending): ?>
        <p>Nenhum pedido aguardando pagamento.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Pedido</th><th scope="col">Cliente</th><th scope="col">Data</th><th scope="col">Total</th><th scope="col"><span class="sr-only">Ação</span></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($pending, 0, 10) as $o): ?>
                    <tr>
                        <td data-label="Pedido">nº <?= (int) $o['id'] ?></td>
                        <td data-label="Cliente"><?= e($o['customer_name']) ?></td>
                        <td data-label="Data"><?= e(format_date($o['created_at'])) ?></td>
                        <td data-label="Total"><?= e(money($o['total'])) ?></td>
                        <td><a class="btn btn-outline" href="<?= e(url('/admin/pedidos/' . (int) $o['id'])) ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2>Estoque baixo (<?= (int) $threshold ?> ou menos)</h2>
    <?php if (!$lowStock): ?>
        <p>Nenhum livro com estoque baixo.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Livro</th><th scope="col">Estoque</th><th scope="col"><span class="sr-only">Ação</span></th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $b): ?>
                    <tr>
                        <td data-label="Livro"><strong><?= e($b['title']) ?></strong><br><?= e($b['author']) ?></td>
                        <td data-label="Estoque"><?= (int) $b['stock'] === 0 ? '<strong>Esgotado</strong>' : (int) $b['stock'] ?></td>
                        <td><a class="btn btn-outline" href="<?= e(url('/admin/livros/' . (int) $b['id'] . '/editar')) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<p><?= (int) $booksTotal ?> livro(s) no catálogo.</p>
