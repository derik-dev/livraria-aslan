<?php
declare(strict_types=1);

final class AdminBook
{
    public static function save(?int $id, array $data, ?int $expectedStock): int
    {
        return AdminAudit::transaction(function () use ($id, $data, $expectedStock): int {
            $old = $id === null ? null : db_one('SELECT * FROM books WHERE id=? FOR UPDATE', [$id]);
            if ($id !== null && !$old) throw new DomainException('Livro não encontrado.');
            if ($old && $expectedStock !== (int) $old['stock']) throw new DomainException('O estoque mudou enquanto você editava. Recarregue a página antes de salvar.');
            $values = [
                'title' => $data['title'], 'author' => $data['author'], 'description' => $data['description'],
                'price' => cents_to_decimal($data['price_cents']), 'stock' => $data['stock'],
                'image' => $data['image'], 'featured' => $data['featured'],
            ];
            if (AdminSchema::has('books', 'allows_dedication')) {
                $values['allows_dedication'] = (int) ($data['allows_dedication'] ?? 0);
            }
            if (AdminSchema::core()) {
                $values += array_intersect_key($data, array_flip(['category_id','isbn','publisher','pages','cost_price','min_stock','active']));
            }
            if ($id === null) {
                $columns = implode('`,`', array_keys($values)); $marks = implode(',', array_fill(0, count($values), '?'));
                db_run("INSERT INTO books (`$columns`) VALUES ($marks)", array_values($values));
                $id = (int) db()->lastInsertId();
            } else {
                $set = implode(',', array_map(static fn ($key) => "`$key`=?", array_keys($values)));
                db_run("UPDATE books SET $set WHERE id=?", [...array_values($values), $id]);
            }
            $delta = $data['stock'] - (int) ($old['stock'] ?? 0);
            if ($delta !== 0) AdminStock::record($id, $old ? 'ajuste' : 'entrada', $delta, $data['stock'], $data['stock_note'] ?: ($old ? 'Edição do livro' : 'Estoque inicial'));
            AdminAudit::record($old ? 'editar' : 'criar', 'books', $id, $values);
            return $id;
        });
    }

    public static function delete(int $id): ?array
    {
        return AdminAudit::transaction(function () use ($id): ?array {
            $book = db_one('SELECT * FROM books WHERE id=? FOR UPDATE', [$id]);
            if (!$book) return null;
            if (db_value('SELECT COUNT(*) FROM order_items WHERE book_id=?', [$id])) {
                throw new DomainException('Este livro tem pedidos. Desative o livro para preservar o histórico de vendas e estoque.');
            }
            AdminAudit::record('excluir', 'books', $id, ['title' => $book['title']]);
            db_run('DELETE FROM books WHERE id=?', [$id]);
            return $book;
        });
    }
}
