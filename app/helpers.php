<?php
declare(strict_types=1);

const ORDER_STATUSES = ['pendente', 'pago', 'enviado', 'entregue', 'cancelado'];

const BR_STATES = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
    'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

/* ---------------------------------------------------------------- config */

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/* ---------------------------------------------------------------- saída */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_to_chars($s));
}

/** Fallback de contagem de caracteres sem mbstring. */
function utf8_to_chars(string $s): string
{
    return (string) preg_replace('/[\x{80}-\x{10FFFF}]/u', '?', $s);
}

function money_cents(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function money(mixed $value): string
{
    return money_cents(to_cents($value));
}

function to_cents(mixed $value): int
{
    return (int) round(((float) $value) * 100);
}

function cents_to_decimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function format_date(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    return date($withTime ? 'd/m/Y \à\s H:i' : 'd/m/Y', $ts);
}

function status_label(string $status): string
{
    return match ($status) {
        'pendente'  => 'Pendente',
        'pago'      => 'Pago',
        'enviado'   => 'Enviado',
        'entregue'  => 'Entregue',
        'cancelado' => 'Cancelado',
        default     => $status,
    };
}

function book_image(?string $image): string
{
    return url($image !== null && $image !== '' ? $image : 'assets/img/sem-capa.svg');
}

/* ---------------------------------------------------------------- URLs */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function base_path(): string
{
    return rtrim((string) config('base_path', ''), '/');
}

function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    }
    return $u;
}

function absolute_url(string $path, array $query = []): string
{
    $app = rtrim((string) config('app_url', ''), '/');
    if ($app === '' || str_contains($app, 'SEU-DOMINIO')) {
        $host = preg_replace('/[^a-zA-Z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $app = (is_https() ? 'https' : 'http') . '://' . $host;
    }
    return $app . url($path, $query);
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $path = rawurldecode(is_string($path) ? $path : '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, strlen('/index.php'));
    }
    return '/' . trim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/** Aceita só caminhos internos (evita redirecionar para outro site). */
function safe_internal_path(?string $path, string $fallback = '/'): string
{
    if (!is_string($path) || $path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
        return $fallback;
    }
    return $path;
}

/* ---------------------------------------------------------------- sessão */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('livraria_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

/* ---------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    $real = $_SESSION['_csrf'] ?? '';
    if (!is_string($sent) || !is_string($real) || $real === '' || !hash_equals($real, $sent)) {
        render_error(419);
        exit;
    }
}

/* ---------------------------------------------------------------- input */

function clean_string(mixed $value, int $max = 255): string
{
    if (!is_string($value)) {
        return '';
    }
    // Remove caracteres de controle (mantém quebras de linha) e UTF-8 inválido.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if ($value === null) {
        return '';
    }
    $value = trim(str_replace("\r\n", "\n", $value));
    if (text_length($value) > $max) {
        $value = function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
    return $value;
}

function input(string $key, int $max = 255): string
{
    return clean_string($_POST[$key] ?? '', $max);
}

function query(string $key, int $max = 255): string
{
    return clean_string($_GET[$key] ?? '', $max);
}

function input_int(string $key, ?array $source = null): ?int
{
    $source ??= $_POST;
    $v = $source[$key] ?? null;
    if (is_int($v)) {
        return $v;
    }
    if (!is_string($v)) {
        return null;
    }
    $r = filter_var(trim($v), FILTER_VALIDATE_INT);
    return $r === false ? null : $r;
}

/** Escapa curingas do LIKE (usar com ESCAPE '!'). */
function like_escape(string $s): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
}

function only_digits(string $s): string
{
    return (string) preg_replace('/\D+/', '', $s);
}

function valid_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function parse_money(string $value): ?int
{
    $v = str_replace(['R$', ' '], '', $value);
    if (str_contains($v, ',')) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    }
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
        return null;
    }
    return (int) round(((float) $v) * 100);
}

