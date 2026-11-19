<?php
declare(strict_types=1);

final class HomeController
{
    public function index(): void
    {
        $featured = Book::featured(6);
        view('shop/home', [
            'title'  => 'Início',
            'books'  => $featured,
            'latest' => Book::latest(6, array_column($featured, 'id')),
        ]);
    }

    public function catalog(): void
    {
        $q = query('q', 100);
        $perPage = max(1, (int) config('shop.per_page', 12));
        $total = Book::count($q);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = input_int('pagina', $_GET) ?? 1;
        $page = min(max(1, $page), $pages);

        $books = Book::search($q, $perPage, ($page - 1) * $perPage);

        view('shop/catalog', [
            'title'   => $q === '' ? 'Catálogo' : 'Busca: ' . $q,
            'q'       => $q,
            'books'   => $books,
            'related' => $q === '' ? [] : Book::related($books, 4),
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
        ]);
    }

    public function show(int $id): void
    {
        $book = Book::find($id);
        if ($book === null || (int) ($book['active'] ?? 1) !== 1) {
            render_error(404);
            return;
        }
        $inCart = cart()[$id] ?? 0;
        $old = $_SESSION['_old_dedication'] ?? null;
        unset($_SESSION['_old_dedication']);
        view('shop/book', [
            'title'  => $book['title'],
            'book'   => $book,
            'inCart' => $inCart,
            'allowsDedication' => Dedication::allowed($book),
            'dedicationMax'    => Dedication::max(),
            'dedication'       => is_array($old) && ($old['id'] ?? null) === $id && is_string($old['text'] ?? null)
                ? $old['text'] : (cart_dedications()[$id] ?? ''),
            'maxQty' => max(1, min((int) $book['stock'], (int) config('shop.max_qty_per_item', 99))),
        ]);
    }
}
