<?php
declare(strict_types=1);

final class PanelAdminController
{
    public function stock(): void
    {
        require_admin();
        if (!AdminSchema::has('stock_movements')) { view('admin/setup', ['title' => 'Estoque'], 'admin'); return; }
        $bookId = input_int('livro', $_GET);
        $q = query('q', 100);
        $from = 'FROM stock_movements m JOIN books b ON b.id=m.book_id LEFT JOIN users u ON u.id=m.user_id WHERE 1=1'; $params = [];
        if ($bookId !== null) { $from .= ' AND m.book_id=?'; $params[] = $bookId; }
        if ($q !== '') { $from .= " AND (b.title LIKE ? ESCAPE '!' OR b.author LIKE ? ESCAPE '!')"; $params = [...$params, '%' . like_escape($q) . '%', '%' . like_escape($q) . '%']; }
        $data = AdminPage::fetch('SELECT m.*,b.title,u.name AS actor', $from, $params, 'm.id DESC');
        view('admin/stock', $data + ['title' => 'Estoque', 'q' => $q, 'bookId' => $bookId,
            'books' => db_all('SELECT id,title,stock FROM books ORDER BY title'), 'lowStock' => Book::lowStock(3)], 'admin');
    }

    public function moveStock(): void
    {
        require_admin();
        if (!AdminSchema::has('stock_movements')) { render_error(409); return; }
        try { AdminStock::move(input_int('book_id') ?? 0, input('type', 15), input_int('quantity') ?? -1, input('note', 255)); flash('success', 'Movimentação registrada.'); }
        catch (DomainException $e) { flash('error', $e->getMessage()); }
        redirect('/admin/estoque');
    }

    public static function settingsFields(): array
    {
        return [
            'store_name' => ['Nome da loja','text',true,100], 'store_email' => ['E-mail da loja','email',true,190],
            'store_phone' => ['Telefone','text',false,20], 'store_whatsapp' => ['WhatsApp com DDD','text',false,20],
            'pix_key' => ['Chave Pix','text',false,255], 'pix_holder' => ['Titular do Pix','text',false,150],
            'pix_expires_minutes' => ['Prazo do Pix em minutos','integer',true,10080],
            'shipping_fixed' => ['Frete fixo em euros (usado quando não há faixa em Frete)','money',true], 'free_shipping_min' => ['Frete grátis a partir de (€, 0 desativa; vale quando a faixa não tem valor próprio)','money',true],
            'free_shipping_bar_enabled' => ['Mostrar a barra "Faltam X € para frete grátis" no carrinho','checkbox'],
            'dedication_enabled' => ['Permitir dedicatória nos livros marcados','checkbox'],
            'dedication_max_chars' => ['Limite de caracteres da dedicatória','integer',true, Dedication::columnLimit()],
            'invoice_nif_required' => ['NIF obrigatório no checkout','checkbox'],
            'return_window_days' => ['Prazo para devolução ou troca, em dias após a entrega','integer',true,365],
            'cart_abandoned_hours' => ['Carrinho abandonado após quantas horas','integer',true,8760],
        ];
    }

    public function settings(): void
    {
        require_admin();
        if (!AdminSchema::has('settings')) { view('admin/setup', ['title' => 'Configurações'], 'admin'); return; }
        $fields = self::settingsFields(); $values = StoreSettings::all(); $errors = [];
        $values += ['pix_expires_minutes' => '60', 'return_window_days' => '7', 'cart_abandoned_hours' => '24', 'reviews_need_approval' => '1',
            'free_shipping_bar_enabled' => '0', 'dedication_enabled' => '0', 'dedication_max_chars' => '200', 'invoice_nif_required' => '0'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$clean, $errors] = AdminResources::validate(['fields' => $fields], $_POST);
            if (!valid_email((string) $clean['store_email'])) $errors['store_email'] = 'Informe um e-mail válido.';
            foreach (['pix_expires_minutes','cart_abandoned_hours','dedication_max_chars'] as $key) if ((int) $clean[$key] < 1) $errors[$key] = 'Informe pelo menos 1.';
            if (!$errors) {
                AdminAudit::transaction(function () use ($clean): void {
                    foreach ($clean as $key => $value) db_run('INSERT INTO settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$key, $value]);
                    AdminAudit::record('editar', 'settings', null, ['fields' => array_keys($clean)]);
                });
                flash('success', 'Configurações salvas.'); redirect('/admin/configuracoes');
            }
            http_response_code(422); $values = [];
            foreach ($fields as $key => $field) $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        }
        view('admin/settings', compact('fields','values','errors') + ['title' => 'Configurações'], 'admin');
    }

    public function security(): void
    {
        require_admin();
        if (!AdminSchema::has('audit_logs') || !AdminSchema::has('login_attempts')) { view('admin/setup', ['title' => 'Segurança'], 'admin'); return; }
        $q = query('q', 100); $kind = query('tipo', 10) === 'login' ? 'login' : 'acoes';
        if ($kind === 'login') {
            $params = $q === '' ? [] : ['%' . like_escape($q) . '%'];
            $data = AdminPage::fetch('SELECT *', 'FROM login_attempts' . ($q === '' ? '' : " WHERE email LIKE ? ESCAPE '!'"), $params);
        } else {
            $params = $q === '' ? [] : ['%' . like_escape($q) . '%', '%' . like_escape($q) . '%'];
            $data = AdminPage::fetch('SELECT l.*,u.name AS actor', 'FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id' . ($q === '' ? '' : " WHERE l.entity LIKE ? ESCAPE '!' OR u.name LIKE ? ESCAPE '!'"), $params, 'l.id DESC');
        }
        view('admin/security', $data + compact('q','kind') + ['title' => 'Segurança'], 'admin');
    }
}
