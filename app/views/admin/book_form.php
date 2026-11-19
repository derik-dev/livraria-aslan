<?php
/** @var ?array $book @var array $values @var array $errors @var int $maxBytes */
$action = $book === null ? url('/admin/livros') : url('/admin/livros/' . (int) $book['id']);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : '';
$aria = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
?>
<p class="breadcrumb"><a href="<?= e(url('/admin/livros')) ?>">Livros</a> / <?= $book === null ? 'Novo livro' : e($book['title']) ?></p>
<h1><?= $book === null ? 'Novo livro' : 'Editar livro' ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Atenção:</strong> confira os campos marcados abaixo.<?= isset($errors['image']) ? '' : ' Se você escolheu uma imagem, selecione de novo.' ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card form-stack" novalidate>
    <?= csrf_field() ?>
    <?php if ($book): ?><input type="hidden" name="expected_stock" value="<?= (int) $book['stock'] ?>"><?php endif; ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) $maxBytes ?>">

    <div class="field">
        <label for="title">Título</label>
        <input id="title" name="title" value="<?= e($values['title']) ?>" maxlength="200" required<?= $aria('title') ?>>
        <?= $err('title') ?>
    </div>
    <div class="field">
        <label for="author">Autor</label>
        <input id="author" name="author" value="<?= e($values['author']) ?>" maxlength="150" required<?= $aria('author') ?>>
        <?= $err('author') ?>
    </div>
    <div class="field">
        <label for="description">Descrição</label>
        <textarea id="description" name="description" rows="6" maxlength="5000" required<?= $aria('description') ?>><?= e($values['description']) ?></textarea>
        <?= $err('description') ?>
    </div>
    <div class="form-grid">
        <div class="field">
            <label for="price">Preço em reais (ex.: 39,90)</label>
            <input id="price" name="price" value="<?= e($values['price']) ?>" inputmode="decimal" maxlength="20" required<?= $aria('price') ?>>
            <?= $err('price') ?>
        </div>
        <div class="field">
            <label for="stock">Estoque</label>
            <input id="stock" type="number" name="stock" value="<?= e($values['stock']) ?>" min="0" max="100000" step="1" required<?= $aria('stock') ?>>
            <?= $err('stock') ?>
        </div>
    </div>

    <?php if ($extended): ?>
    <div class="form-grid">
        <div class="field"><label for="category_id">Categoria</label><select id="category_id" name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"<?= (int) $values['category_id'] === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?><?= (int) $category['active'] ? '' : ' (inativa)' ?></option><?php endforeach; ?></select><?= $err('category_id') ?></div>
        <?php foreach (['isbn' => 'ISBN', 'publisher' => 'Editora', 'pages' => 'Páginas', 'cost_price' => 'Custo em reais', 'min_stock' => 'Estoque mínimo'] as $name => $label): ?>
        <div class="field"><label for="<?= e($name) ?>"><?= e($label) ?></label><input id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($values[$name]) ?>"<?= in_array($name, ['pages','min_stock'], true) ? ' type="number" min="0" step="1"' : '' ?><?= $aria($name) ?>><?= $err($name) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="field"><label for="stock_note">Motivo da alteração de estoque</label><input id="stock_note" name="stock_note" maxlength="255" value="<?= e($values['stock_note']) ?>"></div>
    <label class="check"><input type="checkbox" name="active" value="1"<?= (int) $values['active'] === 1 ? ' checked' : '' ?>>Livro ativo na loja</label>
    <?php endif; ?>
    <label class="check">
        <input type="checkbox" name="featured" value="1"<?= (int) $values['featured'] === 1 ? ' checked' : '' ?>>
        Mostrar nos destaques da página inicial
    </label>

    <div class="field">
        <label for="image">Imagem da capa (JPG, PNG ou WEBP, até <?= e(number_format($maxBytes / 1024 / 1024, 0)) ?> MB)</label>
        <?php if (!empty($values['image'])): ?>
            <div class="current-image">
                <img class="thumb-lg" src="<?= e(book_image($values['image'])) ?>" alt="Capa atual" width="120" height="180">
                <label class="check">
                    <input type="checkbox" name="remove_image" value="1">
                    Remover a capa atual
                </label>
            </div>
            <p>Para trocar a capa, escolha um arquivo novo.</p>
        <?php endif; ?>
        <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"<?= $aria('image') ?>>
        <?= $err('image') ?>
    </div>

    <div class="actions">
        <button class="btn" type="submit"><?= $book === null ? 'Cadastrar livro' : 'Salvar alterações' ?></button>
        <a class="btn btn-outline" href="<?= e(url('/admin/livros')) ?>">Voltar</a>
        <?php if ($book !== null): ?>
            <a href="<?= e(url('/livro/' . (int) $book['id'])) ?>">Ver na loja</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($book !== null): ?>
    <form method="post" action="<?= e(url('/admin/livros/' . (int) $book['id'] . '/excluir')) ?>" class="danger-zone"
          data-confirm="Excluir o livro &quot;<?= e($book['title']) ?>&quot;? Esta ação não pode ser desfeita.">
        <?= csrf_field() ?>
        <h2>Excluir livro</h2>
        <p>O livro sai da loja. Pedidos antigos continuam mostrando o título e o preço da época.</p>
        <button class="btn btn-outline" type="submit">Excluir este livro</button>
    </form>
<?php endif; ?>
