<?php
declare(strict_types=1);

final class StoreSettings
{
    private static ?array $values = null;

    public static function all(): array
    {
        if (self::$values === null) {
            self::$values = [];
            if (AdminSchema::has('settings')) {
                foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
                    self::$values[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
        return self::$values;
    }

    public static function override(string $key, mixed $fallback): mixed
    {
        if ($key === 'payment.pix') {
            $settings = self::all(); $pix = (array) $fallback;
            if (($settings['pix_key'] ?? '') !== '') $pix['key'] = $settings['pix_key'];
            if (($settings['pix_holder'] ?? '') !== '') $pix['beneficiary'] = $settings['pix_holder'];
            return $pix;
        }
        $map = ['app_name' => 'store_name', 'mail.from' => 'store_email', 'payment.pix.key' => 'pix_key',
            'payment.pix.beneficiary' => 'pix_holder', 'shop.shipping_flat' => 'shipping_fixed',
            'shop.free_shipping_from' => 'free_shipping_min'];
        if (!isset($map[$key])) return $fallback;
        $value = self::all()[$map[$key]] ?? null;
        return $value === null || $value === '' ? $fallback : $value;
    }
}
