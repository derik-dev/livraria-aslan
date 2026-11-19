<?php
declare(strict_types=1);

/** NIF português: 9 dígitos com dígito de controle módulo 11. */
final class Nif
{
    /** Tira espaços, pontos, hífens e o prefixo "PT". */
    public static function clean(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if (str_starts_with($raw, 'PT')) {
            $raw = substr($raw, 2);
        }
        return (string) preg_replace('/[\s.\-]/', '', $raw);
    }

    public static function valid(string $nif): bool
    {
        if (!preg_match('/^\d{9}$/D', $nif)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += (int) $nif[$i] * (9 - $i);
        }
        $check = 11 - ($sum % 11);
        if ($check >= 10) {
            $check = 0;
        }
        return $check === (int) $nif[8];
    }

    /**
     * Lê o NIF digitado. Devolve [nif limpo, erro ou null].
     * Vazio é aceito (consumidor final) quando não for obrigatório.
     */
    public static function read(string $raw, bool $required): array
    {
        $nif = self::clean($raw);
        if ($nif === '') {
            return ['', $required ? 'Informe o NIF. Ele é obrigatório para emitir a fatura.' : null];
        }
        if (!preg_match('/^\d{9}$/D', $nif)) {
            return [$nif, 'O NIF precisa ter 9 números. Confira o que foi digitado.'];
        }
        if (!self::valid($nif)) {
            return [$nif, 'Este NIF não é válido: o último número não confere. Confira os 9 números.'];
        }
        return [$nif, null];
    }

    public static function required(): bool
    {
        return setting('invoice_nif_required', '0') === '1';
    }

    /** As colunas de fatura existem no banco? */
    public static function enabled(): bool
    {
        return AdminSchema::has('orders', 'invoice_nif');
    }
}