/** Lê e valida os campos de endereço enviados no POST. */
function read_address_input(): array
{
    $data = [
        'cep'        => only_digits(input('cep', 20)),
        'street'     => input('street', 150),
        'number'     => input('number', 20),
        'complement' => input('complement', 100),
        'district'   => input('district', 100),
        'city'       => input('city', 100),
        'state'      => strtoupper(input('state', 2)),
    ];
    $errors = [];
    if (strlen($data['cep']) !== 8) {
        $errors['cep'] = 'Informe um CEP com 8 números.';
    } else {
        $data['cep'] = substr($data['cep'], 0, 5) . '-' . substr($data['cep'], 5);
    }
    if (text_length($data['street']) < 3) {
        $errors['street'] = 'Informe a rua.';
    }
    if ($data['number'] === '') {
        $errors['number'] = 'Informe o número (ou "s/n").';
    }
    if (text_length($data['district']) < 2) {
        $errors['district'] = 'Informe o bairro.';
    }
    if (text_length($data['city']) < 2) {
        $errors['city'] = 'Informe a cidade.';
    }
    if (!in_array($data['state'], BR_STATES, true)) {
        $errors['state'] = 'Escolha o estado.';
    }
    return [$data, $errors];
}

/* ---------------------------------------------------------------- auth */

function current_user(): ?array
{
    static $cache = [];
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = User::find($id);
        if ($cache[$id] === null) {
            unset($_SESSION['user_id']);
        }
    }
    return $cache[$id];
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['_csrf']);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $_SESSION['intended'] = safe_internal_path($uri);
        flash('info', 'Entre na sua conta para continuar.');
        redirect('/login');
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/admin/login');
    }
    if ($user['role'] !== 'admin') {
        render_error(403);
        exit;
    }
    return $user;
}

function take_intended(string $fallback = '/'): string
{
    $p = safe_internal_path($_SESSION['intended'] ?? null, $fallback);
    unset($_SESSION['intended']);
    return $p;
}

/* ---------------------------------------------------------------- carrinho */

