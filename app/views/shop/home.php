<?php /** @var array $books */ ?>
<section class="hero">
    <h1>Livros para ler devagar</h1>
    <p>Encontre seu próximo livro pelo título ou pelo autor.</p>
    <form class="search-form" method="get" action="<?= e(url('/catalogo')) ?>" role="search">
        <label for="busca-home">Buscar livro</label>
        <div class="search-row">
            <input id="busca-home" type="search" name="q" maxlength="100" autocomplete="off">
            <button class="btn" type="submit">Buscar</button>
        </div>
    </form>
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
