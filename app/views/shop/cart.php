<?php /** @var array $summary */ ?>
<h1>Carrinho</h1>

<?php if (!$summary['items']): ?>
    <div class="empty">
        <p>Seu carrinho está vazio.</p>
        <a class="btn" href="<?= e(url('/catalogo')) ?>">Ver catálogo</a>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url('/carrinho/atualizar')) ?>" id="cart-form">
        <?= csrf_field() ?>
    </form>

    <div class="table-wrap">
        <table class="cart-table">
            <thead>
            <tr>
                <th scope="col">Livro</th>
                <th scope="col">Preço</th>
                <th scope="col">Quantidade</th>
                <th scope="col">Total</th>
                <th scope="col"><span class="sr-only">Remover</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($summary['items'] as $item): $b = $item['book']; ?>
                <tr>
                    <td data-label="Livro">
                        <a href="<?= e(url('/livro/' . (int) $b['id'])) ?>"><strong><?= e($b['title']) ?></strong></a><br>
                        <?= e($b['author']) ?>
                    </td>
                    <td data-label="Preço"><?= e(money_cents($item['price_cents'])) ?></td>
                    <td data-label="Quantidade">
                        <label class="sr-only" for="qty-<?= (int) $b['id'] ?>">Quantidade de <?= e($b['title']) ?></label>
                        <input class="qty-input" id="qty-<?= (int) $b['id'] ?>" form="cart-form" type="number"
                               name="qty[<?= (int) $b['id'] ?>]" value="<?= (int) $item['qty'] ?>"
                               min="0" max="<?= (int) min((int) $b['stock'], (int) config('shop.max_qty_per_item', 99)) ?>" step="1" inputmode="numeric">
                    </td>
                    <td data-label="Total"><?= e(money_cents($item['line_cents'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/carrinho/remover')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="book_id" value="<?= (int) $b['id'] ?>">
                            <button class="btn btn-outline" type="submit">Remover</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cart-actions">
        <button class="btn btn-outline" type="submit" form="cart-form">Atualizar quantidades</button>
        <a href="<?= e(url('/catalogo')) ?>">Continuar comprando</a>
    </div>

    <div class="summary-box">
        <dl class="totals">
            <dt>Subtotal</dt><dd><?= e(money_cents($summary['subtotal_cents'])) ?></dd>
            <dt>Frete</dt><dd><?= $summary['shipping_cents'] === 0 ? 'Grátis' : e(money_cents($summary['shipping_cents'])) ?></dd>
            <dt class="total">Total</dt><dd class="total"><?= e(money_cents($summary['total_cents'])) ?></dd>
        </dl>
        <?php $freeFrom = to_cents(config('shop.free_shipping_from', 0)); ?>
        <?php if ($freeFrom > 0 && $summary['shipping_cents'] > 0): ?>
            <p>Faltam <?= e(money_cents($freeFrom - $summary['subtotal_cents'])) ?> para o frete grátis.</p>
        <?php endif; ?>
        <a class="btn btn-block" href="<?= e(url('/checkout')) ?>">Finalizar compra</a>
    </div>
<?php endif; ?>
