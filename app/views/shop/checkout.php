<?php
/** @var array $summary @var array $form @var array $errors @var array<string,PaymentGateway> $gateways @var string $method */
?>
<h1>Finalizar compra</h1>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados abaixo.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/checkout')) ?>" class="checkout" data-once>
    <?= csrf_field() ?>
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
    </div>

    <aside class="summary-box">
        <h2>Resumo</h2>
        <ul class="summary-list">
            <?php foreach ($summary['items'] as $item): ?>
                <li>
                    <span><?= (int) $item['qty'] ?> x <?= e($item['book']['title']) ?></span>
                    <span><?= e(money_cents($item['line_cents'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl class="totals">
            <dt>Subtotal</dt><dd><?= e(money_cents($summary['subtotal_cents'])) ?></dd>
            <dt>Frete</dt><dd><?= $summary['shipping_cents'] === 0 ? 'Grátis' : e(money_cents($summary['shipping_cents'])) ?></dd>
            <dt class="total">Total</dt><dd class="total"><?= e(money_cents($summary['total_cents'])) ?></dd>
        </dl>
        <button class="btn btn-block" type="submit"<?= $gateways ? '' : ' disabled' ?>>Confirmar pedido</button>
        <p><a href="<?= e(url('/carrinho')) ?>">Voltar ao carrinho</a></p>
    </aside>
</form>
