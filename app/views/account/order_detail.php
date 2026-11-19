<?php /** @var array $order @var array $items @var ?array $instructions @var string $paymentLabel */
$steps = ['pendente', 'pago', 'enviado', 'entregue'];
$current = array_search($order['status'], $steps, true);
?>
<p class="breadcrumb"><a href="<?= e(url('/pedidos')) ?>">Meus pedidos</a> / Pedido nº <?= (int) $order['id'] ?></p>
<h1>Pedido nº <?= (int) $order['id'] ?></h1>
<p>Feito em <?= e(format_date($order['created_at'])) ?> &middot; Pagamento: <?= e($paymentLabel) ?></p>
<?php if (invoice_link($order['invoice_url'] ?? null) !== null): ?>
    <p><a class="btn" href="<?= e(invoice_link($order['invoice_url'])) ?>" target="_blank" rel="noopener noreferrer">Baixar fatura</a><?php if (!empty($order['invoice_number'])): ?> Fatura nº <?= e($order['invoice_number']) ?><?php endif; ?></p>
<?php endif; ?>

<?php if ($order['status'] === 'cancelado'): ?>
    <div class="alert alert-error" role="status"><strong>Este pedido foi cancelado.</strong></div>
<?php else: ?>
    <ol class="steps" aria-label="Andamento do pedido">
        <?php foreach ($steps as $i => $s): ?>
            <li class="<?= $current !== false && $i <= $current ? 'done' : '' ?><?= $i === $current ? ' current' : '' ?>"<?= $i === $current ? ' aria-current="step"' : '' ?>>
                <?= e(status_label($s)) ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($instructions !== null): ?>
    <?= render_template('shop/_payment_instructions', ['instructions' => $instructions]) ?>
<?php endif; ?>

<section class="section">
    <h2>Itens</h2>
    <?= render_template('shop/_order_summary', ['order' => $order, 'items' => $items]) ?>
</section>
