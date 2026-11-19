<?php
declare(strict_types=1);

final class AccountController
{
    public function profile(): void
    {
        $user = require_login();
        $this->renderProfile($user, [
            'profile'  => ['name' => $user['name'], 'email' => $user['email'], 'phone' => (string) ($user['phone'] ?? '')],
            'address'  => Address::forUser((int) $user['id']) ?? [],
        ]);
    }

    public function updateProfile(): void
    {
        $user = require_login();
        $name = input('name', 100);
        $email = strtolower(input('email', 190));
        $phone = only_digits(input('phone', 20));
        $nifEnabled = AdminSchema::has('users', 'nif');
        [$nif, $nifError] = $nifEnabled ? Nif::read(input('nif', 30), false) : ['', null];

        $errors = [];
        if (text_length($name) < 3) {
            $errors['name'] = 'Informe seu nome completo.';
        }
        if (!valid_email($email)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } elseif (User::emailTaken($email, (int) $user['id'])) {
            $errors['email'] = 'Este e-mail já está em uso por outra conta.';
        }
        if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 11)) {
            $errors['phone'] = 'Informe o telefone com DDD (10 ou 11 números).';
        }
        if ($nifError !== null) {
            $errors['nif'] = $nifError;
        }

        if ($errors) {
            http_response_code(422);
            $this->renderProfile($user, [
                'profile'        => ['name' => $name, 'email' => $email, 'phone' => $phone, 'nif' => $nif],
                'address'        => Address::forUser((int) $user['id']) ?? [],
                'profile_errors' => $errors,
            ]);
            return;
        }

        User::updateProfile((int) $user['id'], $name, $email, $phone === '' ? null : $phone);
        if ($nifEnabled) {
            User::setNif((int) $user['id'], $nif === '' ? null : $nif);
        }
        flash('success', 'Seus dados foram salvos.');
        redirect('/perfil');
    }

    public function updateAddress(): void
    {
        $user = require_login();
        [$address, $errors] = read_address_input();
        if ($errors) {
            http_response_code(422);
            $this->renderProfile($user, [
                'profile'        => ['name' => $user['name'], 'email' => $user['email'], 'phone' => (string) ($user['phone'] ?? '')],
                'address'        => $address,
                'address_errors' => $errors,
            ]);
            return;
        }
        Address::save((int) $user['id'], $address);
        flash('success', 'Endereço salvo.');
        redirect('/perfil');
    }

    public function updatePassword(): void
    {
        $user = require_login();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $error = null;
        $hash = User::passwordHash((int) $user['id']);
        if ($hash === null || !password_verify($current, $hash)) {
            $error = 'A senha atual está incorreta.';
        } else {
            $error = AuthController::validatePassword($new, $confirm);
        }

        if ($error !== null) {
            http_response_code(422);
            $this->renderProfile($user, [
                'profile'        => ['name' => $user['name'], 'email' => $user['email'], 'phone' => (string) ($user['phone'] ?? '')],
                'address'        => Address::forUser((int) $user['id']) ?? [],
                'password_error' => $error,
            ]);
            return;
        }

        User::updatePassword((int) $user['id'], $new);
        session_regenerate_id(true);
        flash('success', 'Senha alterada.');
        redirect('/perfil');
    }

    public function orders(): void
    {
        $user = require_login();
        view('account/orders', ['title' => 'Meus pedidos', 'orders' => Order::forUser((int) $user['id'])]);
    }

    public function orderShow(int $id): void
    {
        $user = require_login();
        $order = Order::findForUser($id, (int) $user['id']);
        if ($order === null) {
            render_error(404);
            return;
        }
        $gateway = Payment::get((string) $order['payment_method']);
        view('account/order_detail', [
            'title'        => 'Pedido nº ' . $order['id'],
            'order'        => $order,
            'items'        => Order::items($id),
            'instructions' => ($gateway !== null && $order['status'] === 'pendente') ? $gateway->instructions($order) : null,
            'paymentLabel' => $gateway !== null ? $gateway->label() : (string) $order['payment_method'],
            'returns'      => CustomerReturn::enabled() ? [
                'check'    => CustomerReturn::eligibility($order),
                'items'    => CustomerReturn::items($id),
                'requests' => CustomerReturn::forOrder($id, (int) $user['id']),
            ] : null,
        ]);
    }

    public function requestReturn(int $id): void
    {
        $user = require_login();
        if (!CustomerReturn::enabled()) {
            render_error(404);
            return;
        }
        $posted = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
        $quantities = [];
        foreach ($posted as $itemId => $qty) {
            $n = filter_var(is_string($qty) ? trim($qty) : '', FILTER_VALIDATE_INT);
            if (is_int($itemId) && $n !== false && $n > 0) {
                $quantities[$itemId] = $n;
            }
        }
        try {
            CustomerReturn::create($id, (int) $user['id'], input('type', 20), input('reason', 20), input('description', 2000), $quantities);
            flash('success', 'Pedido de devolução/troca enviado. A loja vai analisar e você acompanha aqui.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/pedidos/' . $id . '#devolucao');
    }

    private function renderProfile(array $user, array $data): void
    {
        view('account/profile', $data + [
            'title'          => 'Meu perfil',
            'user'           => $user,
            'profile_errors' => [],
            'address_errors' => [],
            'password_error' => null,
            'nifEnabled'     => AdminSchema::has('users', 'nif'),
        ]);
    }
}
