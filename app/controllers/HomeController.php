<?php
declare(strict_types=1);

final class HomeController
{
    public function index(): void
    {
        view('shop/home', [
            'title' => 'Início',
            'books' => Book::featured(6),
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

        view('shop/catalog', [
            'title' => $q === '' ? 'Catálogo' : 'Busca: ' . $q,
            'q'     => $q,
            'books' => Book::search($q, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ]);
    }

    public function show(int $id): void
    {
        $book = Book::find($id);
        if ($book === null) {
            render_error(404);
            return;
        }
        $inCart = cart()[$id] ?? 0;
        view('shop/book', [
            'title'  => $book['title'],
            'book'   => $book,
            'inCart' => $inCart,
            'maxQty' => max(0, min((int) $book['stock'] - $inCart, (int) config('shop.max_qty_per_item', 99) - $inCart)),
        ]);
    }
}
