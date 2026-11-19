<?php
declare(strict_types=1);

final class AdminAuthController
{
    public function showLogin(): void
    {
        if (is_admin()) {
            redirect('/admin');
        }
        view('admin/login', ['title' => 'Entrar no painel', 'email' => '', 'error' => null, 'loginPage' => true], 'admin');
    }

    public function login(): void
    {
        $email = strtolower(input('email', 190));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $result = AdminLogin::authenticate($email, $password, true);
        $user = $result['user'];

        if ($user === null) {
            http_response_code($result['blocked'] ? 429 : 422);
            if ($result['blocked']) header('Retry-After: 900');
            view('admin/login', [
                'title' => 'Entrar no painel',
                'email' => $email,
                'error' => $result['blocked'] ? 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.' : 'E-mail ou senha incorretos, ou esta conta não tem acesso ao painel.',
                'loginPage' => true,
            ], 'admin');
            return;
        }

        User::rehashIfNeeded((int) $user['id'], $password, (string) $user['password_hash']);
        login_user($user);
        AdminAudit::record('login', 'users', (int) $user['id']);
        flash('success', 'Bem-vindo(a) ao painel, ' . $user['name'] . '.');
        redirect('/admin');
    }

    public function logout(): void
    {
        if (is_admin()) AdminAudit::record('logout', 'users', (int) current_user()['id']);
        logout_user();
        flash('success', 'Você saiu do painel.');
        redirect('/admin/login');
    }
}
