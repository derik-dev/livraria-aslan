<?php /** @var array $instructions */ ?>
<section class="card card-strong">
    <h2>Como pagar</h2>
    <p><?= e($instructions['text']) ?></p>
    <dl class="info-list">
        <?php foreach ($instructions['fields'] as [$label, $value]): ?>
            <dt><?= e($label) ?></dt>
            <dd class="copyable"><?= e($value) ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php if (!empty($instructions['link'])): ?>
        <a class="btn" href="<?= e($instructions['link']) ?>" rel="noopener">Ir para o pagamento</a>
    <?php endif; ?>
</section>
