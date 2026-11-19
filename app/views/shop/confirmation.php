<?php /** @var array $order @var array $items @var ?array $instructions */ ?>
<section class="confirm-head">
    <h1>Pedido nº <?= (int) $order['id'] ?> recebido</h1>
    <p>Obrigado! Seu pedido foi registrado em <?= e(format_date($order['created_at'])) ?>.</p>
    <p class="status-line">Status: <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
</section>

<?php if ($instructions !== null): ?>
    <?= render_template('shop/_payment_instructions', ['instructions' => $instructions]) ?>
<?php endif; ?>

<section class="section">
    <h2>Resumo do pedido</h2>
    <?= render_template('shop/_order_summary', ['order' => $order, 'items' => $items]) ?>
</section>

<div class="actions">
    <a class="btn" href="<?= e(url('/pedidos/' . (int) $order['id'])) ?>">Acompanhar pedido</a>
    <a class="btn btn-outline" href="<?= e(url('/catalogo')) ?>">Continuar comprando</a>
</div>
