<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$loginSide = login_side_data('login');

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
        <?= render_login_side('login') ?>

        <?= render_login_panel('login', $error) ?>
    </div>
</div>
</body>
</html>
