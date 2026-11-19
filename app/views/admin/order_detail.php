<?php /** @var array $order @var array $items @var string $paymentLabel */ ?>
<p class="breadcrumb"><a href="<?= e(url('/admin/pedidos')) ?>">Pedidos</a> / Pedido nº <?= (int) $order['id'] ?></p>
<h1>Pedido nº <?= (int) $order['id'] ?></h1>

<div class="profile-grid">
    <section class="card">
        <h2>Status</h2>
        <p>Atual: <span class="status status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></p>
        <?php if ($order['status'] === 'cancelado'): ?>
            <p>Pedido cancelado não pode mudar de status. Os livros já voltaram ao estoque.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/pedidos/' . (int) $order['id'] . '/status')) ?>" class="form-stack"
                  data-confirm-select="cancelado" data-confirm="Cancelar este pedido? Os livros voltam ao estoque e o pedido não pode ser reaberto.">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="status">Mudar para</label>
                    <select id="status" name="status">
                        <?php foreach (ORDER_STATUSES as $s): ?>
                            <option value="<?= e($s) ?>"<?= $order['status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">Salvar status</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Cliente</h2>
        <p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['customer_email']) ?></p>
        <p>Feito em <?= e(format_date($order['created_at'])) ?><br>
            Atualizado em <?= e(format_date($order['updated_at'])) ?></p>
        <p>Pagamento: <?= e($paymentLabel) ?>
            <?php if (!empty($order['payment_reference'])): ?><br>Referência: <?= e($order['payment_reference']) ?><?php endif; ?></p>
    </section>
</div>

<section class="section">
    <h2>Itens</h2>
    <?= render_template('shop/_order_summary', ['order' => $order, 'items' => $items]) ?>
</section>
