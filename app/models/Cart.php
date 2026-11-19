<?php
declare(strict_types=1);

final class Cart
{
    /** Resumo do carrinho da sessão (ajusta o carrinho se algo mudou no estoque). */
    public static function summary(): array
    {
        $summary = self::build(cart());
        $_SESSION['cart'] = $summary['map'];
        return $summary;
    }

    /** Resumo da compra direta ("Comprar agora"), sem tocar no carrinho. */
    public static function buyNowSummary(): array
    {
        $summary = self::build(buy_now_items());
        $_SESSION['buy_now'] = $summary['map'];
        return $summary;
    }

    /**
     * Monta o resumo com preços e estoque atuais do banco.
     * Remove itens que sumiram ou esgotaram e limita a quantidade ao estoque.
     *
     * @param array<int,int> $wanted [book_id => quantidade]
     */
    public static function build(array $wanted): array
    {
        $items = [];
        $notices = [];
        $map = [];
        $subtotal = 0;

        if ($wanted) {
            $books = Book::findMany(array_keys($wanted));
            foreach ($wanted as $id => $qty) {
                $book = $books[$id] ?? null;
                if ($book === null) {
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
                $items[] = [
                    'book'        => $book,
                    'qty'         => $qty,
                    'price_cents' => $price,
                    'line_cents'  => $line,
                ];
            }
        }

        $shipping = shipping_cents($subtotal);
        return [
            'items'          => $items,
            'map'            => $map,
            'notices'        => $notices,
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents'    => $subtotal + $shipping,
        ];
    }
}