function cart(): array
{
    $raw = $_SESSION['cart'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $clean = [];
    foreach ($raw as $id => $qty) {
        if (is_int($id) && $id > 0 && is_int($qty) && $qty > 0) {
            $clean[$id] = $qty;
        }
    }
    return $clean;
}

function cart_set(int $bookId, int $qty): void
{
    $cart = cart();
    if ($qty <= 0) {
        unset($cart[$bookId]);
    } else {
        $cart[$bookId] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

function cart_count(): int
{
    return array_sum(cart());
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function shipping_cents(int $subtotalCents): int
{
    if ($subtotalCents <= 0) {
        return 0;
    }
    $freeFrom = to_cents(config('shop.free_shipping_from', 0));
    if ($freeFrom > 0 && $subtotalCents >= $freeFrom) {
        return 0;
    }
    return to_cents(config('shop.shipping_flat', 0));
}

/* ---------------------------------------------------------------- views */

function render_template(string $__template, array $__data = []): string
{
    $__file = APP_ROOT . '/app/views/' . $__template . '.php';
    if (!is_file($__file)) {
        throw new RuntimeException('View não encontrada: ' . $__template);
    }
    extract($__data, EXTR_SKIP);
    $__level = ob_get_level();
    ob_start();
    try {
        require $__file;
    } catch (Throwable $ex) {
        while (ob_get_level() > $__level) {
            ob_end_clean();
        }
        throw $ex;
    }
    return (string) ob_get_clean();
}

function view(string $template, array $data = [], string $layout = 'main'): void
{
    $content = render_template($template, $data);
    echo render_template('layout/' . $layout, $data + ['content' => $content]);
}

function render_error(int $code, ?Throwable $exception = null): void
{
    $info = [
        403 => ['Acesso negado', 'Você não tem permissão para ver esta página.'],
        404 => ['Página não encontrada', 'O endereço não existe ou o item foi removido.'],
        405 => ['Ação não permitida', 'Esta página não aceita este tipo de envio.'],
        419 => ['Formulário expirado', 'O formulário ficou aberto por muito tempo ou o arquivo enviado era grande demais. Volte, recarregue a página e tente de novo.'],
        500 => ['Erro no servidor', 'Algo deu errado do nosso lado. Tente de novo em instantes.'],
    ];
    [$title, $message] = $info[$code] ?? ['Erro', 'Ocorreu um erro.'];

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
    }

    $template = in_array($code, [403, 404], true) ? 'errors/' . $code : 'errors/generic';
    $data = [
        'title'   => $title,
        'code'    => $code,
        'message' => $message,
        'detail'  => ($exception !== null && config('debug')) ? (string) $exception : null,
    ];
    try {
        view($template, $data);
    } catch (Throwable $inner) {
        error_log((string) $inner);
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . e($title) . '</title></head>'
            . '<body style="font-family:Arial,sans-serif;font-size:18px;color:#000;background:#fff;padding:24px">'
            . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        if ($data['detail'] !== null) {
            echo '<pre style="white-space:pre-wrap;font-size:16px">' . e($data['detail']) . "\n\n" . e((string) $inner) . '</pre>';
        }
        echo '</body></html>';
    }
}

/* ---------------------------------------------------------------- e-mail */

function app_log(string $file, string $line): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    @file_put_contents($dir . '/' . $file, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function send_mail(string $to, string $subject, string $body): bool
{
    $to = str_replace(["\r", "\n"], '', $to);
    $from = str_replace(["\r", "\n"], '', (string) config('mail.from', ''));
    $fromName = str_replace(["\r", "\n"], '', (string) config('mail.from_name', config('app_name', 'Loja')));

    if (config('debug')) {
        app_log('mail.log', "Para: {$to} | Assunto: {$subject}\n{$body}\n");
    }

    if (!function_exists('mail') || $from === '' || str_contains($from, 'SEU-DOMINIO')) {
        return false;
    }

    $headers = [
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    try {
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    } catch (Throwable $e) {
        error_log('Falha ao enviar e-mail: ' . $e->getMessage());
        return false;
    }
}

/* ---------------------------------------------------------------- upload */

/**
 * Valida e salva uma imagem enviada. Retorna o caminho relativo a public/
 * (ex.: "uploads/abc.jpg"), null se nada foi enviado. Em erro, preenche $error.
 */
function handle_image_upload(string $field, ?string &$error): ?string
{
    $error = null;
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return null;
    }
    $max = (int) config('upload.max_bytes', 2 * 1024 * 1024);
    $maxMb = number_format($max / 1024 / 1024, 0);

    switch ((int) $file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return null;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error = "A imagem passa do limite de {$maxMb} MB.";
            return null;
        default:
            $error = 'Falha no envio da imagem. Tente de novo.';
            return null;
    }

    $tmp = (string) $file['tmp_name'];
    $size = (int) $file['size'];
    if (!is_uploaded_file($tmp)) {
        $error = 'Envio de imagem inválido.';
        return null;
    }
    if ($size <= 0 || $size > $max) {
        $error = "A imagem passa do limite de {$maxMb} MB.";
        return null;
    }

    $info = @getimagesize($tmp);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($types[$info[2]])) {
        $error = 'Envie uma imagem JPG, PNG ou WEBP.';
        return null;
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
        $error = 'A imagem deve ter no máximo 6000 x 6000 pixels.';
        return null;
    }
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $error = 'Envie uma imagem JPG, PNG ou WEBP.';
            return null;
        }
    }

    $dir = APP_ROOT . '/public/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $error = 'A pasta de uploads não existe e não pôde ser criada.';
        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$info[2]];
    if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
        $error = 'Não foi possível salvar a imagem. Verifique a permissão da pasta public/uploads.';
        return null;
    }
    @chmod($dir . '/' . $name, 0644);
    return 'uploads/' . $name;
}

function delete_upload(?string $path): void
{
    if ($path === null || !preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $full = APP_ROOT . '/public/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}
