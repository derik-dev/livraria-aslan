<?php
declare(strict_types=1);

/**
 * Onde o carrinho fica guardado: tabelas carts/cart_items.
 * Visitante: carrinho ligado à sessão ($_SESSION['cart_id']).
 * Cliente logado: carrinho ligado ao user_id (volta no próximo login).
 * Se as tabelas não existirem, usa a sessão como antes.
 */
final class CartStore
{
    private static ?int $id = null;
    /** @var array<int,array{qty:int,dedication:string}>|null */
    private static ?array $rows = null;

    public static function usesDatabase(): bool
    {
        return AdminSchema::has('carts') && AdminSchema::has('cart_items', 'dedication');
    }

    /** @return array<int,array{qty:int,dedication:string}> [book_id => linha] */
    public static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }
        $rows = [];
        if (!self::usesDatabase()) {
            $ded = is_array($_SESSION['cart_dedication'] ?? null) ? $_SESSION['cart_dedication'] : [];
            foreach (clean_items($_SESSION['cart'] ?? []) as $id => $qty) {
                $rows[$id] = ['qty' => $qty, 'dedication' => is_string($ded[$id] ?? null) ? $ded[$id] : ''];
            }
            return self::$rows = $rows;
        }
        $cartId = self::id(false);
        if ($cartId !== null) {
            foreach (db_all('SELECT book_id, quantity, dedication FROM cart_items WHERE cart_id = ? ORDER BY id', [$cartId]) as $r) {
                if ((int) $r['quantity'] > 0) {
                    $rows[(int) $r['book_id']] = ['qty' => (int) $r['quantity'], 'dedication' => (string) ($r['dedication'] ?? '')];
                }
            }
        }
        return self::$rows = $rows;
    }

    /**
     * Grava a quantidade (0 remove). $dedication null = mantém a que já existe; '' = apaga.
     */
    public static function set(int $bookId, int $qty, ?string $dedication = null): void
    {
        self::$rows = null;
        if (!self::usesDatabase()) {
            $cart = clean_items($_SESSION['cart'] ?? []);
            $ded = is_array($_SESSION['cart_dedication'] ?? null) ? $_SESSION['cart_dedication'] : [];
            if ($qty <= 0) {
                unset($cart[$bookId], $ded[$bookId]);
            } else {
                $cart[$bookId] = $qty;
                if ($dedication !== null) {
                    $ded[$bookId] = $dedication;
                }
            }
            $_SESSION['cart'] = $cart;
            $_SESSION['cart_dedication'] = $ded;
            return;
        }
        if ($qty <= 0) {
            $cartId = self::id(false);
            if ($cartId !== null) {
                db_run('DELETE FROM cart_items WHERE cart_id = ? AND book_id = ?', [$cartId, $bookId]);
            }
            return;
        }
        $cartId = self::id(true);
        $value = $dedication === null || $dedication === '' ? null : $dedication;
        db_run(
            'INSERT INTO cart_items (cart_id, book_id, quantity, dedication) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)' . ($dedication !== null ? ', dedication = VALUES(dedication)' : ''),
            [$cartId, $bookId, $qty, $value]
        );
        db_run('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cartId]);
    }

    /** Pedido feito: o carrinho vira "convertido" e um novo começa vazio. */
    public static function clear(): void
    {
        self::$rows = null;
        if (!self::usesDatabase()) {
            $_SESSION['cart'] = [];
            $_SESSION['cart_dedication'] = [];
            return;
        }
        $cartId = self::id(false);
        if ($cartId !== null) {
            db_run("UPDATE carts SET status = 'convertido' WHERE id = ?", [$cartId]);
        }
        unset($_SESSION['cart_id']);
        self::$id = null;
    }

    /**
     * Depois do login: junta o carrinho de visitante ao carrinho guardado da conta.
     */
    public static function mergeOnLogin(int $userId): void
    {
        self::$rows = null;
        self::$id = null;
        if (!self::usesDatabase()) {
            return;
        }
        $guestId = $_SESSION['cart_id'] ?? null;
        $guest = is_int($guestId)
            ? db_one("SELECT id, user_id FROM carts WHERE id = ? AND status = 'ativo'", [$guestId])
            : null;
        if ($guest !== null && $guest['user_id'] !== null && (int) $guest['user_id'] !== $userId) {
            $guest = null; // carrinho de outra conta: ignora
        }
        $own = db_one("SELECT id FROM carts WHERE user_id = ? AND status = 'ativo' AND id <> ? ORDER BY updated_at DESC, id DESC LIMIT 1",
            [$userId, $guest !== null ? (int) $guest['id'] : 0]);

        if ($guest === null) {
            unset($_SESSION['cart_id']);
            if ($own !== null) {
                $_SESSION['cart_id'] = (int) $own['id'];
            }
            return;
        }
        if ($own === null) {
            db_run('UPDATE carts SET user_id = ? WHERE id = ?', [$userId, (int) $guest['id']]);
            $_SESSION['cart_id'] = (int) $guest['id'];
            return;
        }
        $max = (int) config('shop.max_qty_per_item', 99);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach (db_all('SELECT book_id, quantity, dedication FROM cart_items WHERE cart_id = ?', [(int) $guest['id']]) as $r) {
                db_run(
                    'INSERT INTO cart_items (cart_id, book_id, quantity, dedication) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?), dedication = COALESCE(VALUES(dedication), dedication)',
                    [(int) $own['id'], (int) $r['book_id'], min((int) $r['quantity'], $max), $r['dedication'], $max]
                );
            }
            db_run('DELETE FROM carts WHERE id = ?', [(int) $guest['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $_SESSION['cart_id'] = (int) $own['id'];
    }

    /** Id do carrinho ativo desta sessão/conta; cria um se $create. */
    private static function id(bool $create): ?int
    {
        if (self::$id !== null) {
            return self::$id;
        }
        $userId = is_int($_SESSION['user_id'] ?? null) ? $_SESSION['user_id'] : null;
        $sessionCart = $_SESSION['cart_id'] ?? null;
        if (is_int($sessionCart)) {
            $row = db_one("SELECT id, user_id FROM carts WHERE id = ? AND status = 'ativo'", [$sessionCart]);
            $owner = $row === null || $row['user_id'] === null ? null : (int) $row['user_id'];
            if ($row !== null && ($owner === null || $owner === $userId)) {
                if ($userId !== null && $owner === null) {
                    db_run('UPDATE carts SET user_id = ? WHERE id = ?', [$userId, (int) $row['id']]);
                }
                return self::$id = (int) $row['id'];
            }
            unset($_SESSION['cart_id']);
        }
        if ($userId !== null) {
            $row = db_one("SELECT id FROM carts WHERE user_id = ? AND status = 'ativo' ORDER BY updated_at DESC, id DESC LIMIT 1", [$userId]);
            if ($row !== null) {
                $_SESSION['cart_id'] = (int) $row['id'];
                return self::$id = (int) $row['id'];
            }
        }
        if (!$create) {
            return null;
        }
        // Guarda só um hash da sessão: o id real da sessão nunca vai para o banco.
        db_run('INSERT INTO carts (user_id, session_id) VALUES (?, ?)', [$userId, hash('sha256', session_id())]);
        $id = (int) db()->lastInsertId();
        $_SESSION['cart_id'] = $id;
        return self::$id = $id;
    }
}
