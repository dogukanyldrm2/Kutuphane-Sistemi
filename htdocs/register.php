<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

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
        <div class="login-side">
            <h1>Yeni Kullanıcı Kaydı</h1>
            <p>Her üye kendi hesabı ile sisteme girsin diye kayıt formu da eklendi.</p>
            <ul>
                <li>Ad soyad</li>
                <li>Kullanıcı adı</li>
                <li>E-posta</li>
                <li>Telefon numarası</li>
                <li>Adres bilgisi</li>
                <li>Güvenli şifre</li>
            </ul>
        </div>

        <div>
            <h1>Kayıt Ol</h1>
            <p class="muted">Kütüphane sistemine üye hesabını oluştur.</p>

            <?php if ($error !== ''): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="full_name">Ad Soyad</label>
                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Kullanıcı Adı</label>
                        <input class="form-control" id="username" name="username" type="text" value="<?= e($_POST['username'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefon Numarası</label>
                        <input class="form-control" id="phone" name="phone" type="text" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="Örn: 05051234567" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Adres</label>
                        <input class="form-control" id="address" name="address" type="text" value="<?= e($_POST['address'] ?? '') ?>" placeholder="Adresinizi girin" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Şifre</label>
                        <input class="form-control" id="password" name="password" type="password" required>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Şifre Tekrar</label>
                        <input class="form-control" id="password_confirm" name="password_confirm" type="password" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit">Hesap Oluştur</button>
                    <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>">Giriş Sayfasına Dön</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
