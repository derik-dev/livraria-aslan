<?php /** @var string $q @var string $id @var string $label */ ?>
<form class="search-form" method="get" action="<?= e(url('/catalogo')) ?>" role="search"
      data-suggest-url="<?= e(url('/api/busca')) ?>">
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <div class="search-box">
        <div class="search-row">
            <input id="<?= e($id) ?>" type="search" name="q" value="<?= e($q) ?>" maxlength="100" autocomplete="off"
                   aria-autocomplete="list" aria-controls="<?= e($id) ?>-sugestoes" aria-expanded="false">
            <button class="btn" type="submit">Buscar</button>
        </div>
        <div class="suggest" id="<?= e($id) ?>-sugestoes" hidden></div>
    </div>
    <p class="sr-only" aria-live="polite" data-suggest-status></p>
</form>
