<?php
declare(strict_types=1);

/** Registro das formas de pagamento. Único ponto que conhece as classes concretas. */
final class Payment
{
    private const GATEWAYS = [
        'pix_manual' => PixManualGateway::class,
        // 'mercadopago' => MercadoPagoGateway::class,
    ];

    /** @return array<string, PaymentGateway> */
    public static function available(): array
    {
        $out = [];
        foreach ((array) config('payment.enabled', ['pix_manual']) as $code) {
            if (is_string($code) && isset(self::GATEWAYS[$code])) {
                $class = self::GATEWAYS[$code];
                $out[$code] = new $class();
            }
        }
        return $out;
    }

    public static function get(string $code): ?PaymentGateway
    {
        if (!isset(self::GATEWAYS[$code])) {
            return null;
        }
        $class = self::GATEWAYS[$code];
        return new $class();
    }
}
