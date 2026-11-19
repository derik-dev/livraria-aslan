<?php
declare(strict_types=1);

final class Cart
{
    /** Resumo do carrinho (ajusta o carrinho guardado se algo mudou no estoque). */
    public static function summary(): array
    {
        $wanted = cart();
        $summary = self::build($wanted, cart_dedications());
        foreach ($wanted as $id => $qty) {
            $new = $summary['map'][$id] ?? 0;
            if ($new !== $qty) {
                cart_set($id, $new);
            }
        }
        return $summary;
    }

    /** Resumo sem gravar nada (mini-carrinho do topo). */
    public static function peek(): array
    {
        return self::build(cart(), cart_dedications());
    }

    /** Resumo da compra direta ("Comprar agora"), sem tocar no carrinho. */
    public static function buyNowSummary(): array
    {
        $summary = self::build(buy_now_items(), buy_now_dedications());
        $_SESSION['buy_now'] = $summary['map'];
        return $summary;
    }

    /**
     * Monta o resumo com preços e estoque atuais do banco.
     * Remove itens que sumiram ou esgotaram e limita a quantidade ao estoque.
     *
     * @param array<int,int>    $wanted      [book_id => quantidade]
     * @param array<int,string> $dedications [book_id => texto]
     * @param array{cep:?string,state:?string}|null $location endereço para o frete (null = endereço salvo)
     */
    public static function build(array $wanted, array $dedications = [], ?array $location = null): array
    {
        $items = [];
        $notices = [];
        $map = [];
        $dedicationMap = [];
        $subtotal = 0;

        if ($wanted) {
            $books = Book::findMany(array_keys($wanted));
            foreach ($wanted as $id => $qty) {
                $book = $books[$id] ?? null;
                if ($book === null || (int) ($book['active'] ?? 1) !== 1) {
                    $notices[] = 'Um livro escolhido não está mais disponível e foi removido.';
                    continue;
                }
                $stock = (int) $book['stock'];
                if ($stock <= 0) {
                    $notices[] = 'O livro "' . $book['title'] . '" esgotou e foi removido.';
                    continue;
                }
                if ($qty > $stock) {
                    $qty = $stock;
                    $notices[] = 'A quantidade de "' . $book['title'] . '" foi ajustada para ' . $stock . ' (estoque disponível).';
                }
                $map[$id] = $qty;
                $price = to_cents($book['price']);
                $line = $price * $qty;
                $subtotal += $line;
                $allows = Dedication::allowed($book);
                $dedication = $allows ? ($dedications[$id] ?? '') : '';
                if ($dedication !== '') {
                    $dedicationMap[$id] = $dedication;
                }
                $items[] = [
                    'book'            => $book,
                    'qty'             => $qty,
                    'price_cents'     => $price,
                    'line_cents'      => $line,
                    'allows_dedication' => $allows,
                    'dedication'      => $dedication,
                ];
            }
        }

        $location ??= Shipping::location();
        return self::withShipping([
            'items'          => $items,
            'map'            => $map,
            'dedications'    => $dedicationMap,
            'notices'        => $notices,
            'subtotal_cents' => $subtotal,
        ], $location['cep'] ?? null, $location['state'] ?? null);
    }

    /** Refaz frete e total para um endereço (sempre no servidor). */
    public static function withShipping(array $summary, ?string $cep, ?string $state): array
    {
        $quote = Shipping::quote($summary['subtotal_cents'], $cep, $state);
        $summary['shipping_cents'] = $quote['shipping_cents'];
        $summary['free_from_cents'] = $quote['free_from_cents'];
        $summary['missing_cents'] = $quote['missing_cents'];
        $summary['shipping_label'] = $quote['label'];
        $summary['shipping_days'] = $quote['days'];
        $summary['total_cents'] = $summary['subtotal_cents'] + $quote['shipping_cents'];
        return $summary;
    }
}
