<?php
declare(strict_types=1);

/** Sugestões da busca enquanto a pessoa digita (JSON consumido pelo app.js). */
final class SearchController
{
    public function suggest(): void
    {
        $q = query('q', 100);
        $items = [];
        if (text_length($q) >= 2) {
            foreach (Book::search($q, 6, 0) as $b) {
                $items[] = [
                    'title'  => (string) $b['title'],
                    'author' => (string) $b['author'],
                    'price'  => money($b['price']),
                    'image'  => book_image($b['image']),
                    'url'    => url('/livro/' . (int) $b['id']),
                    'soldOut'=> (int) $b['stock'] <= 0,
                ];
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode([
            'q'       => $q,
            'items'   => $items,
            'allUrl'  => url('/catalogo', ['q' => $q]),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }
}
