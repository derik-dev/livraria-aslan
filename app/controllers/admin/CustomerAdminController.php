<?php
declare(strict_types=1);

final class CustomerAdminController
{
    public function index(): void
    {
        require_admin();
        $q = query('q', 100);
        $from="FROM users u WHERE u.role='customer'"; $params=[];
        if ($q!=='') { $from.=" AND (u.name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')"; $like='%'.like_escape($q).'%'; $params=[$like,$like]; }
        $extra=AdminSchema::has('users','active')?',u.active,u.last_login_at':'';
        $data=AdminPage::fetch("SELECT u.id,u.name,u.email,u.phone,u.created_at$extra,(SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS orders_count,COALESCE((SELECT SUM(o.total) FROM orders o WHERE o.user_id=u.id AND o.status IN ('pago','enviado','entregue')),0) AS orders_total",$from,$params,'u.created_at DESC,u.id DESC');
        view('admin/customers', $data + [
            'title'     => 'Clientes',
            'q'         => $q,
            'customers' => $data['rows'],
        ], 'admin');
    }

    public function show(int $id): void
    {
        require_admin();
        $customer = User::find($id);
        if (!$customer || $customer['role'] !== 'customer') { render_error(404); return; }
        $data = AdminPage::fetch('SELECT id,status,total,created_at', 'FROM orders WHERE user_id=?', [$id], 'created_at DESC,id DESC');
        $spent = db_value("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status IN ('pago','enviado','entregue')", [$id]);
        view('admin/customer_detail', $data + ['title' => $customer['name'], 'customer' => $customer, 'address' => Address::forUser($id), 'spent' => $spent], 'admin');
    }

    public function toggle(int $id): void
    {
        require_admin();
        if (!AdminSchema::has('users','active')) { render_error(409); return; }
        $active = input('active',1);
        if (!in_array($active,['0','1'],true)) { render_error(422); return; }
        AdminAudit::transaction(function () use ($id,$active): void {
            $user = db_one("SELECT id FROM users WHERE id=? AND role='customer' FOR UPDATE", [$id]);
            if (!$user) { render_error(404); return; }
            db_run("UPDATE users SET active=? WHERE id=? AND role='customer'", [(int) $active,$id]);
            AdminAudit::record((int) $active ? 'desbloquear' : 'bloquear','users',$id);
        });
        flash('success','Situação do cliente atualizada.'); redirect('/admin/clientes/' . $id);
    }
}
