<?php
declare(strict_types=1);

final class ResourceAdminController
{
    private function context(): array
    {
        require_admin();
        $key = explode('/', trim(request_path(), '/'))[1] ?? '';
        $definition = AdminResources::get($key);
        if (!AdminSchema::has($definition['table'])) {
            view('admin/setup', ['title' => 'Atualizar banco'], 'admin');
            exit;
        }
        return [$key, $definition];
    }

    public function index(): void
    {
        [$key, $definition] = $this->context();
        $q = query('q', 100); $params = []; $where = '';
        if ($q !== '') {
            $clauses = [];
            foreach ($definition['search'] as $column) {
                $clauses[] = "`$column` LIKE ? ESCAPE '!'";
                $params[] = '%' . like_escape($q) . '%';
            }
            $where = ' WHERE (' . implode(' OR ', $clauses) . ')';
        }
        $table = $definition['table'];
        $total = (int) db_value("SELECT COUNT(*) FROM `$table`$where", $params);
        $pages = max(1, (int) ceil($total / 25));
        $page = min(max(1, input_int('pagina', $_GET) ?? 1), $pages);
        $rows = db_all("SELECT * FROM `$table`$where ORDER BY id DESC LIMIT ? OFFSET ?", [...$params, 25, ($page - 1) * 25]);
        view('admin/resource_list', compact('key', 'definition', 'q', 'rows', 'total', 'pages', 'page') + ['title' => $definition['title']], 'admin');
    }

    public function create(): void { $this->form(null); }
    public function edit(int $id): void { $this->form($id); }

    private function form(?int $id, array $values = [], array $errors = []): void
    {
        [$key, $definition] = $this->context();
        $row = $id === null ? null : db_one('SELECT * FROM `' . $definition['table'] . '` WHERE id = ?', [$id]);
        if ($id !== null && !$row) { render_error(404); return; }
        if (!$values) {
            $values = $row ?? [];
            if ($row === null) {
                foreach ($definition['fields'] as $name => $field) $values[$name] = $field[1] === 'checkbox' ? 1 : ($field[1] === 'money' ? '0.00' : '');
            }
        }
        $extra = [];
        if ($key === 'cupons' && $id !== null) {
            $extra = db_all('SELECT o.id, o.coupon_code, o.discount, o.status, o.created_at, u.name AS customer_name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.coupon_id=? ORDER BY o.id DESC LIMIT 100', [$id]);
        }
        view('admin/resource_form', compact('key', 'definition', 'row', 'values', 'errors', 'extra') + ['title' => $definition['title']], 'admin');
    }

    public function store(): void { $this->save(null); }
    public function update(int $id): void { $this->save($id); }

    private function save(?int $id): void
    {
        [$key, $definition] = $this->context();
        [$values, $errors] = AdminResources::validate($definition, $_POST);
        $table = $definition['table'];
        if ($id !== null && !db_value("SELECT id FROM `$table` WHERE id=?", [$id])) { render_error(404); return; }
        $posted = [];
        foreach ($definition['fields'] as $name => $field) $posted[$name] = is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
        if ($errors) { http_response_code(422); $this->form($id, $posted, $errors); return; }
        $image = null;
        if (isset($definition['image'])) {
            $error = null; $image = handle_image_upload('image', $error);
            if ($error !== null) { http_response_code(422); $this->form($id, $posted, ['image' => $error]); return; }
            if ($image !== null) $values['image'] = $image;
        }
        try {
            $id = AdminAudit::transaction(function () use ($id, $table, $values): int {
                $old = $id === null ? null : db_one("SELECT * FROM `$table` WHERE id=? FOR UPDATE", [$id]);
                if ($id !== null && !$old) throw new DomainException('Registro não encontrado.');
                if ($table === 'coupons' && $values['max_uses'] !== null && $values['max_uses'] < (int) ($old['used_count'] ?? 0)) throw new DomainException('O limite não pode ser menor que os usos já registrados.');
                if ($id === null) {
                    $columns = implode('`,`', array_keys($values));
                    $marks = implode(',', array_fill(0, count($values), '?'));
                    db_run("INSERT INTO `$table` (`$columns`) VALUES ($marks)", array_values($values));
                    $id = (int) db()->lastInsertId();
                    $action = 'criar';
                } else {
                    $set = implode(',', array_map(static fn ($column) => "`$column`=?", array_keys($values)));
                    db_run("UPDATE `$table` SET $set WHERE id=?", [...array_values($values), $id]);
                    $action = 'editar';
                }
                AdminAudit::record($action, $table, $id, $values);
                return $id;
            });
        } catch (PDOException $e) {
            if ($image !== null) delete_upload($image);
            if ($e->getCode() !== '23000') throw $e;
            http_response_code(422); $this->form($id, $posted, ['_form' => 'Já existe um registro com esse código ou endereço.']); return;
        } catch (DomainException $e) {
            if ($image !== null) delete_upload($image);
            http_response_code(422); $this->form($id, $posted, ['_form' => $e->getMessage()]); return;
        } catch (Throwable $e) {
            if ($image !== null) delete_upload($image);
            throw $e;
        }
        flash('success', 'Alterações salvas.'); redirect('/admin/' . $key . '/' . $id . '/editar');
    }

    public function destroy(int $id): void
    {
        [$key, $definition] = $this->context();
        if (!$definition['delete']) { render_error(405); return; }
        AdminAudit::transaction(function () use ($definition, $id): void {
            $row = db_one('SELECT * FROM `' . $definition['table'] . '` WHERE id=? FOR UPDATE', [$id]);
            if (!$row) return;
            db_run('DELETE FROM `' . $definition['table'] . '` WHERE id=?', [$id]);
            AdminAudit::record('excluir', $definition['table'], $id);
        });
        flash('success', 'Registro excluído.'); redirect('/admin/' . $key);
    }
}
