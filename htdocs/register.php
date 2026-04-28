<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$loginSide = login_side_data('register');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = mb_strtoupper(trim($_POST['full_name'] ?? ''), 'UTF-8');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($fullName === '' || $username === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
        $error = 'Tüm alanlar zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi gir.';
    } elseif (strlen($phone) < 10) {
        $error = 'Geçerli bir telefon numarası gir.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler aynı değil.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
        $stmt->execute([
            'username' => $username,
            'email' => $email,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            $error = 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO users (full_name, username, email, phone, address, password_hash, role)
                 VALUES (:full_name, :username, :email, :phone, :address, :password_hash, :role)'
            );

            $stmt->execute([
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'member',
            ]);

            set_flash('success', 'Hesap oluşturuldu. Şimdi giriş yapabilirsiniz!');
            redirect('login.php');
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> - Kayıt</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <?= render_login_side('register') ?>

        <?= render_login_panel('register', $error) ?>
    </div>
</div>
</body>
</html>
