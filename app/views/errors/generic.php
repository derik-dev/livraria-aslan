<?php /** @var int $code @var string $title @var string $message @var ?string $detail */ ?>
<div class="error-page">
    <p class="error-code"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <div class="actions">
        <a class="btn" href="<?= e(url('/')) ?>">Ir para o início</a>
    </div>
    <?php if (!empty($detail)): ?>
        <h2>Detalhes (debug ligado)</h2>
        <pre class="debug"><?= e($detail) ?></pre>
    <?php endif; ?>
</div>
