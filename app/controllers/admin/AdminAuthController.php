<?php
declare(strict_types=1);

final class AdminAuthController
{
    public function showLogin(): void
    {
        if (is_admin()) {
            redirect('/admin');
        }
        view('admin/login', ['title' => 'Entrar no painel', 'email' => '', 'error' => null], 'admin');
    }

    public function login(): void
    {
        $email = strtolower(input('email', 190));
        $password = (string) ($_POST['password'] ?? '');
        $user = valid_email($email) ? User::findByEmailWithHash($email) : null;

        if ($user === null || !password_verify($password, (string) $user['password_hash']) || $user['role'] !== 'admin') {
            http_response_code(422);
            view('admin/login', [
                'title' => 'Entrar no painel',
                'email' => $email,
                'error' => 'E-mail ou senha incorretos, ou esta conta não tem acesso ao painel.',
            ], 'admin');
            return;
        }

        User::rehashIfNeeded((int) $user['id'], $password, (string) $user['password_hash']);
        login_user($user);
        flash('success', 'Bem-vindo(a) ao painel, ' . $user['name'] . '.');
        redirect('/admin');
    }

    public function logout(): void
    {
        logout_user();
        flash('success', 'Você saiu do painel.');
        redirect('/admin/login');
    }
}
