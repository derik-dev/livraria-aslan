<?php /** @var array $blocks */
$labels = static fn (string $key): string => ucfirst(str_replace('_', ' ', $key)); ?>
<div class="no-print">
    <h1>Dedicatórias a preparar</h1>
    <?php if ($blocks): ?>
        <p><?= count($blocks) ?> livro(s) com dedicatória. Cada bloco da impressão é um livro.</p>
        <p><button class="btn" type="button" data-print>Imprimir dedicatórias</button></p>
    <?php endif; ?>
</div>

<?php if (!$blocks): ?>
    <div class="empty"><p>Nenhuma dedicatória para preparar agora.</p></div>
<?php else: ?>
    <div class="dedication-sheet">
        <?php foreach ($blocks as $b):
            $copies = max(1, min(50, (int) ($b['qty'] ?? 1))); ?>
            <?php for ($i = 1; $i <= $copies; $i++): ?>
                <article class="dedication-block">
                    <p class="dedication-order">Pedido nº <?= e($b['order'] ?? '-') ?><?= $copies > 1 ? ' · exemplar ' . $i . ' de ' . $copies : '' ?></p>
                    <h2 class="dedication-title"><?= e($b['title'] ?? 'Livro sem título') ?></h2>
                    <p class="dedication-recipient"><strong>Para:</strong> <?= e($b['recipient'] ?? 'não informado') ?></p>
                    <p class="dedication-body"><?= e($b['text'] ?? '') ?></p>
                    <?php if ($b['extra']): ?>
                        <dl class="dedication-extra no-print">
                            <?php foreach ($b['extra'] as $key => $value): ?>
                                <dt><?= e($labels($key)) ?></dt><dd><?= e($value) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($b['order'] !== null): ?><p class="no-print"><a href="<?= e(url('/admin/pedidos/' . (int) $b['order'])) ?>">Ver pedido</a></p><?php endif; ?>
                </article>
            <?php endfor; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
