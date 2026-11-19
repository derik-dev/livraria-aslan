<?php
declare(strict_types=1);

final class Book
{
    private const COLUMNS = 'id, title, author, description, price, stock, image, featured, created_at';

    public static function find(int $id): ?array
    {
        return db_one('SELECT * FROM books WHERE id = ?', [$id]);
    }

    /** @return array<int, array> indexado pelo id */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if (!$ids) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $active = self::activeFilter();
        $rows = db_all('SELECT ' . self::COLUMNS . " FROM books WHERE id IN ($marks)$active", $ids);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    public static function featured(int $limit = 6): array
    {
        $active = self::activeFilter();
        $rows = db_all('SELECT ' . self::COLUMNS . " FROM books WHERE featured = 1$active ORDER BY created_at DESC, id DESC LIMIT ?", [$limit]);
        if (!$rows) {
            $rows = db_all('SELECT ' . self::COLUMNS . " FROM books WHERE 1=1$active ORDER BY created_at DESC, id DESC LIMIT ?", [$limit]);
        }
        return $rows;
    }

    /** Quebra a busca em palavras (máx. 5). "farol  vidro" => ["farol", "vidro"]. */
    private static function terms(string $q): array
    {
        $parts = preg_split('/\s+/u', trim($q)) ?: [];
        return array_slice(array_values(array_filter($parts, static fn (string $t): bool => $t !== '')), 0, 5);
    }

    /**
     * Cada palavra precisa aparecer no título, no autor ou na descrição.
     * A collation utf8mb4_unicode_ci já ignora acentos e maiúsculas.
     */
    private static function searchWhere(string $q): array
    {
        $terms = self::terms($q);
        if (!$terms) {
            return ['', []];
        }
        $clauses = [];
        $params = [];
        foreach ($terms as $t) {
            $like = '%' . like_escape($t) . '%';
            $clauses[] = "(title LIKE ? ESCAPE '!' OR author LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like);
        }
        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** Ordena por relevância: título começa com a busca > título contém > autor contém > só descrição. */
    private static function relevanceOrder(string $q): array
    {
        $q = trim((string) preg_replace('/\s+/u', ' ', $q));
        if ($q === '') {
            return [' ORDER BY title ASC, id ASC', []];
        }
        $e = like_escape($q);
        return [
            " ORDER BY CASE
                WHEN title LIKE ? ESCAPE '!' THEN 0
                WHEN title LIKE ? ESCAPE '!' THEN 1
                WHEN author LIKE ? ESCAPE '!' THEN 2
                ELSE 3 END, title ASC, id ASC",
            [$e . '%', '%' . $e . '%', '%' . $e . '%'],
        ];
    }

    public static function search(string $q, int $limit, int $offset, bool $admin = false): array
    {
        [$where, $params] = self::searchWhere($q);
        if (!$admin) $where = ($where ?: ' WHERE 1=1') . self::activeFilter();
        [$order, $orderParams] = self::relevanceOrder($q);
        $params = array_merge($params, $orderParams, [$limit, $offset]);
        return db_all('SELECT ' . ($admin ? '*' : self::COLUMNS) . " FROM books{$where}{$order} LIMIT ? OFFSET ?", $params);
    }

    /**
     * Livros relacionados aos resultados: primeiro os dos mesmos autores,
     * depois destaques e mais recentes. Nunca repete os livros já mostrados.
     */
    public static function related(array $shown, int $limit = 4): array
    {
        $ids = array_map(static fn (array $b): int => (int) $b['id'], $shown) ?: [0];
        $authors = array_values(array_unique(array_map(static fn (array $b): string => (string) $b['author'], $shown))) ?: [''];
        $idMarks = implode(',', array_fill(0, count($ids), '?'));
        $authorMarks = implode(',', array_fill(0, count($authors), '?'));
        return db_all(
            'SELECT ' . self::COLUMNS . " FROM books
             WHERE id NOT IN ($idMarks) AND stock > 0" . self::activeFilter() . "
             ORDER BY CASE WHEN author IN ($authorMarks) THEN 0 ELSE 1 END, featured DESC, created_at DESC, id DESC
             LIMIT ?",
            array_merge($ids, $authors, [$limit])
        );
    }

    public static function count(string $q = '', bool $admin = false): int
    {
        [$where, $params] = self::searchWhere($q);
        if (!$admin) $where = ($where ?: ' WHERE 1=1') . self::activeFilter();
        return (int) db_value("SELECT COUNT(*) FROM books{$where}", $params);
    }

    public static function lowStock(int $threshold): array
    {
        if (AdminSchema::has('books', 'min_stock')) return db_all('SELECT id,title,author,stock,min_stock FROM books WHERE active=1 AND stock<=min_stock ORDER BY stock,title');
        return db_all('SELECT id, title, author, stock FROM books WHERE stock <= ? ORDER BY stock ASC, title ASC', [$threshold]);
    }

    private static function activeFilter(): string
    {
        return AdminSchema::has('books', 'active') ? ' AND active=1' : '';
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
