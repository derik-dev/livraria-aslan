<?php
/** @var array $summary @var array $form @var array $errors @var array<string,PaymentGateway> $gateways @var string $method */
?>
<h1>Finalizar compra</h1>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados abaixo.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/checkout')) ?>" class="checkout" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="<?= $buyNow ? 'agora' : 'carrinho' ?>">
    <input type="hidden" name="shown_total" value="<?= (int) $summary['total_cents'] ?>">
    <div class="checkout-main">
        <section class="card">
            <h2>1. Entrega</h2>
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="name">Nome de quem recebe</label>
                    <input id="name" name="name" value="<?= e($form['name'] ?? '') ?>" autocomplete="name" maxlength="100" required<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="err-name"' : '' ?>>
                    <?php if (isset($errors['name'])): ?><p class="field-error" id="err-name"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="phone">Telefone com DDD (opcional)</label>
                    <input id="phone" name="phone" value="<?= e($form['phone'] ?? '') ?>" inputmode="tel" autocomplete="tel" maxlength="20"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="err-phone"' : '' ?>>
                    <?php if (isset($errors['phone'])): ?><p class="field-error" id="err-phone"><?= e($errors['phone']) ?></p><?php endif; ?>
                </div>
            </div>
            <?= render_template('shop/_address_fields', ['values' => $form, 'errors' => $errors]) ?>
            <p>O endereço fica salvo no seu perfil para as próximas compras.</p>
        </section>

        <section class="card">
            <h2>2. Pagamento</h2>
            <?php if (!$gateways): ?>
                <p><strong>Nenhuma forma de pagamento está ativa.</strong> Fale com a loja.</p>
            <?php endif; ?>
            <fieldset class="radio-list">
                <legend class="sr-only">Forma de pagamento</legend>
                <?php foreach ($gateways as $code => $g): ?>
                    <label class="radio-option">
                        <input type="radio" name="payment_method" value="<?= e($code) ?>"<?= $method === $code ? ' checked' : '' ?> required>
                        <span><strong><?= e($g->label()) ?></strong><br><?= e($g->description()) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (isset($errors['payment_method'])): ?><p class="field-error"><?= e($errors['payment_method']) ?></p><?php endif; ?>
        </section>

        <?php if (!empty($invoiceEnabled)): ?>
        <section class="card">
            <h2>3. Fatura com NIF</h2>
            <p><?= !empty($nifRequired) ? 'O NIF é obrigatório para finalizar a compra.' : 'Quer o NIF na fatura? Preencha abaixo. Sem NIF, a fatura sai como consumidor final.' ?></p>
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="invoice_name">Nome na fatura</label>
                    <input id="invoice_name" name="invoice_name" value="<?= e($form['invoice_name'] ?? '') ?>" autocomplete="name" maxlength="150"<?= isset($errors['invoice_name']) ? ' aria-invalid="true" aria-describedby="err-invoice_name"' : '' ?>>
                    <?php if (isset($errors['invoice_name'])): ?><p class="field-error field-error-lg" id="err-invoice_name"><?= e($errors['invoice_name']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="invoice_nif">NIF<?= !empty($nifRequired) ? ' (obrigatório)' : ' (opcional)' ?></label>
                    <input id="invoice_nif" name="invoice_nif" value="<?= e($form['invoice_nif'] ?? '') ?>" inputmode="numeric" autocomplete="off" maxlength="14"<?= !empty($nifRequired) ? ' required' : '' ?><?= isset($errors['invoice_nif']) ? ' aria-invalid="true" aria-describedby="err-invoice_nif"' : '' ?>>
                    <?php if (isset($errors['invoice_nif'])): ?><p class="field-error field-error-lg" id="err-invoice_nif" role="alert"><?= e($errors['invoice_nif']) ?></p><?php endif; ?>
                </div>
            </div>
            <p>Para trocar o NIF salvo, vá em <a href="<?= e(url('/perfil')) ?>">Meu perfil</a>.</p>
        </section>
        <?php endif; ?>
    </div>

    <aside class="summary-box">
        <h2>Resumo</h2>
        <ul class="summary-list">
            <?php foreach ($summary['items'] as $item): ?>
                <li>
                    <span><?= (int) $item['qty'] ?> x <?= e($item['book']['title']) ?></span>
                    <span><?= e(money_cents($item['line_cents'])) ?></span>
                    <?php if ($item['dedication'] !== ''): ?>
                        <span class="dedication-text"><strong>Dedicatória:</strong> <?= e($item['dedication']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl class="totals">
            <dt>Subtotal</dt><dd><?= e(money_cents($summary['subtotal_cents'])) ?></dd>
            <dt>Frete</dt><dd><?= $summary['shipping_cents'] === 0 ? 'Grátis' : e(money_cents($summary['shipping_cents'])) ?></dd>
            <dt class="total">Total</dt><dd class="total"><?= e(money_cents($summary['total_cents'])) ?></dd>
        </dl>
        <?php if (!empty($summary['shipping_days'])): ?><p>Entrega: <?= e($summary['shipping_days']) ?>.</p><?php endif; ?>
        <p>O frete é calculado pelo CEP da entrega. Se você mudar o endereço, o total é conferido antes de fechar o pedido.</p>
        <button class="btn btn-block" type="submit"<?= $gateways ? '' : ' disabled' ?>>Confirmar pedido</button>
        <p><a href="<?= e(url($backTo)) ?>"><?= $buyNow ? 'Voltar ao livro' : 'Voltar ao carrinho' ?></a></p>
        <?php if ($buyNow): ?>
            <p>Compra direta: só este livro. Seu carrinho continua guardado.</p>
        <?php endif; ?>
    </aside>
</form>
