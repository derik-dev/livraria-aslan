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

        // Dedicatória (opcional): só se o livro permitir e a loja estiver com ela ligada.
        $dedication = null;
        if (Dedication::allowed($book)) {
            [$dedication, $dedError] = Dedication::read($_POST['dedication'] ?? '');
            if ($dedError !== null) {
                $_SESSION['_old_dedication'] = ['id' => (int) $book['id'], 'text' => $dedication];
                flash('error', $dedError);
                redirect($backTo);
            }
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
            $_SESSION['buy_now_dedication'] = $dedication ? [(int) $book['id'] => $dedication] : [];
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

        // Dedicatória em branco não apaga a que já estava no carrinho.
        cart_set((int) $book['id'], $wanted, $dedication === '' ? null : $dedication);
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

        // Confere todas as dedicatórias antes de gravar qualquer coisa.
        $postedDed = is_array($_POST['dedication'] ?? null) ? $_POST['dedication'] : [];
        $dedications = [];
        foreach ($cart as $id => $oldQty) {
            if (!array_key_exists($id, $postedDed) || !Dedication::allowed($books[$id] ?? null)) {
                continue;
            }
            [$text, $error] = Dedication::read($postedDed[$id]);
            if ($error !== null) {
                flash('error', '"' . $books[$id]['title'] . '": ' . $error . ' Nada foi alterado no carrinho.');
                redirect('/carrinho');
            }
            $dedications[$id] = $text;
        }

        foreach ($cart as $id => $oldQty) {
            if (!array_key_exists($id, $posted)) {
                continue;
            }
            $qty = filter_var(is_string($posted[$id]) ? trim($posted[$id]) : '', FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 0) {
                $adjusted = true;
                if (isset($dedications[$id])) {
                    cart_set($id, $oldQty, $dedications[$id]);
                }
                continue;
            }
            $stock = isset($books[$id]) ? (int) $books[$id]['stock'] : 0;
            $limit = min($stock, $maxPerItem);
            if ($qty > $limit) {
                $qty = $limit;
                $adjusted = true;
            }
            cart_set($id, $qty, $dedications[$id] ?? null);
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
