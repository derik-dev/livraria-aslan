<?php
declare(strict_types=1);

final class AdminPayments
{
    public static function create(int $orderId, array $data): int
    {
        return AdminAudit::transaction(function () use ($orderId,$data): int {
            $order = db_one('SELECT * FROM orders WHERE id=? FOR UPDATE', [$orderId]);
            if (!$order || $order['status'] === 'cancelado') throw new DomainException('Pedido inexistente ou cancelado.');
            $amount = parse_money(clean_string($data['amount'] ?? '',20));
            if ($amount === null || $amount <= 0 || $amount > to_cents($order['total'])) throw new DomainException('O valor deve ser maior que zero e não pode passar do total do pedido.');
            $methods = ['pix','cartao_credito','cartao_debito','boleto','outro'];
            if (!in_array($data['method'] ?? '',$methods,true)) throw new DomainException('Escolha uma forma de pagamento válida.');
            $gateway = clean_string($data['gateway'] ?? '',30) ?: 'manual';
            $installments = filter_var($data['installments'] ?? '1',FILTER_VALIDATE_INT);
            if ($installments === false || $installments < 1 || $installments > 24) throw new DomainException('Informe entre 1 e 24 parcelas.');
            $response = clean_string($data['gateway_response'] ?? '',10000);
            if ($response !== '') { json_decode($response); if (json_last_error() !== JSON_ERROR_NONE) throw new DomainException('O retorno do gateway precisa ser JSON válido.'); }
            $expiry = clean_string($data['pix_expires_at'] ?? '',16);
            if ($expiry !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$expiry);
                if (!$date || $date->format('Y-m-d\TH:i') !== $expiry) throw new DomainException('Prazo Pix inválido.');
                $expiry = $date->format('Y-m-d H:i:s');
            }
            db_run('INSERT INTO payments(order_id,method,gateway,gateway_id,amount,installments,pix_code,pix_expires_at,gateway_response) VALUES (?,?,?,?,?,?,?,?,?)', [
                $orderId,$data['method'],$gateway,clean_string($data['gateway_id'] ?? '',100) ?: null,cents_to_decimal($amount),$installments,
                clean_string($data['pix_code'] ?? '',5000) ?: null,$expiry ?: null,$response ?: null,
            ]);
            $id = (int) db()->lastInsertId(); AdminAudit::record('registrar','payments',$id,['order_id'=>$orderId,'amount'=>cents_to_decimal($amount)]); return $id;
        });
    }

    public static function change(int $id, string $action, string $reason, ?int $refund = null, ?string $expected = null): void
    {
        $payment = db_one('SELECT order_id FROM payments WHERE id=?',[$id]);
        if (!$payment) throw new DomainException('Pagamento não encontrado.');
        AdminAudit::transaction(function () use ($id,$action,$reason,$refund,$expected,$payment): void {
            $order = db_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[(int) $payment['order_id']]);
            $row = db_one('SELECT * FROM payments WHERE id=? FOR UPDATE',[$id]);
            if (!$order || !$row) throw new DomainException('Pagamento ou pedido não encontrado.');
            if ($expected !== null && $expected !== $row['status']) throw new DomainException('O pagamento mudou. Recarregue a página.');
            if (!in_array($action,['aprovar','recusar','estornar'],true)) throw new DomainException('Ação inválida.');
            if ($action === 'aprovar') {
                if ($row['status'] !== 'pendente' || $order['status'] === 'cancelado') throw new DomainException('Só é possível aprovar um pagamento pendente de pedido não cancelado.');
                if ($row['pix_expires_at'] && strtotime($row['pix_expires_at']) < time()) throw new DomainException('O prazo deste Pix expirou. Registre uma nova tentativa após conferir o recebimento.');
                $received = self::received((int) $order['id']);
                if ($received + to_cents($row['amount']) > to_cents($order['total'])) throw new DomainException('A aprovação ultrapassaria o total do pedido. Confira os pagamentos existentes.');
                db_run("UPDATE payments SET status='aprovado',paid_at=NOW() WHERE id=?",[$id]);
                if ($order['status'] === 'pendente' && $received + to_cents($row['amount']) === to_cents($order['total'])) Order::changeStatus((int) $order['id'],'pago','Pagamento nº ' . $id . ' confirmado');
            } elseif ($action === 'recusar') {
                if ($row['status'] !== 'pendente' || $reason === '') throw new DomainException('Só é possível recusar um pagamento pendente; informe o motivo.');
                db_run("UPDATE payments SET status='recusado',refund_reason=? WHERE id=?",[$reason,$id]);
            } else {
                if (!in_array($row['status'],['aprovado','estornado'],true) || $refund === null || $refund <= 0 || $reason === '') throw new DomainException('Informe o valor e o motivo de um estorno já realizado.');
                $already = to_cents($row['refund_amount'] ?? 0);
                if ($already + $refund > to_cents($row['amount'])) throw new DomainException('O estorno ultrapassa o saldo deste pagamento.');
                db_run("UPDATE payments SET status='estornado',refund_amount=?,refund_reason=?,refunded_at=NOW() WHERE id=?",[cents_to_decimal($already+$refund),$reason,$id]);
                // Estorno é registro financeiro; cancelamento e recebimento de livros são ações separadas.
            }
            AdminAudit::record($action,'payments',$id,['reason'=>$reason,'refund_cents'=>$refund]);
        });
    }

    public static function received(int $orderId): int
    {
        return to_cents(db_value("SELECT COALESCE(SUM(amount-COALESCE(refund_amount,0)),0) FROM payments WHERE order_id=? AND status IN ('aprovado','estornado')",[$orderId]));
    }
}
