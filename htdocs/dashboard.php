<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

$totalBooks = count_row('SELECT COUNT(*) FROM books');
$totalCopies = count_row('SELECT COALESCE(SUM(total_copies), 0) FROM books');
$availableCopies = count_row('SELECT COALESCE(SUM(available_copies), 0) FROM books');
$totalUsers = count_row('SELECT COUNT(*) FROM users');
$myActiveLoans = count_row('SELECT COUNT(*) FROM borrows WHERE user_id = :user_id AND status = :status', [
    'user_id' => (int) $user['id'],
    'status' => 'borrowed',
]);
$cartCount = count_row('SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id', [
    'user_id' => (int) $user['id'],
]);

$recentBooksStmt = db()->query(
    'SELECT b.title, b.price, a.name AS author_name, c.name AS category_name
     FROM books b
     INNER JOIN authors a ON a.id = b.author_id
     INNER JOIN categories c ON c.id = b.category_id
     ORDER BY b.id DESC
     LIMIT 6'
);
$recentBooks = $recentBooksStmt->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Hoş geldin, <?= e($user['full_name']) ?></h1>
    <?php if ($user['role'] !== 'admin'): ?>
        <p class="muted">Bakiyeniz: <strong><?= e(format_money($user['balance'])) ?></strong></p>
    <?php endif; ?>
</section>

<section class="grid grid-3">
    <div class="stats-card">
        <div class="stats-label">Toplam Kitap</div>
        <div class="stats-value"><?= e($totalBooks) ?></div>
    </div>

    <div class="stats-card">
        <div class="stats-label">Toplam Kiralanabilir Stok</div>
        <div class="stats-value"><?= e($availableCopies) ?></div>
    </div>

    <div class="stats-card">
        <div class="stats-label"><?= $user['role'] === 'admin' ? 'Toplam Kullanıcı' : 'Aktif Kiraladıklarım' ?></div>
        <div class="stats-value"><?= e($user['role'] === 'admin' ? $totalUsers : $myActiveLoans) ?></div>
    </div>
</section>

<section class="grid grid-2" style="margin-top: 20px;">
    <div class="card">
        <h2>Hızlı İşlem</h2>

        <div class="form-actions">
            <?php if ($user['role'] === 'admin'): ?>
                <a class="btn btn-success" href="<?= e(url('add_book.php')) ?>">Yeni Kitap Ekle</a>
                <a class="btn btn-success" href="<?= e(url('users.php')) ?>">Kullanıcıları Yönet</a>
            <?php else: ?>
                <a class="btn" href="<?= e(url('books.php')) ?>">Kitapları Gör</a>
                <a class="btn btn-secondary" href="<?= e(url('cart.php')) ?>">Sepetim (<?= e($cartCount) ?>)</a>
                <a class="btn btn-secondary" href="<?= e(url('my_loans.php')) ?>">Kiralamalarım</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2>Sistem Özeti</h2>
        <p class="muted">
            <?php if ($user['role'] === 'admin'): ?>
                Kullanıcı sayısı: <strong><?= e($totalUsers) ?></strong><br>
            <?php endif; ?>
            Rolünüz: <strong><?= e($user['role'] === 'admin' ? 'Admin' : 'Üye') ?></strong><br>
            <?php if ($user['role'] === 'admin'): ?>
                Yönetim modu: <strong>Aktif</strong><br>
                Toplam Stok: <strong><?= e($totalCopies) ?></strong><br>

            <?php else: ?>
                Sepetteki kitap: <strong><?= e($cartCount) ?></strong><br>
                Giriş durumu: <strong>Aktif</strong><br>
            <?php endif; ?>
        </p>
    </div>
</section>

<section class="table-card" style="margin-top: 20px;">
    <div class="section-title">
        <h2>Son Eklenen Kitaplar</h2>
        <a class="btn btn-secondary" href="<?= e(url('books.php')) ?>">Tümünü Gör</a>
    </div>

    <?php if (!$recentBooks): ?>
        <div class="empty-state">Henüz kitap bulunmuyor.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Kitap</th>
                <th>Yazar</th>
                <th>Kategori</th>
                <th>Fiyat</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentBooks as $book): ?>
                <tr>
                    <td><?= e($book['title']) ?></td>
                    <td><?= e($book['author_name']) ?></td>
                    <td><?= e($book['category_name']) ?></td>
                    <td><?= e(format_money($book['price'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
