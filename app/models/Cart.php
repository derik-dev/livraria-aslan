<?php
declare(strict_types=1);

final class Cart
{
    /**
     * Monta o resumo do carrinho com preços e estoque atuais do banco.
     * Ajusta sozinho itens removidos ou com estoque menor que o pedido.
     */
    public static function summary(): array
    {
        $cart = cart();
        $items = [];
        $notices = [];
        $subtotal = 0;

        if ($cart) {
            $books = Book::findMany(array_keys($cart));
            foreach ($cart as $id => $qty) {
                $book = $books[$id] ?? null;
                if ($book === null) {
                    cart_set($id, 0);
                    $notices[] = 'Um livro do seu carrinho não está mais disponível e foi removido.';
                    continue;
                }
                $stock = (int) $book['stock'];
                if ($stock <= 0) {
                    cart_set($id, 0);
                    $notices[] = 'O livro "' . $book['title'] . '" esgotou e foi removido do carrinho.';
                    continue;
                }
                if ($qty > $stock) {
                    $qty = $stock;
                    cart_set($id, $qty);
                    $notices[] = 'A quantidade de "' . $book['title'] . '" foi ajustada para ' . $stock . ' (estoque disponível).';
                }
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
            'notices'        => $notices,
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents'    => $subtotal + $shipping,
        ];
    }
}
