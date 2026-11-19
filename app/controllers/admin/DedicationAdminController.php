<?php
declare(strict_types=1);

/** "Dedicatórias a preparar": lê a view v_dedications_to_prepare e monta a folha de impressão. */
final class DedicationAdminController
{
    /** Nomes aceitos para cada informação vinda da view do banco. */
    private const FIELDS = [
        'order'     => ['order_id', 'pedido_id', 'order_number', 'numero_pedido', 'pedido'],
        'title'     => ['title', 'book_title', 'titulo', 'livro'],
        'recipient' => ['recipient_name', 'recipient', 'destinatario', 'nome_destinatario', 'ship_name', 'customer_name', 'cliente'],
        'text'      => ['dedication', 'dedicatoria', 'texto'],
        'qty'       => ['quantity', 'qty', 'quantidade'],
    ];

    public function index(): void
    {
        require_admin();
        if (!AdminSchema::has('v_dedications_to_prepare')) {
            view('admin/setup', ['title' => 'Dedicatórias a preparar'], 'admin');
            return;
        }
        $rows = db_all('SELECT * FROM v_dedications_to_prepare LIMIT 1000');
        $blocks = [];
        $missingRecipient = [];
        foreach ($rows as $row) {
            $block = ['extra' => []];
            $used = [];
            foreach (self::FIELDS as $field => $names) {
                $block[$field] = null;
                foreach ($names as $name) {
                    if (array_key_exists($name, $row)) {
                        $block[$field] = $row[$name];
                        $used[] = $name;
                        break;
                    }
                }
            }
            foreach ($row as $key => $value) {
                if (!in_array($key, $used, true) && $value !== null && $value !== '') {
                    $block['extra'][(string) $key] = (string) $value;
                }
            }
            if ($block['recipient'] === null && $block['order'] !== null) {
                $missingRecipient[(int) $block['order']] = true;
            }
            $blocks[] = $block;
        }
        // Se a view não trouxer o destinatário, usa o nome de entrega do pedido.
        if ($missingRecipient) {
            $ids = array_keys($missingRecipient);
            $names = [];
            foreach (db_all('SELECT id, ship_name FROM orders WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $o) {
                $names[(int) $o['id']] = $o['ship_name'];
            }
            foreach ($blocks as &$b) {
                if ($b['recipient'] === null && $b['order'] !== null) {
                    $b['recipient'] = $names[(int) $b['order']] ?? null;
                }
            }
            unset($b);
        }
        view('admin/dedications', ['title' => 'Dedicatórias a preparar', 'blocks' => $blocks], 'admin');
    }
}
