<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Páginas">
    <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= e(url($path, $filters + ['pagina' => $page - 1])) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= (int) $page ?> de <?= (int) $pages ?> · <?= (int) $total ?> registros</span>
    <?php if ($page < $pages): ?><a class="btn btn-outline" href="<?= e(url($path, $filters + ['pagina' => $page + 1])) ?>">Próxima</a><?php endif; ?>
</nav>
<?php endif; ?>
