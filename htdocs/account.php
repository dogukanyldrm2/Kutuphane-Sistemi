<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';
$balanceError = '';
$balanceSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($fullName === '' || $username === '' || $email === '') {
            $profileError = 'Ad soyad, kullanıcı adı ve e-posta zorunludur.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Geçerli bir e-posta girin.';
        } else {
            $checkStmt = db()->prepare('SELECT id FROM users WHERE (username = :username OR email = :email) AND id <> :id LIMIT 1');
            $checkStmt->execute([
                'username' => $username,
                'email' => $email,
                'id' => (int) $user['id'],
            ]);

            if ($checkStmt->fetch()) {
                $profileError = 'Bu kullanıcı adı veya e-posta başka bir hesapta kullanılıyor.';
            } else {
                $stmt = db()->prepare('UPDATE users
                    SET full_name = :full_name,
                        username = :username,
                        email = :email,
                        phone = :phone,
                        address = :address
                    WHERE id = :id');
                $stmt->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'id' => (int) $user['id'],
                ]);

                $profileSuccess = 'Hesap bilgileriniz başarıyla güncellendi.';
                $user = current_user();
            }
        }
    }

    if ($action === 'update_balance') {
        $amount = round((float) ($_POST['amount'] ?? 0), 2);

        if ($amount <= 0) {
            $balanceError = 'Lütfen 0 dan büyük bir tutar girin.';
        } else {
            $stmt = db()->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id');
            $stmt->execute([
                'amount' => $amount,
                'id' => (int) $user['id'],
            ]);

            $balanceSuccess = 'Bakiyeniz başarıyla güncellendi.';
            $user = current_user();
        }
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $passwordError = 'Mevcut şifreniz yanlış.';
        } elseif (strlen($newPassword) < 6) {
            $passwordError = 'Yeni şifre en az 6 karakter olmalıdır.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'Yeni şifreler eşleşmiyor.';
        } else {
            $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => (int) $user['id'],
            ]);
            $passwordSuccess = 'Şifreniz başarıyla güncellendi.';
            $user = current_user();
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Hesabım</h1>
    <p class="muted">Profil bilgilerinizi, şifrenizi ve bakiyenizi buradan yönetebilirsiniz.</p>
</section>

<section class="grid grid-2">
    <div class="form-card">
        <h2>Hesap Bilgileri</h2>

        <p class="muted">
            Rol: <strong><?= e($user['role'] === 'member' ? 'Üye' : ($user['role'] === 'admin' ? 'Admin' : $user['role'])) ?></strong>
        </p>

        <?php if ($profileError !== ''): ?>
            <div class="flash flash-error"><?= e($profileError) ?></div>
        <?php endif; ?>

        <?php if ($profileSuccess !== ''): ?>
            <div class="flash flash-success"><?= e($profileSuccess) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="full_name">Ad Soyad</label>
                <input class="form-control" id="full_name" name="full_name" type="text" value="<?= e($user['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input class="form-control" id="username" name="username" type="text" value="<?= e($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">E-posta</label>
                <input class="form-control" id="email" name="email" type="email" value="<?= e($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Telefon</label>
                <input class="form-control" id="phone" name="phone" type="text" value="<?= e($user['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address">Adres</label>
                <textarea class="form-control" id="address" name="address" rows="4"><?= e($user['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit">Bilgileri Güncelle</button>
            </div>
        </form>

        <hr style="margin: 24px 0; border: 0; border-top: 1px solid #e5e7eb;">

        <h3>Şifre Güncelle</h3>

        <?php if ($passwordError !== ''): ?>
            <div class="flash flash-error"><?= e($passwordError) ?></div>
        <?php endif; ?>

        <?php if ($passwordSuccess !== ''): ?>
            <div class="flash flash-success"><?= e($passwordSuccess) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="current_password">Mevcut Şifre</label>
                <input class="form-control" id="current_password" name="current_password" type="password" required>
            </div>

            <div class="form-group">
                <label for="new_password">Yeni Şifre</label>
                <input class="form-control" id="new_password" name="new_password" type="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Yeni Şifre Tekrar</label>
                <input class="form-control" id="confirm_password" name="confirm_password" type="password" required>
            </div>

            <div class="form-actions">
                <button type="submit">Şifreyi Güncelle</button>
            </div>
        </form>
    </div>

    <div class="form-card">
        <h2>Bakiye İşlemleri</h2>

        <p class="muted">
            Güncel Bakiye: <strong><?= e(format_money($user['balance'])) ?></strong>
        </p>

        <?php if ($balanceError !== ''): ?>
            <div class="flash flash-error"><?= e($balanceError) ?></div>
        <?php endif; ?>

        <?php if ($balanceSuccess !== ''): ?>
            <div class="flash flash-success"><?= e($balanceSuccess) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="action" value="update_balance">

            <div class="form-group">
                <label for="amount">Bakiye Güncelle</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0" step="0.01" placeholder="50" required>
            </div>

            <div class="form-actions">
                <button type="submit">Bakiye Ekle</button>
            </div>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
