<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$cartCount = count_row('SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id', [
    'user_id' => (int) $user['id'],
]);
$myLoanBooksStmt = db()->prepare(
    'SELECT b.title
     FROM borrows br
     INNER JOIN books b ON b.id = br.book_id
     WHERE br.user_id = :user_id AND br.status = :status
     ORDER BY br.id DESC
     LIMIT 5'
);

$myLoanBooksStmt->execute([
    'user_id' => (int) $user['id'],
    'status' => 'borrowed',
]);

$myLoanBooks = $myLoanBooksStmt->fetchAll();

db()->exec(
    'CREATE TABLE IF NOT EXISTS announcements (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_by INTEGER DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )'
);

if ($user['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_announcement') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($title === '' || $message === '') {
            set_flash('error', 'Duyuru başlığı ve metni zorunludur.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO announcements (title, message, created_by, is_active)
                 VALUES (:title, :message, :created_by, 1)'
            );
            $stmt->execute([
                'title' => $title,
                'message' => $message,
                'created_by' => (int) $user['id'],
            ]);
            set_flash('success', 'Duyuru başarıyla yayınlandı.');
        }

        redirect('dashboard.php');
    }

    if ($action === 'delete_announcement') {
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);

        if ($announcementId <= 0) {
            set_flash('error', 'Geçersiz duyuru.');
            redirect('dashboard.php');
        }

        $stmt = db()->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $announcementId]);

        set_flash('success', 'Duyuru silindi.');
        redirect('dashboard.php');
    }
}

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

$announcementStmt = db()->query(
    'SELECT a.id, a.title, a.message, a.created_at, u.full_name AS creator_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.created_by
     WHERE a.is_active = 1
     ORDER BY a.id DESC
     LIMIT 5'
);
$announcements = $announcementStmt->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="hero-content">
        <h1>Hoş geldin, <?= e($user['full_name']) ?></h1>
        <?php if ($user['role'] !== 'admin'): ?>
            <div class="hero-balance">
                <img src="<?= e(url('assets/images/pngtree-turkish-lira-icon-turkish-currency-vector-symbol-currency-illustration-cartoon-vector-png-image_44050924-removebg-preview.png')) ?>" alt="Bakiye" class="balance-icon">
                <p class="muted">Bakiyeniz: <strong><?= e(format_money($user['balance'])) ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-3">
    <div class="stats-card">
        <div class="stats-label">Toplam Kitap</div>
        <div class="stats-value" data-target="145">0</div>
    </div>

    <div class="stats-card">
        <div class="stats-label">Toplam Kiralanabilir Stok</div>
        <div class="stats-value" data-target="964">0</div>
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
                <a
                    class="btn btn-success"
                    href="javascript:void(0);"
                    onclick="var panel=document.getElementById('announcement-panel'); if(panel){ panel.style.display = panel.style.display === 'none' ? 'block' : 'none'; }"
                >
                    Duyuru Yap
                </a>
            <?php else: ?>
                <a class="btn btn-secondary books-preview-btn" href="<?= e(url('books.php')) ?>">
    Kitapları Gör

    <span class="book-preview">
        <img src="<?= e(url('assets/uploads/book_69e3a6ca95aba4.93402064.jpg')) ?>" alt="">
        <img src="<?= e(url('assets/uploads/book_69e38a645a4384.56256121.jpg')) ?>" alt="">
        <img src="<?= e(url('assets/uploads/book_69e39990323f64.52843691.jpg')) ?>" alt="">
        <img src="<?= e(url('assets/uploads/book_69e39587d07920.11538023.jpg')) ?>" alt="">
    </span>
</a>
                <a class="btn btn-secondary cart-preview-btn" href="<?= e(url('cart.php')) ?>">
    Sepetim (<?= e($cartCount) ?>)

    <span class="cart-preview">
        <img class="cart-bg" src="<?= e(url('assets/images/sepet.png')) ?>" alt="">
</a>
                <a class="btn btn-secondary loans-preview-btn" href="<?= e(url('my_loans.php')) ?>">
    Kiralamalarım

    <span class="loans-preview">
        <?php if (empty($myLoanBooks)): ?>
            <div class="loan-empty">Kiralık kitabınız bulunmuyor</div>
        <?php else: ?>
            <?php foreach ($myLoanBooks as $loanBook): ?>
                <div class="loan-item"><?= e($loanBook['title']) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </span>
</a>
            <?php endif; ?>
        </div>

        <?php if ($user['role'] === 'admin'): ?>
            <div id="announcement-panel" class="helper-box" style="margin-top:16px; display:none;">
                <strong>Duyuru Yayınla</strong>
                <p class="muted" style="margin-top:8px;">Buradan kısa bir sistem duyurusu paylaşabilirsin. Duyuru sistem özeti içinde silinebilir.</p>

                <form method="post" action="" style="margin-top:12px;">
                    <input type="hidden" name="action" value="create_announcement">

                    <div class="form-group">
                        <label for="announcement_title">Duyuru Başlığı</label>
                        <input class="form-control" id="announcement_title" name="title" type="text" maxlength="255" placeholder="Örn: Yeni teslim kuralları" required>
                    </div>

                    <div class="form-group">
                        <label for="announcement_message">Duyuru Metni</label>
                        <textarea class="form-control" id="announcement_message" name="message" rows="4" placeholder="Kullanıcıların görmesini istediğiniz duyuruyu yazın..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-success" type="submit">Duyuruyu Yayınla</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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

        <div class="helper-box" style="margin-top:16px;">
            <strong>Son Duyurular</strong>

            <?php if (!$announcements): ?>
                <p class="muted" style="margin:10px 0 0 0;">Henüz yayınlanmış duyuru yok.</p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div style="padding:12px 0; border-bottom:1px solid rgba(0,0,0,.08);">
                        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                            <div style="font-weight:700;"><?= e($announcement['title']) ?></div>

                            <?php if ($user['role'] === 'admin'): ?>
                                <form method="post" action="" onsubmit="return confirm('Bu duyuru silinsin mi?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= e((string) $announcement['id']) ?>">
                                    <button class="btn btn-danger" type="submit">Sil</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <p class="muted" style="margin:8px 0 6px 0;"><?= nl2br(e($announcement['message'])) ?></p>
                        <div class="small muted">
                            <?= e($announcement['creator_name'] ?: 'Yönetim') ?> · <?= e($announcement['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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






<script>
document.addEventListener("DOMContentLoaded", () => {
    const counters = document.querySelectorAll(".stats-value");

    counters.forEach(counter => {
        const target = +counter.getAttribute("data-target");
        let count = 0;

        const duration = 1500;
        const startTime = performance.now();

        const animate = (time) => {
            const progress = (time - startTime) / duration;
            const value = Math.min(progress * target, target);
            counter.textContent = Math.floor(value);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                counter.textContent = target;
            }
        };

        requestAnimationFrame(animate);
    });
});
</script>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
