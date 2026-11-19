<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        require_admin();
        $threshold = (int) config('shop.low_stock', 5);
        view('admin/dashboard', [
            'title'         => 'Painel',
            'counts'        => Order::countByStatus(),
            'pending'       => Order::all('pendente'),
            'lowStock'      => Book::lowStock($threshold),
            'threshold'     => $threshold,
            'booksTotal'    => Book::count(),
            'customersTotal'=> (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
        ], 'admin');
    }
}
