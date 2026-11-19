<?php
/** @var array $summary @var bool $live (atualiza em JS quando a quantidade muda) */
if (!Shipping::barEnabled() || ($summary['free_from_cents'] ?? 0) <= 0 || !$summary['items']) {
    return;
}
$free = (int) $summary['free_from_cents'];
$sub = (int) $summary['subtotal_cents'];
$missing = max(0, $free - $sub);
$pct = (int) min(100, floor($sub * 100 / $free));
?>
<div class="free-bar" data-free-from="<?= $free ?>"<?= !empty($live) ? ' data-free-live' : '' ?>>
    <p class="free-bar-text" aria-live="polite">
        <?php if ($missing > 0): ?>
            Faltam <strong><?= e(money_cents($missing)) ?></strong> para frete grátis
        <?php else: ?>
            <strong>Você ganhou frete grátis</strong>
        <?php endif; ?>
    </p>
    <div class="free-bar-track" aria-hidden="true"><div class="free-bar-fill" style="width: <?= $pct ?>%"></div></div>
    <p class="free-bar-note">Valor conferido de novo no checkout, pelo seu endereço.</p>
</div>
