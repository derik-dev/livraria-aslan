<?php
declare(strict_types=1);

final class WorkflowAdminController
{
    private function context(): array
    {
        require_admin();
        $key = explode('/',trim(request_path(),'/'))[1] ?? '';
        $definition = AdminWorkflows::definitions()[$key] ?? null;
        if (!$definition) { render_error(404); exit; }
        if (!AdminSchema::has($definition['table'])) { view('admin/setup',['title'=>$definition['title']],'admin'); exit; }
        return [$key,$definition];
    }

    public function index(): void
    {
        [$key,$definition] = $this->context();
        $status = query('status',20); $q = query('q',100); $orderId = input_int('pedido',$_GET);
        $table = $definition['table']; $params = []; $where = ' WHERE 1=1';
        if (in_array($status,$definition['states'],true)) { $where .= ' AND t.status=?'; $params[]=$status; } else $status='';
        if ($key === 'mensagens') {
            $select='SELECT t.*,t.name AS customer_name,t.subject AS subject_label'; $from="FROM `$table` t";
            if ($q !== '') { $where .= " AND (t.name LIKE ? ESCAPE '!' OR t.email LIKE ? ESCAPE '!' OR t.subject LIKE ? ESCAPE '!')"; $like='%'.like_escape($q).'%'; $params=[...$params,$like,$like,$like]; }
        } elseif ($key === 'avaliacoes') {
            $select='SELECT t.*,u.name AS customer_name,b.title AS subject_label'; $from="FROM `$table` t JOIN users u ON u.id=t.user_id JOIN books b ON b.id=t.book_id";
            if ($q !== '') { $where .= " AND (u.name LIKE ? ESCAPE '!' OR b.title LIKE ? ESCAPE '!')"; $like='%'.like_escape($q).'%'; $params=[...$params,$like,$like]; }
        } else {
            $select='SELECT t.*,u.name AS customer_name'; $from="FROM `$table` t JOIN orders o ON o.id=t.order_id JOIN users u ON u.id=o.user_id";
            if ($q !== '') { $where .= " AND (u.name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')"; $like='%'.like_escape($q).'%'; $params=[...$params,$like,$like]; }
            if ($orderId !== null) { $where.=' AND t.order_id=?'; $params[]=$orderId; }
        }
        $data=AdminPage::fetch($select,$from.$where,$params,'t.id DESC');
        view('admin/workflow_list',$data+compact('key','definition','status','q','orderId')+['title'=>$definition['title']],'admin');
    }

    public function show(int $id): void
    {
        [$key,$definition]=$this->context();
        $row=db_one('SELECT * FROM `'.$definition['table'].'` WHERE id=?',[$id]);
        if (!$row) { render_error(404); return; }
        $order=isset($row['order_id']) && $row['order_id'] ? Order::find((int)$row['order_id']) : null;
        $customer=isset($row['user_id']) && $row['user_id'] ? User::find((int)$row['user_id']) : null;
        $book=$key==='avaliacoes' ? Book::find((int)$row['book_id']) : null;
        $items=$key==='devolucoes' ? db_all('SELECT ri.quantity,oi.title,oi.unit_price FROM return_items ri JOIN order_items oi ON oi.id=ri.order_item_id WHERE ri.return_id=?',[$id]) : [];
        $payments=$key==='devolucoes' && $order ? db_all("SELECT * FROM payments WHERE order_id=? AND status IN ('aprovado','estornado') ORDER BY id",[(int)$order['id']]) : [];
        view('admin/workflow_detail',compact('key','definition','row','order','customer','book','items','payments')+['title'=>$definition['title'].' nº '.$id],'admin');
    }

    public function storePayment(): void
    {
        require_admin();
        if (!AdminSchema::has('payments')) { render_error(409); return; }
        $orderId=input_int('order_id') ?? 0;
        try { $id=AdminPayments::create($orderId,$_POST); flash('success','Tentativa de pagamento registrada.'); redirect('/admin/pagamentos/'.$id); }
        catch (DomainException $e) { flash('error',$e->getMessage()); }
        catch (PDOException $e) { if ($e->getCode()!=='23000') throw $e; flash('error','Essa referência de gateway já está cadastrada.'); }
        redirect('/admin/pagamentos?pedido='.$orderId);
    }

