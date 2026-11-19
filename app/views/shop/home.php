<?php /** @var array $books */ ?>
<section class="hero">
    <h1>Livros para ler devagar</h1>
    <p>Encontre seu próximo livro pelo título ou pelo autor.</p>
    <?= render_template('shop/_search_form', ['q' => '', 'id' => 'busca-home', 'label' => 'Buscar livro']) ?>
</section>

<section class="section">
    <div class="section-head">
        <h2>Destaques</h2>
        <a href="<?= e(url('/catalogo')) ?>">Ver catálogo completo</a>
    </div>
    <?php if (!$books): ?>
        <div class="empty">
            <p>Ainda não há livros cadastrados.</p>
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php foreach ($books as $book): ?>
                <?= render_template('shop/_book_card', ['book' => $book]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($latest)): ?>
<section class="section">
    <div class="section-head">
        <h2>Novidades</h2>
        <a href="<?= e(url('/catalogo')) ?>">Ver todos os livros</a>
    </div>
    <div class="book-grid">
        <?php foreach ($latest as $book): ?>
            <?= render_template('shop/_book_card', ['book' => $book]) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
