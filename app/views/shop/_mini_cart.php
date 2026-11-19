<?php /** @var ?array $mini resumo do carrinho (null se não deu para carregar) @var int $count */ ?>
<details class="mini-cart">
    <summary>Carrinho (<?= (int) $count ?>)</summary>
    <div class="mini-cart-panel">
        <?php if ($mini === null || !$mini['items']): ?>
            <p>Seu carrinho está vazio.</p>
            <a class="btn btn-block" href="<?= e(url('/carrinho')) ?>">Ver carrinho</a>
        <?php else: ?>
            <ul class="mini-cart-list">
                <?php foreach ($mini['items'] as $item): ?>
                    <li>
                        <span><?= (int) $item['qty'] ?> x <?= e($item['book']['title']) ?></span>
                        <strong><?= e(money_cents($item['line_cents'])) ?></strong>
                        <?php if ($item['dedication'] !== ''): ?><span class="mini-cart-ded">Com dedicatória</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mini-cart-total">Subtotal: <strong><?= e(money_cents($mini['subtotal_cents'])) ?></strong></p>
            <?= render_template('shop/_free_shipping_bar', ['summary' => $mini, 'live' => false]) ?>
            <a class="btn btn-block" href="<?= e(url('/carrinho')) ?>">Ver carrinho</a>
            <a class="btn btn-outline btn-block" href="<?= e(url('/checkout')) ?>">Finalizar compra</a>
        <?php endif; ?>
    </div>
</details>
