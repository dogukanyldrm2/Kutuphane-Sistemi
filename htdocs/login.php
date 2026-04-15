<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($identity === '' || $password === '') {
        $error = 'Kullanici adi veya e-posta ile sifre zorunludur.';
    } elseif (attempt_login($identity, $password, $remember)) {
        set_flash('success', 'Hoş geldin, giriş başarılı.');
        redirect('dashboard.php');
    } else {
        $error = 'Giriş bilgileri hatali.';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> - Giriş</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <style>
        @media (max-width: 768px) {
            .login-side-content {
                gap: 14px !important;
            }

            .login-side-title {
                font-size: 28px;
                line-height: 1.2;
            }

            .login-side-text {
                max-width: 100% !important;
                font-size: 14px;
                line-height: 1.5 !important;
            }

            .login-feature-grid {
                gap: 10px !important;
            }

            .login-stat-grid {
                grid-template-columns: 1fr !important;
            }

            .login-stat-box {
                padding: 10px 12px !important;
            }

            .login-stat-value {
                font-size: 18px !important;
                line-height: 1.2;
                word-break: break-word;
            }

            .login-stat-label {
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-side">
            <div class="login-side-content" style="display:flex; flex-direction:column; gap:18px; height:100%; justify-content:center;">
                <div>
                    <div style="display:inline-block; padding:6px 12px; border-radius:999px; background:rgba(255,255,255,.16); color:#fff; font-size:12px; letter-spacing:.4px; margin-bottom:14px;">
                        Dijital Kütüphane
                    </div>
                    <h1 class="login-side-title" style="margin:0 0 10px;">Kütüphane Yönetimi</h1>
                    <p class="login-side-text" style="margin:0; color:rgba(255,255,255,.88); line-height:1.6; max-width:460px;">
                        Kitapları takip et, ödünç işlemlerini kolayca yönet ve hesabına hızlıca eriş.
                    </p>
                </div>

                <div class="login-feature-grid" style="display:grid; gap:12px;">
                    <div style="background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.15); border-radius:16px; padding:14px 16px; backdrop-filter:blur(3px);">
                        <strong style="display:block; margin-bottom:6px; color:#fff;">Hızlı erişim</strong>
                        <span style="color:rgba(255,255,255,.82); font-size:14px;">Giriş yaptıktan sonra panel, kitaplar ve hesap bölümlerine doğrudan ulaşabilirsin.</span>
                    </div>

                    <div style="background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.15); border-radius:16px; padding:14px 16px; backdrop-filter:blur(3px);">
                        <strong style="display:block; margin-bottom:6px; color:#fff;">Kolay takip</strong>
                        <span style="color:rgba(255,255,255,.82); font-size:14px;">Ödünç alınan kitaplar, teslim tarihleri ve kişisel işlemler tek ekranda düzenli görünür.</span>
                    </div>

                    <div class="login-stat-grid" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px;">
                        <div class="login-stat-box" style="background:rgba(255,255,255,.12); border-radius:14px; padding:12px; text-align:center; border:1px solid rgba(255,255,255,.14);">
                            <div class="login-stat-value" style="font-size:22px; font-weight:700; color:#fff;">7/24</div>
                            <div class="login-stat-label" style="font-size:12px; color:rgba(255,255,255,.82);">erişim</div>
                        </div>
                        <div class="login-stat-box" style="background:rgba(255,255,255,.12); border-radius:14px; padding:12px; text-align:center; border:1px solid rgba(255,255,255,.14);">
                            <div class="login-stat-value" style="font-size:22px; font-weight:700; color:#fff;">Güvenli</div>
                            <div class="login-stat-label" style="font-size:12px; color:rgba(255,255,255,.82);">oturum</div>
                        </div>
                        <div class="login-stat-box" style="background:rgba(255,255,255,.12); border-radius:14px; padding:12px; text-align:center; border:1px solid rgba(255,255,255,.14);">
                            <div class="login-stat-value" style="font-size:22px; font-weight:700; color:#fff;">Kolay</div>
                            <div class="login-stat-label" style="font-size:12px; color:rgba(255,255,255,.82);">kullanım</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <?= display_flash() ?>

            <h1>Giriş Yap</h1>
            <p class="muted">Site açıldıgında önce giriş ekrani gelir. Beni Hatırla seçeneği sayesinde tekrar giriş yapmanıza gerek kalmaz!</p>

            <?php if ($error !== ''): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="identity">Kullanıcı adı veya e-posta</label>
                    <input class="form-control" id="identity" name="identity" type="text" value="<?= e($_POST['identity'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input class="form-control" id="password" name="password" type="password" required>
                </div>

                <label class="checkbox-line small">
                    <input type="checkbox" name="remember" checked>
                    Beni hatırla (30 gün)
                </label>

                <div class="form-actions">
                    <button type="submit">Giriş Yap</button>
                    <a class="btn btn-secondary" href="<?= e(url('register.php')) ?>">Yeni Hesap Oluştur</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