    public function update(int $id): void
    {
        [$key,$definition]=$this->context(); $table=$definition['table'];
        $status=input('status',20); $expected=input('expected_status',20);
        $reply=input('admin_reply',5000); $note=input('note',2000); $mail=null;
        try {
            if ($key==='pagamentos') {
                $action=input('action',20);
                $refund=input('refund_amount',20)==='' ? null : parse_money(input('refund_amount',20));
                AdminPayments::change($id,$action,input('reason',255),$refund,$expected);
            } elseif ($key==='devolucoes') {
                $refund=input('refund_amount',20)==='' ? null : parse_money(input('refund_amount',20));
                if (input('refund_amount',20)!=='' && $refund===null) throw new DomainException('Valor de reembolso inválido.');
                AdminWorkflows::returnsUpdate($id,$status,$note,$refund,input_int('payment_id') ?? 0,input('restock',1)==='1',$expected);
            } else {
                if (!in_array($status,$definition['states'],true)) throw new DomainException('Situação inválida.');
                $mail=AdminAudit::transaction(function () use ($id,$key,$table,$status,$expected,$reply): ?array {
                    $row=db_one("SELECT * FROM `$table` WHERE id=? FOR UPDATE",[$id]);
                    if (!$row) throw new DomainException('Registro não encontrado.');
                    if ($row['status']!==$expected) throw new DomainException('O registro mudou. Recarregue a página.');
                    if ($key==='mensagens') {
                        if ($status==='respondida' && $reply==='') throw new DomainException('Escreva uma resposta.');
                        $replied=$status==='respondida';
                        db_run('UPDATE contact_messages SET status=?,admin_reply=?,replied_by=?,replied_at=CASE WHEN ?=1 THEN NOW() ELSE replied_at END WHERE id=?',[$status,$reply?:null,$replied?($_SESSION['user_id']??null):$row['replied_by'],$replied?1:0,$id]);
                        $mail=$replied && ($row['admin_reply']!==$reply || $row['status']!=='respondida') ? $row : null;
                    } else { db_run('UPDATE reviews SET status=?,admin_reply=? WHERE id=?',[$status,$reply?:null,$id]); $mail=null; }
                    AdminAudit::record('moderar',$table,$id,['old'=>$row['status'],'new'=>$status,'reply'=>$reply]); return $mail;
                });
            }
            flash('success','Alterações registradas.');
            if ($mail) {
                $sent=send_mail($mail['email'],'Resposta: '.$mail['subject'],$reply."\n\n".config('app_name'));
                flash($sent?'success':'info',$sent?'Resposta enviada por e-mail.':'A resposta foi salva, mas o servidor não conseguiu enviar o e-mail.');
            }
        } catch (DomainException $e) { flash('error',$e->getMessage()); }
        redirect('/admin/'.$key.'/'.$id);
    }

    public function destroy(int $id): void
    {
        [$key,$definition]=$this->context();
        if (!in_array($key,['avaliacoes','mensagens'],true)) { render_error(405); return; }
        AdminAudit::transaction(function () use ($definition,$id): void {
            db_run('DELETE FROM `'.$definition['table'].'` WHERE id=?',[$id]);
            AdminAudit::record('excluir',$definition['table'],$id);
        });
        flash('success','Registro excluído.'); redirect('/admin/'.$key);
    }

    public function uploadProof(int $id): void
    {
        require_admin(); $proof=null;
        try {
            if (!AdminSchema::has('payments') || !db_value('SELECT id FROM payments WHERE id=?',[$id])) { render_error(404); return; }
            $proof=AdminProof::upload();
            if ($proof===null) throw new DomainException('Escolha um comprovante.');
            AdminAudit::transaction(function () use ($proof,$id): void {
                $payment=db_one('SELECT id FROM payments WHERE id=? FOR UPDATE',[$id]);
                if (!$payment) throw new DomainException('Pagamento não encontrado.');
                db_run('UPDATE payments SET proof_image=? WHERE id=?',[$proof,$id]);
                AdminAudit::record('comprovante','payments',$id);
            });
            flash('success','Comprovante salvo com acesso restrito ao painel.');
        } catch (Throwable $e) {
            $path=AdminProof::path($proof); if ($path && is_file($path)) unlink($path);
            if (!$e instanceof DomainException) throw $e;
            flash('error',$e->getMessage());
        }
        redirect('/admin/pagamentos/'.$id);
    }

    public function proof(int $id): void
    {
        require_admin();
        if (!AdminSchema::has('payments')) { render_error(404); return; }
        $payment=db_one('SELECT proof_image FROM payments WHERE id=?',[$id]);
        $path=AdminProof::path($payment['proof_image']??null);
        // Comprovantes antigos em uploads/ também exigem acesso pelo painel.
        if (!$path && preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp)$#D',$payment['proof_image']??'')) $path=APP_ROOT.'/public/'.$payment['proof_image'];
        if (!$path || !is_file($path)) { render_error(404); return; }
        header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store'); header('Content-Disposition: attachment; filename="comprovante-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"');
        readfile($path);
    }
}
