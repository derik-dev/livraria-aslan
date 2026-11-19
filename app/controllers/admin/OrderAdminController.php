<?php
declare(strict_types=1);

final class OrderAdminController
{
    public function index(): void
    {
        require_admin();
        $status = query('status', 20);
        if (!in_array($status, ORDER_STATUSES, true)) {
            $status = '';
        }
        $q=query('q',100); $cancel=query('cancelamento',1)==='1' && AdminSchema::has('orders','cancel_requested_at');
        $from='FROM orders o JOIN users u ON u.id=o.user_id WHERE 1=1'; $params=[];
        if ($status!=='') { $from.=' AND o.status=?'; $params[]=$status; }
        if ($cancel) $from.=" AND o.cancel_requested_at IS NOT NULL AND o.status NOT IN ('cancelado','entregue')";
        if ($q!=='') { $from.=" AND (u.name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR o.id=?)"; $like='%'.like_escape($q).'%'; $params=[...$params,$like,$like,ctype_digit($q)?(int)$q:0]; }
        $data=AdminPage::fetch('SELECT o.*,u.name AS customer_name,u.email AS customer_email',$from,$params,'o.created_at DESC,o.id DESC');
        view('admin/orders', $data + [
            'q'=>$q, 'cancel'=>$cancel,
            'title'  => 'Pedidos',
            'status' => $status,
            'orders' => $data['rows'],
            'counts' => Order::countByStatus(),
        ], 'admin');
    }

    public function show(int $id): void
    {
        require_admin();
        $order = Order::find($id);
        if ($order === null) {
            render_error(404);
            return;
        }
        $gateway = Payment::get((string) $order['payment_method']);
        view('admin/order_detail', [
            'title'        => 'Pedido nº ' . $order['id'],
            'order'        => $order,
            'items'        => Order::items($id),
            'paymentLabel' => $gateway !== null ? $gateway->label() : (string) $order['payment_method'],
            'history' => AdminSchema::has('order_status_history') ? db_all('SELECT h.*,u.name AS actor FROM order_status_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.order_id=? ORDER BY h.id',[$id]) : [],
            'payments' => AdminSchema::has('payments') ? db_all('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC',[$id]) : [],
        ], 'admin');
    }

    public function updateStatus(int $id): void
    {
        require_admin();
        $status = input('status', 20);
        try {
            AdminAudit::transaction(function () use ($id,$status): void {
                $order=db_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);
                if (!$order) throw new DomainException('Pedido não encontrado.');
                if (input('expected_status',20)!==$order['status']) throw new DomainException('O pedido mudou. Recarregue a página antes de continuar.');
                if ($status==='pago' && $order['status']==='pendente' && AdminSchema::has('payments')) {
                    $balance=to_cents($order['total'])-AdminPayments::received($id);
                    if ($balance>0) {
                        $pending=db_one("SELECT id FROM payments WHERE order_id=? AND status='pendente' AND amount=? AND (pix_expires_at IS NULL OR pix_expires_at>=NOW()) ORDER BY id LIMIT 1",[$id,cents_to_decimal($balance)]);
                        $paymentId=$pending?(int)$pending['id']:AdminPayments::create($id,['method'=>$order['payment_method']==='pix_manual'?'pix':'outro','amount'=>cents_to_decimal($balance),'gateway'=>'manual']);
                        AdminPayments::change($paymentId,'aprovar','Confirmação manual no pedido');
                    } else Order::changeStatus($id,'pago',input('status_note',255),$order['status']);
                } else Order::changeStatus($id,$status,input('status_note',255),$order['status']);
            });
            flash('success', 'Status do pedido nº ' . $id . ' alterado para "' . status_label($status) . '".'
                . ($status === 'cancelado' ? ' Os livros voltaram ao estoque.' : ''));
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/pedidos/' . $id);
    }

