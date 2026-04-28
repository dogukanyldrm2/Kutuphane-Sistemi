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

function login_side_data(string $page = 'login'): array
{
    $loginSideContent = [
        'login' => [
            'badge' => APP_NAME,
            'title' => 'Kütüphane Yönetimi',
            'text' => 'Kitapları takip et, ödünç işlemlerini kolayca yönet ve hesabına hızlıca eriş.',
            'features' => [
                [
                    'title' => 'Hızlı erişim',
                    'text' => 'Giriş yaptıktan sonra panel, kitaplar ve hesap bölümlerine doğrudan ulaşabilirsin.',
                ],
                [
                    'title' => 'Kolay takip',
                    'text' => 'Ödünç alınan kitaplar, teslim tarihleri ve kişisel işlemler tek ekranda düzenli görünür.',
                ],
            ],
            'stats' => [
                ['value' => '7/24', 'label' => 'erişim'],
                ['value' => 'Güvenli', 'label' => 'oturum'],
                ['value' => 'Kolay', 'label' => 'kullanım'],
            ],
        ],
        'register' => [
            'badge' => APP_NAME,
            'title' => 'Üye Kaydı',
            'text' => 'Kütüphaneye hızlıca katıl, kitapları takip et ve hesabını güvenle yönet.',
            'features' => [
                [
                    'title' => 'Kolay kayıt',
                    'text' => 'Hızlı form ile üyeliğini hemen aktif edebilirsin.',
                ],
                [
                    'title' => 'Güvenli oturum',
                    'text' => 'Kişisel bilgilerin güvende tutulur ve hesap girişin korunur.',
                ],
            ],
            'stats' => [
                ['value' => '5 adım', 'label' => 'kayıt'],
                ['value' => 'Güvenli', 'label' => 'hesap'],
                ['value' => 'Hızlı', 'label' => 'başlangıç'],
            ],
        ],
    ];

    return $loginSideContent[$page] ?? $loginSideContent['login'];
}

