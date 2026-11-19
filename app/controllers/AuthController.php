<?php
declare(strict_types=1);

final class AuthController
{
    public function showRegister(): void
    {
        if (current_user() !== null) {
            redirect('/perfil');
        }
        view('auth/register', ['title' => 'Criar conta', 'form' => [], 'errors' => []]);
    }

    public function register(): void
    {
        $name = input('name', 100);
        $email = strtolower(input('email', 190));
        $phone = only_digits(input('phone', 20));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];
        if (text_length($name) < 3) {
            $errors['name'] = 'Informe seu nome completo.';
        }
        if (!valid_email($email)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } elseif (User::emailTaken($email)) {
            $errors['email'] = 'Já existe uma conta com este e-mail. Tente entrar ou recuperar a senha.';
        }
        if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 11)) {
            $errors['phone'] = 'Informe o telefone com DDD (10 ou 11 números).';
        }
        $passwordError = self::validatePassword($password, $confirm);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }

        if ($errors) {
            http_response_code(422);
            view('auth/register', [
                'title'  => 'Criar conta',
                'form'   => ['name' => $name, 'email' => $email, 'phone' => $phone],
                'errors' => $errors,
            ]);
            return;
        }

        $id = User::create($name, $email, $password, $phone === '' ? null : $phone);
        login_user(['id' => $id]);
        flash('success', 'Conta criada. Bem-vindo(a), ' . $name . '!');
        redirect(take_intended('/'));
    }

    public function showLogin(): void
    {
        if (current_user() !== null) {
            redirect('/perfil');
        }
        view('auth/login', ['title' => 'Entrar', 'email' => '', 'error' => null]);
    }

    public function login(): void
    {
        $email = strtolower(input('email', 190));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $result = AdminLogin::authenticate($email, $password);
        $user = $result['user'];
        if ($user === null) {
            http_response_code($result['blocked'] ? 429 : 422);
            if ($result['blocked']) header('Retry-After: 900');
            view('auth/login', ['title' => 'Entrar', 'email' => $email, 'error' => $result['blocked'] ? 'Muitas tentativas. Aguarde 15 minutos.' : 'E-mail ou senha incorretos, ou conta bloqueada.']);
            return;
        }

        User::rehashIfNeeded((int) $user['id'], $password, (string) $user['password_hash']);
        login_user($user);
        flash('success', 'Olá, ' . $user['name'] . '!');
        redirect(take_intended($user['role'] === 'admin' ? '/admin' : '/'));
    }

    public function logout(): void
    {
        logout_user();
        flash('success', 'Você saiu da sua conta.');
        redirect('/');
    }

    public function showForgot(): void
    {
        view('auth/forgot', ['title' => 'Recuperar senha', 'email' => '', 'error' => null]);
    }

    public function forgot(): void
    {
        $email = strtolower(input('email', 190));
        if (!valid_email($email)) {
            http_response_code(422);
            view('auth/forgot', ['title' => 'Recuperar senha', 'email' => $email, 'error' => 'Informe um e-mail válido.']);
            return;
        }

        $user = User::findByEmailWithHash($email);
        if ($user !== null) {
            $token = PasswordReset::create((int) $user['id']);
            if ($token !== null) {
                $link = absolute_url('/redefinir-senha', ['token' => $token]);
                $body = "Olá, {$user['name']}.\n\n"
                    . "Recebemos um pedido para redefinir a senha da sua conta na " . config('app_name') . ".\n"
                    . "Abra o link abaixo em até 1 hora para criar uma senha nova:\n\n{$link}\n\n"
                    . "Se não foi você, ignore este e-mail. Sua senha continua a mesma.\n";
                $sent = send_mail($email, 'Redefinir sua senha', $body);
                if (config('debug')) {
                    flash('info', 'Modo de teste (debug ligado): link de redefinição: ' . $link . ($sent ? '' : ' (o e-mail não foi enviado).'));
                }
            }
        }

        // Mesma resposta exista ou não a conta (não revela e-mails cadastrados).
        flash('success', 'Se existir uma conta com este e-mail, enviamos um link para criar uma senha nova. Confira também a caixa de spam.');
        redirect('/login');
    }

    public function showReset(): void
    {
        $token = query('token', 64);
        if (PasswordReset::findValid($token) === null) {
            flash('error', 'Este link de redefinição é inválido ou expirou. Peça um novo.');
            redirect('/recuperar-senha');
        }
        view('auth/reset', ['title' => 'Criar senha nova', 'token' => $token, 'error' => null]);
    }

    public function reset(): void
    {
        $token = input('token', 64);
        $reset = PasswordReset::findValid($token);
        if ($reset === null) {
            flash('error', 'Este link de redefinição é inválido ou expirou. Peça um novo.');
            redirect('/recuperar-senha');
        }

        $password = (string) ($_POST['password'] ?? '');
        $error = self::validatePassword($password, (string) ($_POST['password_confirm'] ?? ''));
        if ($error !== null) {
            http_response_code(422);
            view('auth/reset', ['title' => 'Criar senha nova', 'token' => $token, 'error' => $error]);
            return;
        }

        PasswordReset::consume($reset, $password);
        flash('success', 'Senha alterada. Entre com a senha nova.');
        redirect('/login');
    }

    public static function validatePassword(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8) {
            return 'A senha precisa ter pelo menos 8 caracteres.';
        }
        if (strlen($password) > 72) {
            return 'A senha pode ter no máximo 72 caracteres.';
        }
        if (!hash_equals($password, $confirm)) {
            return 'As duas senhas não são iguais.';
        }
        return null;
    }
}
