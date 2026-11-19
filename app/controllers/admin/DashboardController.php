<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        require_admin();
        $threshold = (int) config('shop.low_stock', 5);
        $start = query('inicio',10) ?: date('Y-m-d', strtotime('-29 days'));
        $end = query('fim',10) ?: date('Y-m-d');
        $error = null;
        try { $sales = AdminReports::sales($start,$end); }
        catch (DomainException $e) { http_response_code(422); $error = $e->getMessage(); $sales = ['daily'=>[], 'top'=>[], 'revenue'=>0, 'count'=>0]; }
        view('admin/dashboard', [
            'start' => $start, 'end' => $end, 'sales' => $sales, 'error' => $error, 'attention' => AdminReports::attention(),
            'title'         => 'Painel',
            'counts'        => Order::countByStatus(),
            'pending'       => Order::all('pendente'),
            'lowStock'      => Book::lowStock($threshold),
            'threshold'     => $threshold,
            'booksTotal'    => Book::count('', true),
            'customersTotal'=> (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
        ], 'admin');
    }
}
