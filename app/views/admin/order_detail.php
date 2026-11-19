<?php /** @var array $order @var array $items @var string $paymentLabel */ ?>
<p class="breadcrumb"><a href="<?= e(url('/admin/pedidos')) ?>">Pedidos</a> / Pedido nº <?= (int) $order['id'] ?></p>
<h1>Pedido nº <?= (int) $order['id'] ?></h1>
<?php if (!empty($order['cancel_requested_at']) && !in_array($order['status'],['cancelado','entregue'],true)): ?><section class="card section"><h2>Solicitação de cancelamento</h2><p>Recebida em <?= e(format_date($order['cancel_requested_at'])) ?></p><?php if (!empty($order['cancel_reason'])): ?><p class="preserve-lines"><?= e($order['cancel_reason']) ?></p><?php endif; ?><form method="post" class="form-stack" action="<?= e(url('/admin/pedidos/'.(int)$order['id'].'/cancelamento')) ?>" data-confirm="Registrar esta decisão sobre o cancelamento?"><?= csrf_field() ?><input type="hidden" name="expected_request" value="<?= e($order['cancel_requested_at']) ?>"><div class="field"><label for="decision">Decisão</label><select id="decision" name="decision"><option value="recusar">Recusar solicitação</option><option value="aprovar">Aprovar cancelamento e repor estoque</option></select></div><div class="field"><label for="cancel_note">Motivo da decisão</label><textarea id="cancel_note" name="note" rows="3" maxlength="200" required></textarea></div><button class="btn">Registrar decisão</button></form></section><?php endif; ?>

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
                <input type="hidden" name="expected_status" value="<?= e($order['status']) ?>">
                <div class="field">
                    <label for="status">Mudar para</label>
                    <select id="status" name="status">
                        <?php foreach (ORDER_STATUSES as $s): ?>
                            <option value="<?= e($s) ?>"<?= $order['status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label for="status_note">Observação para o histórico</label><input id="status_note" name="status_note" maxlength="255"></div>
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

<?php if (array_key_exists('invoice_number', $order)):
    $issued = !empty($order['invoice_issued_at']) ? substr((string) $order['invoice_issued_at'], 0, 10) : ''; ?>
<section class="section card">
    <h2>Fatura</h2>
    <p><strong>Nome na fatura:</strong> <?= e($order['invoice_name'] ?? '') ?: 'não informado' ?><br>
        <strong>NIF:</strong> <?= !empty($order['invoice_nif']) ? e($order['invoice_nif']) : 'consumidor final (sem NIF)' ?></p>
    <p>Emita a fatura no seu programa de faturação certificado e registe aqui o número e o link. O site não emite fatura oficial.</p>
    <form method="post" class="form-stack" action="<?= e(url('/admin/pedidos/' . (int) $order['id'] . '/fatura')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="invoice_number">Número da fatura</label><input id="invoice_number" name="invoice_number" value="<?= e($order['invoice_number'] ?? '') ?>" maxlength="60"></div>
            <div class="field"><label for="invoice_issued_at">Data de emissão</label><input id="invoice_issued_at" name="invoice_issued_at" type="date" value="<?= e($issued) ?>"></div>
            <div class="field field-wide"><label for="invoice_url">Link do PDF da fatura (https://)</label><input id="invoice_url" name="invoice_url" type="url" value="<?= e($order['invoice_url'] ?? '') ?>" maxlength="500" placeholder="https://"></div>
        </div>
        <button class="btn">Salvar dados da fatura</button>
    </form>
    <?php if (invoice_link($order['invoice_url'] ?? null) !== null): ?><p><a href="<?= e(invoice_link($order['invoice_url'])) ?>" target="_blank" rel="noopener noreferrer">Abrir a fatura salva</a></p><?php endif; ?>
</section>
<?php endif; ?>

<section class="section">
    <h2>Itens</h2>
    <?= render_template('shop/_order_summary', ['order' => $order, 'items' => $items]) ?>
</section>
<?php if (array_key_exists('admin_note',$order)): ?><section class="section card"><h2>Entrega e observações internas</h2><form method="post" class="form-stack" action="<?= e(url('/admin/pedidos/'.(int)$order['id'].'/dados')) ?>"><?= csrf_field() ?><input type="hidden" name="expected_metadata" value="<?= e(hash('sha256',(string)$order['tracking_code']."\n".(string)$order['admin_note'])) ?>"><div class="field"><label for="tracking_code">Código de rastreio</label><input id="tracking_code" name="tracking_code" value="<?= e($order['tracking_code']) ?>" maxlength="40"></div><div class="field"><label for="admin_note">Observações internas (visíveis somente no painel)</label><textarea id="admin_note" name="admin_note" rows="4" maxlength="5000"><?= e($order['admin_note']) ?></textarea></div><button class="btn">Salvar dados</button></form></section><?php endif; ?>
<section class="section"><div class="section-head"><h2>Pagamentos</h2><a href="<?= e(url('/admin/pagamentos',['pedido'=>(int)$order['id']])) ?>">Gerenciar pagamentos deste pedido</a></div><?php if (!$payments): ?><p>Nenhum pagamento registrado.</p><?php else: ?><ul><?php foreach ($payments as $payment): ?><li><a href="<?= e(url('/admin/pagamentos/'.(int)$payment['id'])) ?>">Pagamento nº <?= (int)$payment['id'] ?></a> · <?= e(money($payment['amount'])) ?> · <?= e(ucfirst($payment['status'])) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="section"><h2>Linha do tempo</h2><?php if (!$history): ?><p>Não há etapas registradas antes da atualização do painel.</p><?php else: ?><ol class="timeline"><?php foreach ($history as $event): ?><li><strong><?= e(status_label($event['new_status'])) ?></strong> · <?= e(format_date($event['created_at'])) ?><br><?= e($event['actor']??'Sistema') ?><?php if ($event['old_status']): ?> · antes: <?= e(status_label($event['old_status'])) ?><?php endif; ?><?php if ($event['note']): ?><p class="preserve-lines"><?= e($event['note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section>