    public function metadata(int $id): void
    {
        require_admin();
        if (!AdminSchema::has('orders','admin_note')) { render_error(409); return; }
        try {
            AdminAudit::transaction(function () use ($id): void {
                $row=db_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);
                if (!$row) throw new DomainException('Pedido não encontrado.');
                $hash=hash('sha256',(string)$row['tracking_code']."\n".(string)$row['admin_note']);
                if (!hash_equals($hash,input('expected_metadata',64))) throw new DomainException('O rastreio ou a observação mudou. Recarregue a página.');
                $tracking=input('tracking_code',40); $note=input('admin_note',5000);
                db_run('UPDATE orders SET tracking_code=?,admin_note=? WHERE id=?',[$tracking?:null,$note?:null,$id]);
                AdminAudit::record('editar','orders',$id,['tracking_code'=>$tracking,'admin_note'=>$note]);
            });
            flash('success','Rastreio e observação salvos.');
        } catch (DomainException $e) { flash('error',$e->getMessage()); }
        redirect('/admin/pedidos/'.$id);
    }

    /** Dados da fatura emitida FORA do site (programa certificado). O site só guarda. */
    public function invoice(int $id): void
    {
        require_admin();
        if (!AdminSchema::has('orders', 'invoice_number')) { render_error(409); return; }
        $limit = static fn (string $col, int $fallback): int => min($fallback, AdminSchema::column('orders', $col)['length'] ?? $fallback);
        $number = input('invoice_number', $limit('invoice_number', 60));
        $rawUrl = trim(is_string($_POST['invoice_url'] ?? null) ? $_POST['invoice_url'] : '');
        $rawDate = input('invoice_issued_at', 20);
        $errors = [];

        $url = null;
        if ($rawUrl !== '') {
            if (strlen($rawUrl) > $limit('invoice_url', 500) || invoice_link($rawUrl) === null) {
                $errors[] = 'O link da fatura precisa começar com https:// e ser um endereço válido.';
            } else {
                $url = $rawUrl;
            }
        }
        $issuedAt = null;
        if ($rawDate !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
            if (!$date || $date->format('Y-m-d') !== $rawDate) {
                $errors[] = 'Informe a data de emissão no formato do calendário.';
            } else {
                $type = AdminSchema::column('orders', 'invoice_issued_at')['type'] ?? 'datetime';
                $issuedAt = $type === 'date' ? $date->format('Y-m-d') : $date->format('Y-m-d 00:00:00');
            }
        }
        if (($url !== null || $issuedAt !== null) && $number === '') {
            $errors[] = 'Informe o número da fatura.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('/admin/pedidos/' . $id);
        }
        try {
            AdminAudit::transaction(function () use ($id, $number, $url, $issuedAt): void {
                if (!db_one('SELECT id FROM orders WHERE id=? FOR UPDATE', [$id])) throw new DomainException('Pedido não encontrado.');
                Order::setInvoice($id, $number === '' ? null : $number, $url, $issuedAt);
                AdminAudit::record('fatura', 'orders', $id, ['invoice_number' => $number, 'invoice_url' => $url, 'invoice_issued_at' => $issuedAt]);
            });
            flash('success', 'Dados da fatura salvos.' . ($url !== null ? ' O cliente já vê o botão "Baixar fatura".' : ''));
        } catch (DomainException $e) { flash('error', $e->getMessage()); }
        redirect('/admin/pedidos/' . $id);
    }

    public function cancellation(int $id): void
    {
        require_admin();
        if (!AdminSchema::has('orders','cancel_requested_at')) { view('admin/setup',['title'=>'Cancelamentos'],'admin'); return; }
        try {
            $action=input('decision',10); $note=input('note',200);
            if (!in_array($action,['aprovar','recusar'],true) || $note==='') throw new DomainException('Escolha a decisão e registre o motivo.');
            AdminAudit::transaction(function () use ($id,$action,$note): void {
                $order=db_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);
                if (!$order || !$order['cancel_requested_at'] || in_array($order['status'],['cancelado','entregue'],true)) throw new DomainException('Não há solicitação pendente para este pedido.');
                if ($order['cancel_requested_at']!==input('expected_request',19)) throw new DomainException('A solicitação mudou. Recarregue a página.');
                if ($action==='aprovar') Order::changeStatus($id,'cancelado','Cancelamento solicitado pelo cliente: '.$note,$order['status']);
                else db_run('INSERT INTO order_status_history(order_id,user_id,old_status,new_status,note) VALUES (?,?,?,?,?)',[$id,$_SESSION['user_id']??null,$order['status'],$order['status'],'Cancelamento recusado: '.$note]);
                db_run('UPDATE orders SET cancel_requested_at=NULL WHERE id=?',[$id]);
                AdminAudit::record('cancelamento_'.$action,'orders',$id,['requested_at'=>$order['cancel_requested_at'],'note'=>$note]);
            });
            flash('success',$action==='aprovar'?'Cancelamento aprovado e estoque reposto. Confira se há pagamento a estornar.':'Solicitação de cancelamento recusada. A decisão ficou no histórico.');
        } catch (DomainException $e) { flash('error',$e->getMessage()); }
        redirect('/admin/pedidos/'.$id);
    }
}
