<?php
/** @var string $q @var array $books @var int $total @var int $page @var int $pages */
?>
<h1>Catálogo</h1>

<form class="search-form" method="get" action="<?= e(url('/catalogo')) ?>" role="search">
    <label for="busca">Buscar por título ou autor</label>
    <div class="search-row">
        <input id="busca" type="search" name="q" value="<?= e($q) ?>" maxlength="100" autocomplete="off">
        <button class="btn" type="submit">Buscar</button>
    </div>
</form>

<?php if ($q !== ''): ?>
    <p class="result-info">
        <?= $total === 1 ? '1 livro encontrado' : (int) $total . ' livros encontrados' ?> para "<?= e($q) ?>".
        <a href="<?= e(url('/catalogo')) ?>">Limpar busca</a>
    </p>
<?php endif; ?>

<?php if (!$books): ?>
    <div class="empty">
        <?php if ($q !== ''): ?>
            <p>Nenhum livro com "<?= e($q) ?>" no título ou no autor.</p>
            <p>Confira a grafia ou tente só uma parte do nome.</p>
            <a class="btn" href="<?= e(url('/catalogo')) ?>">Ver todos os livros</a>
        <?php else: ?>
            <p>Ainda não há livros cadastrados.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php foreach ($books as $book): ?>
            <?= render_template('shop/_book_card', ['book' => $book]) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Páginas do catálogo">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline" href="<?= e(url('/catalogo', array_filter(['q' => $q, 'pagina' => $page - 1]))) ?>">Anterior</a>
            <?php endif; ?>
            <span>Página <?= (int) $page ?> de <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-outline" href="<?= e(url('/catalogo', array_filter(['q' => $q, 'pagina' => $page + 1]))) ?>">Próxima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
