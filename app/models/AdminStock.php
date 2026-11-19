<?php
declare(strict_types=1);

final class AdminStock
{
    public static function move(int $bookId, string $type, int $quantity, string $note): void
    {
        if (!in_array($type, ['entrada', 'saida', 'ajuste'], true) || $quantity < 0 || $quantity > 100000 || ($type !== 'ajuste' && $quantity === 0) || $note === '' || text_length($note) > 255) {
            throw new DomainException('Informe o livro, uma quantidade válida e o motivo da movimentação.');
        }
        AdminAudit::transaction(function () use ($bookId, $type, $quantity, $note): void {
            $book = db_one('SELECT id,stock FROM books WHERE id=? FOR UPDATE', [$bookId]);
            if (!$book) throw new DomainException('Livro não encontrado.');
            $old = (int) $book['stock'];
            $after = match ($type) { 'entrada' => $old + $quantity, 'saida' => $old - $quantity, 'ajuste' => $quantity };
            if ($after < 0 || $after > 100000) throw new DomainException('O estoque final deve ficar entre 0 e 100000.');
            db_run('UPDATE books SET stock=? WHERE id=?', [$after, $bookId]);
            self::record($bookId, $type, $after - $old, $after, $note);
            AdminAudit::record('movimentar', 'stock_movements', $bookId, ['type' => $type, 'quantity' => $after - $old, 'stock_after' => $after, 'note' => $note]);
        });
    }

    public static function record(int $bookId, string $type, int $delta, int $after, string $note): void
    {
        if (!AdminSchema::has('stock_movements')) return;
        db_run('INSERT INTO stock_movements(book_id,user_id,type,quantity,stock_after,note) VALUES (?,?,?,?,?,?)', [
            $bookId, $_SESSION['user_id'] ?? null, $type, $delta, $after, $note,
        ]);
    }
}
