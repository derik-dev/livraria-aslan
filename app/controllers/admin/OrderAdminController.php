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
        view('admin/orders', [
            'title'  => 'Pedidos',
            'status' => $status,
            'orders' => Order::all($status === '' ? null : $status),
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
        ], 'admin');
    }

    public function updateStatus(int $id): void
    {
        require_admin();
        $status = input('status', 20);
        try {
            Order::changeStatus($id, $status);
            flash('success', 'Status do pedido nº ' . $id . ' alterado para "' . status_label($status) . '".'
                . ($status === 'cancelado' ? ' Os livros voltaram ao estoque.' : ''));
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/pedidos/' . $id);
    }
}
