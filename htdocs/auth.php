<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function url(string $path = ''): string
{
    $prefix = BASE_URL !== '' ? BASE_URL : '';

    if ($path === '') {
        return $prefix !== '' ? $prefix : '/';
    }

    return $prefix . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function display_flash(): string
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return '<div class="flash flash-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

function format_money(float|int|string $amount): string
{
    return number_format((float) $amount, 2, ',', '.') . ' TL';
}

function login_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
}

function remember_user(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresTimestamp = time() + (REMEMBER_COOKIE_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);

    $stmt = db()->prepare('UPDATE users SET remember_token_hash = :token_hash, remember_token_expires_at = :expires_at WHERE id = :id');
    $stmt->execute([
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'id' => $userId,
    ]);

    setcookie(
        REMEMBER_COOKIE_NAME,
        $token,
        [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'httponly' => true,
            'secure' => false,
            'samesite' => 'Lax',
        ]
    );
}

function clear_remember_cookie(): void
{
    setcookie(
        REMEMBER_COOKIE_NAME,
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => false,
            'samesite' => 'Lax',
        ]
    );
}

function current_user(): ?array
{
    static $cachedUser = null;
    static $checked = false;

    if ($checked) {
        return $cachedUser;
    }

    $checked = true;

    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $cachedUser = $stmt->fetch() ?: null;

        if ($cachedUser !== null) {
            return $cachedUser;
        }

        unset($_SESSION['user_id'], $_SESSION['user_role']);
    }

    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $token = $_COOKIE[REMEMBER_COOKIE_NAME];

        if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            $stmt = db()->prepare('SELECT * FROM users WHERE remember_token_hash = :token_hash AND remember_token_expires_at > NOW() LIMIT 1');
            $stmt->execute([
                'token_hash' => hash('sha256', $token),
            ]);

            $user = $stmt->fetch();

            if ($user) {
                login_session($user);
                $cachedUser = $user;

                return $cachedUser;
            }
        }

        clear_remember_cookie();
    }

    return null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Devam etmek için giriş yapın.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    $user = current_user();

    if (!$user || $user['role'] !== 'admin') {
        set_flash('error', 'Bu sayfaya sadece admin erişebilir.');
        redirect('dashboard.php');
    }
}

function attempt_login(string $identity, string $password, bool $remember = true): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :identity OR email = :identity LIMIT 1');
    $stmt->execute(['identity' => $identity]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    login_session($user);

    if ($remember) {
        remember_user((int) $user['id']);
    }

    return true;
}

function logout_user(): void
{
    $user = current_user();

    if ($user) {
        $stmt = db()->prepare('UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = :id');
        $stmt->execute(['id' => (int) $user['id']]);
    }

    $_SESSION = [];

    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    clear_remember_cookie();
    session_destroy();
}

function count_row(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function current_cart_count(int $userId): int
{
    return count_row('SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id', ['user_id' => $userId]);
}
