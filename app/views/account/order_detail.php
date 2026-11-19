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

<?php if (!empty($returns)): $check = $returns['check']; ?>
<section class="section card" id="devolucao">
    <h2>Devolução ou troca</h2>
    <p><strong><?= e($check['message']) ?></strong></p>

    <?php foreach ($returns['requests'] as $r): ?>
        <div class="return-request">
            <p><strong>Solicitação nº <?= (int) $r['id'] ?></strong> · <?= e(format_date($r['created_at'])) ?><br>
                <?= e(CustomerReturn::TYPES[$r['type']] ?? $r['type']) ?> · <?= e(CustomerReturn::REASONS[$r['reason']] ?? $r['reason']) ?><br>
                Situação: <strong><?= e(CustomerReturn::STATUS[$r['status']] ?? $r['status']) ?></strong>
                <?php if ($r['refund_amount'] !== null): ?><br>Reembolso: <?= e(money($r['refund_amount'])) ?><?php endif; ?></p>
            <ul>
                <?php foreach ($r['items'] as $it): ?><li><?= (int) $it['quantity'] ?> x <?= e($it['title']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <?php $available = array_filter($returns['items'], static fn (array $it): bool => $it['available'] > 0); ?>
    <?php if ($check['ok'] && $available): ?>
        <form method="post" action="<?= e(url('/pedidos/' . (int) $order['id'] . '/devolucao')) ?>" class="form-stack"
              data-confirm="Enviar o pedido de devolução/troca para a loja?">
            <?= csrf_field() ?>
            <fieldset class="radio-list">
                <legend>O que você quer?</legend>
                <?php foreach (CustomerReturn::TYPES as $value => $label): ?>
                    <label class="radio-option"><input type="radio" name="type" value="<?= e($value) ?>" required> <span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="field">
                <label for="reason">Motivo</label>
                <select id="reason" name="reason" required>
                    <option value="">Escolha o motivo</option>
                    <?php foreach (CustomerReturn::REASONS as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <fieldset class="return-items">
                <legend>Quais livros e quantos?</legend>
                <?php foreach ($available as $it): ?>
                    <div class="field field-qty">
                        <label for="ret-<?= (int) $it['id'] ?>"><?= e($it['title']) ?> (até <?= (int) $it['available'] ?>)</label>
                        <input id="ret-<?= (int) $it['id'] ?>" type="number" name="qty[<?= (int) $it['id'] ?>]" value="0" min="0" max="<?= (int) $it['available'] ?>" step="1" inputmode="numeric">
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <div class="field">
                <label for="description">Conte o que aconteceu (obrigatório em "Outro motivo")</label>
                <textarea id="description" name="description" rows="4" maxlength="2000"></textarea>
            </div>
            <button class="btn" type="submit">Pedir devolução ou troca</button>
        </form>
    <?php elseif ($check['ok']): ?>
        <p>Todos os livros deste pedido já estão em uma solicitação de devolução ou troca.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
