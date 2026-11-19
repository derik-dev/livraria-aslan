<?php /** @var array $book */ ?>
<article class="card book-card">
    <a href="<?= e(url('/livro/' . (int) $book['id'])) ?>" class="cover-link" tabindex="-1" aria-hidden="true">
        <img class="book-cover" src="<?= e(book_image($book['image'])) ?>" alt="" loading="lazy" width="300" height="450">
    </a>
    <h3 class="book-title"><a href="<?= e(url('/livro/' . (int) $book['id'])) ?>"><?= e($book['title']) ?></a></h3>
    <p class="book-author"><?= e($book['author']) ?></p>
    <p class="book-price"><?= e(money($book['price'])) ?></p>
    <?php if ((int) $book['stock'] <= 0): ?>
        <p class="tag">Esgotado</p>
    <?php endif; ?>
    <a class="btn btn-block" href="<?= e(url('/livro/' . (int) $book['id'])) ?>">Ver livro</a>
</article>
