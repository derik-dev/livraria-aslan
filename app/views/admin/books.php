<?php /** @var string $q @var array $books */ ?>
<div class="section-head">
    <h1>Livros</h1>
    <a class="btn" href="<?= e(url('/admin/livros/novo')) ?>">Cadastrar livro</a>
</div>

<form class="search-form" method="get" action="<?= e(url('/admin/livros')) ?>" role="search">
    <label for="busca">Buscar por título ou autor</label>
    <div class="search-row">
        <input id="busca" type="search" name="q" value="<?= e($q) ?>" maxlength="100">
        <button class="btn" type="submit">Buscar</button>
    </div>
</form>

<?php if (!$books): ?>
    <div class="empty">
        <p><?= $q === '' ? 'Nenhum livro cadastrado.' : 'Nenhum livro encontrado para "' . e($q) . '".' ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th scope="col">Capa</th>
                <th scope="col">Livro</th>
                <th scope="col">Preço</th>
                <th scope="col">Estoque</th>
                <th scope="col">Destaque</th>
                <th scope="col">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($books as $b): ?>
                <tr>
                    <td data-label="Capa"><img class="thumb" src="<?= e(book_image($b['image'])) ?>" alt="" width="60" height="90"></td>
                    <td data-label="Livro"><strong><?= e($b['title']) ?></strong><br><?= e($b['author']) ?></td>
                    <td data-label="Preço"><?= e(money($b['price'])) ?></td>
                    <td data-label="Estoque"><?= (int) $b['stock'] === 0 ? '<strong>Esgotado</strong>' : (int) $b['stock'] ?></td>
                    <td data-label="Destaque"><?= (int) $b['featured'] === 1 ? 'Sim' : 'Não' ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-outline" href="<?= e(url('/admin/livros/' . (int) $b['id'] . '/editar')) ?>">Editar</a>
                            <form method="post" action="<?= e(url('/admin/livros/' . (int) $b['id'] . '/excluir')) ?>"
                                  data-confirm="Excluir o livro &quot;<?= e($b['title']) ?>&quot;? Esta ação não pode ser desfeita.">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline" type="submit">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
