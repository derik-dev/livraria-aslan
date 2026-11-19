<?php
declare(strict_types=1);

/**
 * Cálculo do frete. É a ÚNICA fonte do valor: carrinho, barra de frete grátis
 * e criação do pedido usam esta classe. O valor mostrado em JS é só visual.
 */
final class Shipping
{
    /**
     * Faixa de frete para o CEP/estado. Sem CEP, usa a faixa padrão (sem estado e sem CEP).
     * Com CEP: faixa de CEP > faixa do estado > padrão.
     */
    public static function rate(?string $cep, ?string $state): ?array
    {
        if (!AdminSchema::has('shipping_rates')) {
            return null;
        }
        $cep = only_digits((string) $cep);
        $state = strtoupper(trim((string) $state));
        if (strlen($cep) !== 8) {
            return db_one(
                'SELECT * FROM shipping_rates WHERE active = 1 AND state IS NULL AND cep_start IS NULL AND cep_end IS NULL
                 ORDER BY price, id LIMIT 1'
            );
        }
        return db_one(
            'SELECT * FROM shipping_rates
             WHERE active = 1 AND (
                   (cep_start IS NOT NULL AND cep_end IS NOT NULL AND ? BETWEEN cep_start AND cep_end)
                OR (cep_start IS NULL AND cep_end IS NULL AND state = ?)
                OR (cep_start IS NULL AND cep_end IS NULL AND state IS NULL))
             ORDER BY (cep_start IS NOT NULL) DESC, (state IS NOT NULL) DESC, price, id
             LIMIT 1',
            [$cep, $state]
        );
    }

    /**
     * @return array{shipping_cents:int, free_from_cents:int, missing_cents:int, label:?string, days:?string}
     */
    public static function quote(int $subtotalCents, ?string $cep = null, ?string $state = null): array
    {
        $rate = self::rate($cep, $state);
        $price = $rate !== null ? to_cents($rate['price']) : to_cents(config('shop.shipping_flat', 0));
        $freeFrom = ($rate !== null && $rate['free_above'] !== null)
            ? to_cents($rate['free_above'])
            : to_cents(config('shop.free_shipping_from', 0)); // settings.free_shipping_min

        if ($subtotalCents <= 0) {
            $shipping = 0;
        } elseif ($freeFrom > 0 && $subtotalCents >= $freeFrom) {
            $shipping = 0;
        } else {
            $shipping = max(0, $price);
        }
        return [
            'shipping_cents'  => $shipping,
            'free_from_cents' => max(0, $freeFrom),
            'missing_cents'   => $freeFrom > 0 ? max(0, $freeFrom - $subtotalCents) : 0,
            'label'           => $rate['name'] ?? null,
            'days'            => $rate !== null ? $rate['days_min'] . ' a ' . $rate['days_max'] . ' dias úteis' : null,
        ];
    }

    /** CEP e estado do endereço salvo do cliente logado (ou nada, para visitante). */
    public static function location(): array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return ['cep' => null, 'state' => null];
        }
        $address = Address::forUser($id);
        return ['cep' => $address['cep'] ?? null, 'state' => $address['state'] ?? null];
    }

    public static function barEnabled(): bool
    {
        return setting('free_shipping_bar_enabled', '0') === '1';
    }
}
