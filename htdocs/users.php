<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_admin();

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
     GROUP BY u.id
     ORDER BY u.role DESC, u.full_name ASC'
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
     ORDER BY br.id DESC
     LIMIT 100'
)->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kullanıcı Yönetimi</h1>
    <p class="muted">Tüm kullanıcıları, bakiyelerini ve aldıkları kitapları buradan görebilirsiniz.</p>
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
            <th>Rol</th>
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
            <?php
            $isProtectedUser = $row['role'] === 'admin' || (int) $row['id'] === (int) (current_user()['id'] ?? 0);
            ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['username']) ?></td>
                <td><?= e($row['role'] === 'admin' ? 'Admin' : 'Üye') ?></td>
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
                <td>
                    <?= e($loan['status'] === 'borrowed' ? 'Kullanıcıda' : 'İade Edildi') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
