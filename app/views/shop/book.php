<?php
/** @var array $book @var int $inCart @var int $maxQty */
$stock = (int) $book['stock'];
?>
<p class="breadcrumb"><a href="<?= e(url('/catalogo')) ?>">Catálogo</a> / <?= e($book['title']) ?></p>

<div class="book-detail">
    <div>
        <img class="book-cover" src="<?= e(book_image($book['image'])) ?>" alt="Capa do livro <?= e($book['title']) ?>" width="300" height="450">
    </div>
    <div>
        <h1><?= e($book['title']) ?></h1>
        <p class="book-author-lg">de <?= e($book['author']) ?></p>
        <p class="price-lg"><?= e(money($book['price'])) ?></p>

        <?php if ($stock <= 0): ?>
            <p class="tag">Esgotado</p>
            <p>Este livro está sem estoque no momento.</p>
        <?php else: ?>
            <p><?= $stock <= (int) config('shop.low_stock', 5) ? '<strong>Últimas ' . $stock . ' unidades.</strong>' : 'Em estoque.' ?></p>
            <?php if ($inCart > 0): ?>
                <p>Você já tem <?= (int) $inCart ?> no <a href="<?= e(url('/carrinho')) ?>">carrinho</a>.</p>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/carrinho/adicionar')) ?>" class="add-form">
                <?= csrf_field() ?>
                <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
                <div class="field field-qty">
                    <label for="qty">Quantidade</label>
                    <input id="qty" type="number" name="qty" value="1" min="1" max="<?= (int) $maxQty ?>" step="1" inputmode="numeric" required>
                </div>
                <?php if (!empty($allowsDedication)): ?>
                    <?= render_template('shop/_dedication_field', ['id' => 'dedication', 'name' => 'dedication', 'value' => $dedication ?? '', 'max' => $dedicationMax, 'form' => null]) ?>
                <?php endif; ?>
                <div class="buy-buttons">
                    <button class="btn" type="submit" name="buy_now" value="1">Comprar agora</button>
                    <button class="btn btn-outline" type="submit" name="buy_now" value="0">Adicionar ao carrinho</button>
                </div>
            </form>
        <?php endif; ?>

        <h2>Sobre o livro</h2>
        <div class="description"><?= nl2br(e($book['description'])) ?></div>
    </div>
</div>
