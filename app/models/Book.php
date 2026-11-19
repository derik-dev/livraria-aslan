<?php
declare(strict_types=1);

final class Book
{
    private const COLUMNS = 'id, title, author, description, price, stock, image, featured, created_at';

    public static function find(int $id): ?array
    {
        return db_one('SELECT ' . self::COLUMNS . ' FROM books WHERE id = ?', [$id]);
    }

    /** @return array<int, array> indexado pelo id */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if (!$ids) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all('SELECT ' . self::COLUMNS . " FROM books WHERE id IN ($marks)", $ids);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    public static function featured(int $limit = 6): array
    {
        $rows = db_all('SELECT ' . self::COLUMNS . ' FROM books WHERE featured = 1 ORDER BY created_at DESC, id DESC LIMIT ?', [$limit]);
        if (!$rows) {
            $rows = db_all('SELECT ' . self::COLUMNS . ' FROM books ORDER BY created_at DESC, id DESC LIMIT ?', [$limit]);
        }
        return $rows;
    }

    private static function searchWhere(string $q): array
    {
        if ($q === '') {
            return ['', []];
        }
        $like = '%' . like_escape($q) . '%';
        return [" WHERE (title LIKE ? ESCAPE '!' OR author LIKE ? ESCAPE '!')", [$like, $like]];
    }

    public static function search(string $q, int $limit, int $offset): array
    {
        [$where, $params] = self::searchWhere($q);
        $params[] = $limit;
        $params[] = $offset;
        return db_all('SELECT ' . self::COLUMNS . " FROM books{$where} ORDER BY title ASC, id ASC LIMIT ? OFFSET ?", $params);
    }

    public static function count(string $q = ''): int
    {
        [$where, $params] = self::searchWhere($q);
        return (int) db_value("SELECT COUNT(*) FROM books{$where}", $params);
    }

    public static function lowStock(int $threshold): array
    {
        return db_all('SELECT id, title, author, stock FROM books WHERE stock <= ? ORDER BY stock ASC, title ASC', [$threshold]);
    }

    public static function create(array $d): int
    {
        db_run(
            'INSERT INTO books (title, author, description, price, stock, image, featured) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['title'], $d['author'], $d['description'], cents_to_decimal($d['price_cents']), $d['stock'], $d['image'], $d['featured']]
        );
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        db_run(
            'UPDATE books SET title = ?, author = ?, description = ?, price = ?, stock = ?, image = ?, featured = ? WHERE id = ?',
            [$d['title'], $d['author'], $d['description'], cents_to_decimal($d['price_cents']), $d['stock'], $d['image'], $d['featured'], $id]
        );
    }

    public static function delete(int $id): void
    {
        db_run('DELETE FROM books WHERE id = ?', [$id]);
    }
}
