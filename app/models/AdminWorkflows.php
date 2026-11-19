<?php
declare(strict_types=1);

final class AdminWorkflows
{
    public static function definitions(): array
    {
        return [
            'pagamentos' => ['title'=>'Pagamentos','table'=>'payments','states'=>['pendente','aprovado','recusado','estornado','expirado','cancelado']],
            'avaliacoes' => ['title'=>'Avaliações','table'=>'reviews','states'=>['pendente','aprovado','rejeitado']],
            'mensagens' => ['title'=>'Mensagens','table'=>'contact_messages','states'=>['nova','lida','respondida','arquivada']],
            'devolucoes' => ['title'=>'Devoluções e trocas','table'=>'returns','states'=>['solicitada','aprovada','recusada','recebida','concluida']],
        ];
    }

    public static function returnsUpdate(int $id, string $status, string $note, ?int $refund, int $paymentId, bool $restock, string $expected): void
    {
        $row = db_one('SELECT order_id FROM `returns` WHERE id=?',[$id]);
        if (!$row) throw new DomainException('Solicitação não encontrada.');
        AdminAudit::transaction(function () use ($id,$status,$note,$refund,$paymentId,$restock,$expected,$row): void {
            $order = db_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[(int) $row['order_id']]);
            $request = db_one('SELECT * FROM `returns` WHERE id=? FOR UPDATE',[$id]);
            if (!$order || !$request || (int) $request['user_id'] !== (int) $order['user_id']) throw new DomainException('A solicitação não corresponde ao cliente do pedido.');
            if ($order['status'] === 'cancelado') throw new DomainException('O pedido já foi cancelado. Confira a reposição de estoque no histórico antes de tratar uma devolução.');
            if ($expected !== $request['status']) throw new DomainException('A solicitação mudou. Recarregue a página.');
            $allowed = ['solicitada'=>['aprovada','recusada'],'aprovada'=>['recebida'],'recebida'=>['concluida'],'recusada'=>[],'concluida'=>[]];
            if ($status !== $request['status'] && !in_array($status,$allowed[$request['status']] ?? [],true)) throw new DomainException('Siga a sequência de aprovação, recebimento e conclusão.');
            if ($note === '') throw new DomainException('Registre uma observação sobre a decisão.');
            $items = db_all('SELECT ri.*,oi.order_id,oi.book_id,oi.title,oi.quantity AS sold_quantity,oi.line_total FROM return_items ri JOIN order_items oi ON oi.id=ri.order_item_id WHERE ri.return_id=? ORDER BY oi.book_id',[$id]);
            if (!$items) throw new DomainException('Esta solicitação não tem itens.');
            $maxRefund = 0;
            foreach ($items as $item) {
                if ((int) $item['order_id'] !== (int) $order['id'] || (int) $item['quantity'] < 1 || (int) $item['quantity'] > (int) $item['sold_quantity']) throw new DomainException('Confira os itens e quantidades desta solicitação.');
                $other = (int) db_value("SELECT COALESCE(SUM(ri.quantity),0) FROM return_items ri JOIN `returns` r ON r.id=ri.return_id WHERE ri.order_item_id=? AND r.id<>? AND r.status IN ('aprovada','recebida','concluida')",[(int) $item['order_item_id'],$id]);
                if ($other+(int) $item['quantity'] > (int) $item['sold_quantity']) throw new DomainException('Existem outras devoluções aprovadas para os mesmos itens.');
                $maxRefund += (int) floor(to_cents($item['line_total'])*(int) $item['quantity']/(int) $item['sold_quantity']);
            }
            if ($restock && !($request['status'] === 'aprovada' && $status === 'recebida')) throw new DomainException('A reposição só pode ser registrada ao receber os itens.');
            if ($restock) foreach ($items as $item) {
                if ($item['book_id'] === null) continue;
                $book = db_one('SELECT id,stock FROM books WHERE id=? FOR UPDATE',[(int) $item['book_id']]);
                if (!$book) continue;
                $after = (int) $book['stock']+(int) $item['quantity'];
                db_run('UPDATE books SET stock=? WHERE id=?',[$after,(int) $book['id']]);
                AdminStock::record((int) $book['id'],'entrada',(int) $item['quantity'],$after,'Recebimento da devolução nº ' . $id);
            }
            if ($refund !== null && $refund > 0) {
                if ($status !== 'concluida' || $request['status'] === 'concluida') throw new DomainException('O reembolso é registrado uma vez, na conclusão.');
                if ($refund > min($maxRefund,to_cents($order['total']))) throw new DomainException('O reembolso ultrapassa o valor dos itens ou do pedido.');
                $payment = db_one('SELECT id FROM payments WHERE id=? AND order_id=?',[$paymentId,(int) $order['id']]);
                if (!$payment) throw new DomainException('Escolha um pagamento deste pedido para registrar o reembolso já realizado.');
                AdminPayments::change($paymentId,'estornar','Devolução nº ' . $id . ': ' . $note,$refund);
            }
            if ($request['status'] === 'concluida' && $refund !== null && $refund !== to_cents($request['refund_amount'] ?? 0)) throw new DomainException('O reembolso desta solicitação já foi registrado.');
            db_run('UPDATE `returns` SET status=?,admin_note=?,refund_amount=?,resolved_at=CASE WHEN ? IN (\'concluida\',\'recusada\') THEN COALESCE(resolved_at,NOW()) ELSE resolved_at END WHERE id=?',[$status,$note,$refund === null ? $request['refund_amount'] : cents_to_decimal($refund),$status,$id]);
            AdminAudit::record('atualizar','returns',$id,['old'=>$request['status'],'new'=>$status,'restock'=>$restock,'refund_cents'=>$refund]);
        });
    }
}
