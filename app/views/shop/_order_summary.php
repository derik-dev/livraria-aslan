<?php /** @var array $order @var array $items */ ?>
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th scope="col">Livro</th>
            <th scope="col">Preço</th>
            <th scope="col">Qtd.</th>
            <th scope="col">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td data-label="Livro"><strong><?= e($it['title']) ?></strong><br><?= e($it['author']) ?></td>
                <td data-label="Preço"><?= e(money($it['unit_price'])) ?></td>
                <td data-label="Qtd."><?= (int) $it['quantity'] ?></td>
                <td data-label="Total"><?= e(money($it['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<dl class="totals">
    <dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd>
    <dt>Frete</dt><dd><?= to_cents($order['shipping']) === 0 ? 'Grátis' : e(money($order['shipping'])) ?></dd>
    <dt class="total">Total</dt><dd class="total"><?= e(money($order['total'])) ?></dd>
</dl>

<h3>Entrega</h3>
<address class="address">
    <?= e($order['ship_name']) ?><br>
    <?= e($order['ship_street']) ?>, <?= e($order['ship_number']) ?><?= $order['ship_complement'] ? ' - ' . e($order['ship_complement']) : '' ?><br>
    <?= e($order['ship_district']) ?> - <?= e($order['ship_city']) ?>/<?= e($order['ship_state']) ?><br>
    CEP <?= e($order['ship_cep']) ?>
    <?php if (!empty($order['ship_phone'])): ?><br>Telefone: <?= e($order['ship_phone']) ?><?php endif; ?>
</address>
