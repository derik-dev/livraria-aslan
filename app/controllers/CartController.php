<?php
declare(strict_types=1);

final class CartController
{
    public function index(): void
    {
        $summary = Cart::summary();
        foreach ($summary['notices'] as $n) {
            flash('info', $n);
        }
        view('shop/cart', ['title' => 'Carrinho', 'summary' => $summary]);
    }

    public function add(): void
    {
        $bookId = input_int('book_id');
        $qty = input_int('qty') ?? 1;
        $book = $bookId !== null && $bookId > 0 ? Book::find($bookId) : null;
        if ($book === null || (int) ($book['active'] ?? 1) !== 1) {
            flash('error', 'Livro não encontrado.');
            redirect('/catalogo');
        }

        $backTo = '/livro/' . (int) $book['id'];
        if ($qty < 1) {
            flash('error', 'Escolha uma quantidade de pelo menos 1.');
            redirect($backTo);
        }

        $stock = (int) $book['stock'];
        $maxPerItem = (int) config('shop.max_qty_per_item', 99);

        if ($stock <= 0) {
            flash('error', 'Este livro está esgotado.');
            redirect($backTo);
        }

        // "Comprar agora": vai direto ao checkout só com este livro, sem mexer no carrinho.
        if (input('buy_now', 1) === '1') {
            if ($qty > $stock) {
                flash('error', "Só temos {$stock} unidade(s) deste livro.");
                redirect($backTo);
            }
            if ($qty > $maxPerItem) {
                flash('error', "O limite é de {$maxPerItem} unidades por livro.");
                redirect($backTo);
            }
            $_SESSION['buy_now'] = [(int) $book['id'] => $qty];
            redirect('/checkout?agora=1');
        }

        $current = cart()[(int) $book['id']] ?? 0;
        $wanted = $current + $qty;
        if ($wanted > $stock) {
            flash('error', "Só temos {$stock} unidade(s) deste livro" . ($current > 0 ? " e você já tem {$current} no carrinho." : '.'));
            redirect($backTo);
        }
        if ($wanted > $maxPerItem) {
            flash('error', "O limite é de {$maxPerItem} unidades por livro.");
            redirect($backTo);
        }

        cart_set((int) $book['id'], $wanted);
        flash('success', '"' . $book['title'] . '" foi adicionado ao carrinho.');
        redirect('/carrinho');
    }

    public function update(): void
    {
        $posted = $_POST['qty'] ?? [];
        if (!is_array($posted)) {
            redirect('/carrinho');
        }
        $cart = cart();
        $books = Book::findMany(array_keys($cart));
        $maxPerItem = (int) config('shop.max_qty_per_item', 99);
        $adjusted = false;

        foreach ($cart as $id => $oldQty) {
            if (!array_key_exists($id, $posted)) {
                continue;
            }
            $qty = filter_var(is_string($posted[$id]) ? trim($posted[$id]) : '', FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 0) {
                $adjusted = true;
                continue;
            }
            $stock = isset($books[$id]) ? (int) $books[$id]['stock'] : 0;
            $limit = min($stock, $maxPerItem);
            if ($qty > $limit) {
                $qty = $limit;
                $adjusted = true;
            }
            cart_set($id, $qty);
        }

        flash($adjusted ? 'info' : 'success', $adjusted
            ? 'Carrinho atualizado. Algumas quantidades foram ajustadas ao estoque disponível.'
            : 'Carrinho atualizado.');
        redirect('/carrinho');
    }

    public function remove(): void
    {
        $bookId = input_int('book_id');
        if ($bookId !== null && $bookId > 0) {
            cart_set($bookId, 0);
            flash('success', 'Livro removido do carrinho.');
        }
        redirect('/carrinho');
    }
}
