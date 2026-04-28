<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_admin();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $currentUser = current_user();

        if ($userId <= 0) {
            set_flash('error', 'Geçersiz kullanıcı.');
            redirect('users.php');
        }

        $stmt = db()->prepare('SELECT id, full_name, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            set_flash('error', 'Kullanıcı bulunamadı.');
            redirect('users.php');
        }

        if ((int) $targetUser['id'] === (int) ($currentUser['id'] ?? 0)) {
            set_flash('error', 'Kendi hesabınızı silemezsiniz.');
            redirect('users.php');
        }

        if (($targetUser['role'] ?? '') === 'admin') {
            set_flash('error', 'Admin kullanıcı silinemez.');
            redirect('users.php');
        }

        $deleteStmt = db()->prepare('DELETE FROM users WHERE id = :id');
        $deleteStmt->execute(['id' => $userId]);

        set_flash('success', e($targetUser['full_name']) . ' adlı kullanıcı silindi.');
        redirect('users.php');
    }
}

$users = db()->query(
    'SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.phone,
        u.address,
        u.role,
        u.balance,
        u.created_at,
        COUNT(CASE WHEN br.status = "borrowed" THEN 1 END) AS active_loans,
        COUNT(br.id) AS total_loans
     FROM users u
     LEFT JOIN borrows br ON br.user_id = u.id
     WHERE u.role <> "admin"
     GROUP BY u.id
     ORDER BY u.full_name ASC'
)->fetchAll();

$loanRows = db()->query(
    'SELECT
        u.full_name,
        u.username,
        b.title,
        br.borrow_date,
        br.due_date,
        br.returned_at,
        br.rental_price,
        br.status
     FROM borrows br
     INNER JOIN users u ON u.id = br.user_id
     INNER JOIN books b ON b.id = br.book_id
     WHERE u.role <> "admin"
     ORDER BY br.id DESC
     LIMIT 100'
)->fetchAll();

$announcements = db()->query(
    'SELECT a.title, a.message, a.created_at, u.full_name AS creator_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.created_by
     WHERE a.is_active = 1
     ORDER BY a.id DESC
     LIMIT 5'
)->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kullanıcı Yönetimi</h1>
    <p class="muted">Sadece üye kullanıcıları, bakiyelerini ve aldıkları kitapları buradan görebilirsiniz.</p>
</section>

<section class="table-card" style="margin-bottom:20px;">
    <div class="section-title">
        <h2>Yayınlanan Duyurular</h2>
    </div>

    <?php if (!$announcements): ?>
        <div class="empty-state">Henüz yayınlanmış duyuru bulunmuyor.</div>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach ($announcements as $announcement): ?>
                <div class="card" style="margin:0;">
                    <h3 style="margin-top:0;"><?= e($announcement['title']) ?></h3>
                    <p class="muted" style="white-space:pre-line;"><?= e($announcement['message']) ?></p>
                    <div class="small muted">
                        <?= e($announcement['creator_name'] ?: 'Yönetim') ?> · <?= e($announcement['created_at']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="table-card">
    <div class="section-title">
        <h2>Kullanıcılar</h2>
    </div>
    <table>
        <thead>
        <tr>
            <th>Ad Soyad</th>
            <th>Kullanıcı</th>
            <th>E-posta</th>
            <th>Telefon</th>
            <th>Adres</th>
            <th>Bakiye</th>
            <th>Aktif</th>
            <th>Toplam</th>
            <th>İşlemler</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <?php $isProtectedUser = (int) $row['id'] === (int) (current_user()['id'] ?? 0); ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['username']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><?= e($row['phone']) ?></td>
                <td><?= e($row['address']) ?></td>
                <td><?= e(format_money($row['balance'])) ?></td>
                <td><?= e($row['active_loans']) ?></td>
                <td><?= e($row['total_loans']) ?></td>
                <td>
                    <?php if ($isProtectedUser): ?>
                        <span class="muted small">Silinemez</span>
                    <?php else: ?>
                        <form method="post" action="" onsubmit="return confirm('<?= e($row['full_name']) ?> adlı kullanıcı silinsin mi?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= e($row['id']) ?>">
                            <button class="btn btn-danger" type="submit">Sil</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$users): ?>
            <tr>
                <td colspan="9" class="muted">Gösterilecek üye kullanıcı bulunamadı.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="table-card" style="margin-top:20px;">
    <div class="section-title">
        <h2>Kiralama Detayları</h2>
    </div>
    <table>
        <thead>
        <tr>
            <th>Kullanıcı</th>
            <th>Kitap</th>
            <th>Alış</th>
            <th>Teslim</th>
            <th>İade</th>
            <th>Ücret</th>
            <th>Durum</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($loanRows as $loan): ?>
            <tr>
                <td><?= e($loan['full_name']) ?> (<?= e($loan['username']) ?>)</td>
                <td><?= e($loan['title']) ?></td>
                <td><?= e($loan['borrow_date']) ?></td>
                <td><?= e($loan['due_date']) ?></td>
                <td><?= e($loan['returned_at'] ?: '-') ?></td>
                <td><?= e(format_money($loan['rental_price'])) ?></td>
                <td><?= e($loan['status'] === 'borrowed' ? 'Kullanıcıda' : 'İade Edildi') ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$loanRows): ?>
            <tr>
                <td colspan="7" class="muted">Admin dışındaki kullanıcılar için kiralama kaydı bulunamadı.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
