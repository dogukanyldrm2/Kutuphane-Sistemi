<?php
declare(strict_types=1);

$user = current_user();
$cartCount = 0;

if ($user && ($user['role'] ?? '') !== 'admin') {
    $cartCount = current_cart_count((int) $user['id']);
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=5')) ?>">
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div class="brand">
            <a href="<?= e(url(is_logged_in() ? 'dashboard.php' : 'login.php')) ?>">Kütüphane Sistemi</a>
        </div>

        <?php if ($user): ?>
            <form class="top-search" action="<?= e(url('books.php')) ?>" method="get">
                <input
                    type="text"
                    name="q"
                    value="<?= e($_GET['q'] ?? '') ?>"
                    placeholder="Kitap, yazar veya ISBN ara..."
                >
                <button type="submit">Ara</button>
            </form>

            <nav class="nav-links">
                <a href="<?= e(url('dashboard.php')) ?>">Panel</a>
                <a href="<?= e(url('books.php')) ?>">Kitaplar</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('add_book.php')) ?>">Kitap Ekle</a>
                <?php endif; ?>
                <?php if ($user['role'] !== 'admin'): ?>
                    <a href="<?= e(url('my_loans.php')) ?>">Kiraladıklarım</a>
                <?php endif; ?>
                <?php if ($user['role'] !== 'admin'): ?>
                    <a href="<?= e(url('account.php')) ?>">Hesabım</a>
                <?php endif; ?>
                <?php if ($user['role'] !== 'admin'): ?>
                    <a href="<?= e(url('cart.php')) ?>">Sepetim (<?= e($cartCount) ?>)</a>
                <?php endif; ?>
                <a href="<?= e(url('logout.php')) ?>">Çıkış</a>
            </nav>

            <div class="user-chip">
                <?= e($user['full_name']) ?> - <?= $user['role'] === 'admin' ? 'Admin' : 'üye' ?>
            </div>
        <?php endif; ?>
    </header>

    <main class="page-wrapper">
        <?= display_flash() ?>