function render_login_side(string $page = 'login'): string
{
    $loginSide = login_side_data($page);

    $html = '<div class="login-side">';
    $html .= '<div class="login-side-content">';
    $html .= '<div>'; 
    $html .= '<div class="login-badge">' . e($loginSide['badge']) . '</div>';
    $html .= '<h1 class="login-side-title">' . e($loginSide['title']) . '</h1>';
    $html .= '<p class="login-side-text">' . e($loginSide['text']) . '</p>';
    $html .= '</div>';

    $html .= '<div class="login-feature-grid">';
    foreach ($loginSide['features'] as $feature) {
        $html .= '<div class="login-feature-card">';
        $html .= '<strong>' . e($feature['title']) . '</strong>';
        $html .= '<span>' . e($feature['text']) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div class="login-stat-grid">';
    foreach ($loginSide['stats'] as $stat) {
        $html .= '<div class="login-stat-box">';
        $html .= '<div class="login-stat-value">' . e($stat['value']) . '</div>';
        $html .= '<div class="login-stat-label">' . e($stat['label']) . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function login_panel_data(string $page = 'login'): array
{
    $loginPanelContent = [
        'login' => [
            'title' => 'Giriş Yap',
            'text' => 'Site açıldıgında önce giriş ekrani gelir. Beni Hatırla seçeneği sayesinde tekrar giriş yapmanıza gerek kalmaz!',
            'form_action' => '',
            'fields' => [
                [
                    'name' => 'identity',
                    'label' => 'Kullanıcı adı veya e-posta',
                    'type' => 'text',
                    'required' => true,
                    'value' => $_POST['identity'] ?? '',
                ],
                [
                    'name' => 'password',
                    'label' => 'Şifre',
                    'type' => 'password',
                    'required' => true,
                ],
            ],
            'checkbox' => [
                'name' => 'remember',
                'label' => 'Beni hatırla (30 gün)',
                'checked' => true,
            ],
            'buttons' => [
                [
                    'type' => 'submit',
                    'text' => 'Giriş Yap',
                    'class' => '',
                ],
                [
                    'href' => url('register.php'),
                    'text' => 'Yeni Hesap Oluştur',
                    'class' => 'btn-secondary',
                ],
            ],
        ],
        'register' => [
            'title' => 'Kayıt Ol',
            'text' => 'Kütüphane sistemine üye hesabını oluştur.',
            'form_action' => '',
            'fields' => [
                [
                    'name' => 'full_name',
                    'label' => 'Ad Soyad',
                    'type' => 'text',
                    'required' => true,
                    'value' => $_POST['full_name'] ?? '',
                ],
                [
                    'name' => 'username',
                    'label' => 'Kullanıcı Adı',
                    'type' => 'text',
                    'required' => true,
                    'value' => $_POST['username'] ?? '',
                ],
                [
                    'name' => 'email',
                    'label' => 'E-posta',
                    'type' => 'email',
                    'required' => true,
                    'value' => $_POST['email'] ?? '',
                ],
                [
                    'name' => 'phone',
                    'label' => 'Telefon Numarası',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Örn: 05051234567',
                    'value' => $_POST['phone'] ?? '',
                ],
                [
                    'name' => 'address',
                    'label' => 'Adres',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Adresinizi girin',
                    'value' => $_POST['address'] ?? '',
                ],
                [
                    'name' => 'password',
                    'label' => 'Şifre',
                    'type' => 'password',
                    'required' => true,
                ],
                [
                    'name' => 'password_confirm',
                    'label' => 'Şifre Tekrar',
                    'type' => 'password',
                    'required' => true,
                ],
            ],
            'buttons' => [
                [
                    'type' => 'submit',
                    'text' => 'Hesap Oluştur',
                    'class' => '',
                ],
                [
                    'href' => url('login.php'),
                    'text' => 'Giriş Sayfasına Dön',
                    'class' => 'btn-secondary',
                ],
            ],
        ],
    ];

    return $loginPanelContent[$page] ?? $loginPanelContent['login'];
}

function render_login_panel(string $page = 'login', string $error = ''): string
{
    $panelData = login_panel_data($page);

    $html = '<div class="login-panel">';
    $html .= '<div class="login-panel-inner">';

    $html .= display_flash();

    $html .= '<h1>' . e($panelData['title']) . '</h1>';
    $html .= '<p class="muted">' . e($panelData['text']) . '</p>';

    if ($error !== '') {
        $html .= '<div class="flash flash-error">' . e($error) . '</div>';
    }

    $html .= '<form method="post" action="' . e($panelData['form_action']) . '">';

    // Handle different field layouts
    if ($page === 'register') {
        // Single fields
        foreach ($panelData['fields'] as $field) {
            if (in_array($field['name'], ['full_name', 'username', 'email'])) {
                $html .= '<div class="form-group">';
                $html .= '<label for="' . e($field['name']) . '">' . e($field['label']) . '</label>';
                $html .= '<input class="form-control" id="' . e($field['name']) . '" name="' . e($field['name']) . '" type="' . e($field['type']) . '"';
                if (isset($field['value'])) $html .= ' value="' . e($field['value']) . '"';
                if (isset($field['placeholder'])) $html .= ' placeholder="' . e($field['placeholder']) . '"';
                if ($field['required']) $html .= ' required';
                $html .= '>';
                $html .= '</div>';
            }
        }

        // Row fields
        $html .= '<div class="form-row">';
        foreach ($panelData['fields'] as $field) {
            if (in_array($field['name'], ['phone', 'address'])) {
                $html .= '<div class="form-group">';
                $html .= '<label for="' . e($field['name']) . '">' . e($field['label']) . '</label>';
                $html .= '<input class="form-control" id="' . e($field['name']) . '" name="' . e($field['name']) . '" type="' . e($field['type']) . '"';
                if (isset($field['value'])) $html .= ' value="' . e($field['value']) . '"';
                if (isset($field['placeholder'])) $html .= ' placeholder="' . e($field['placeholder']) . '"';
                if ($field['required']) $html .= ' required';
                $html .= '>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        $html .= '<div class="form-row">';
        foreach ($panelData['fields'] as $field) {
            if (in_array($field['name'], ['password', 'password_confirm'])) {
                $html .= '<div class="form-group">';
                $html .= '<label for="' . e($field['name']) . '">' . e($field['label']) . '</label>';
                $html .= '<input class="form-control" id="' . e($field['name']) . '" name="' . e($field['name']) . '" type="' . e($field['type']) . '"';
                if ($field['required']) $html .= ' required';
                $html .= '>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';
    } else {
        // Login fields
        foreach ($panelData['fields'] as $field) {
            $html .= '<div class="form-group">';
            $html .= '<label for="' . e($field['name']) . '">' . e($field['label']) . '</label>';
            $html .= '<input class="form-control" id="' . e($field['name']) . '" name="' . e($field['name']) . '" type="' . e($field['type']) . '"';
            if (isset($field['value'])) $html .= ' value="' . e($field['value']) . '"';
            if ($field['required']) $html .= ' required';
            $html .= '>';
            $html .= '</div>';
        }

        if (isset($panelData['checkbox'])) {
            $html .= '<label class="checkbox-line small">';
            $html .= '<input type="checkbox" name="' . e($panelData['checkbox']['name']) . '"';
            if ($panelData['checkbox']['checked']) $html .= ' checked';
            $html .= '>';
            $html .= e($panelData['checkbox']['label']);
            $html .= '</label>';
        }
    }

    $html .= '<div class="form-actions">';
    foreach ($panelData['buttons'] as $button) {
        if (isset($button['type']) && $button['type'] === 'submit') {
            $html .= '<button type="submit"';
            if ($button['class'] !== '') $html .= ' class="' . e($button['class']) . '"';
            $html .= '>' . e($button['text']) . '</button>';
        } else {
            $html .= '<a class="btn';
            if ($button['class'] !== '') $html .= ' ' . e($button['class']);
            $html .= '" href="' . e($button['href']) . '">' . e($button['text']) . '</a>';
        }
    }
    $html .= '</div>';

    $html .= '</form>';

    $html .= '</div>';
    $html .= '</div>';

    return $html;
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
