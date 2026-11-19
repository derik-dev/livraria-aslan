<?php
declare(strict_types=1);

/** Regras da dedicatória (texto opcional escrito no livro). */
final class Dedication
{
    /** Ligado nas configurações e com as colunas no banco. */
    public static function enabled(): bool
    {
        return setting('dedication_enabled', '0') === '1'
            && AdminSchema::has('cart_items', 'dedication')
            && AdminSchema::has('order_items', 'dedication')
            && AdminSchema::has('books', 'allows_dedication');
    }

    public static function allowed(?array $book): bool
    {
        return $book !== null && (int) ($book['allows_dedication'] ?? 0) === 1 && self::enabled();
    }

    /** Limite das configurações, sem passar do tamanho das colunas do banco. */
    public static function max(): int
    {
        $max = (int) setting('dedication_max_chars', '200');
        foreach (['cart_items', 'order_items'] as $table) {
            $col = AdminSchema::column($table, 'dedication');
            if ($col !== null && $col['length'] !== null && $col['length'] > 0) {
                $max = min($max, $col['length']);
            }
        }
        return max(1, $max);
    }

    /** Maior limite que o banco aceita (para a tela de configurações). */
    public static function columnLimit(): int
    {
        $limit = 65535;
        foreach (['cart_items', 'order_items'] as $table) {
            $col = AdminSchema::has($table, 'dedication') ? AdminSchema::column($table, 'dedication') : null;
            if ($col !== null && $col['length'] !== null && $col['length'] > 0) {
                $limit = min($limit, $col['length']);
            }
        }
        return $limit;
    }

    /**
     * Limpa o texto e confere o limite. Devolve [texto ('' = sem dedicatória), erro ou null].
     * Não corta em silêncio: se passar do limite, devolve erro.
     */
    public static function read(mixed $raw): array
    {
        $text = clean_string(is_string($raw) ? $raw : '', 20000);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);
        $max = self::max();
        $len = text_length($text);
        if ($len > $max) {
            return [$text, "A dedicatória tem {$len} caracteres. O limite é de {$max}. Encurte o texto."];
        }
        return [$text, null];
    }
}
