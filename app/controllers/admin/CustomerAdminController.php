<?php
declare(strict_types=1);

final class CustomerAdminController
{
    public function index(): void
    {
        require_admin();
        $q = query('q', 100);
        view('admin/customers', [
            'title'     => 'Clientes',
            'q'         => $q,
            'customers' => User::customers($q),
        ], 'admin');
    }
}
