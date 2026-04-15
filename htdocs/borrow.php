<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$bookId = (int) ($_GET['book_id'] ?? $_POST['book_id'] ?? 0);

$stmt = db()->prepare(
    'SELECT b.*, a.name AS author_name, c.name AS category_name
     FROM books b
     INNER JOIN authors a ON a.id = b.author_id
     INNER JOIN categories c ON c.id = b.category_id
     WHERE b.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $bookId]);
$book = $stmt->fetch();

if (!$book) {
    set_flash('error', 'Kitap bulunamadı.');
    redirect('books.php');
}

$error = '';

$alreadyBorrowedStmt = db()->prepare('SELECT COUNT(*) FROM borrows WHERE user_id = :user_id AND book_id = :book_id AND status = :status');
$alreadyBorrowedStmt->execute([
    'user_id' => (int) $user['id'],
    'book_id' => $bookId,
    'status' => 'borrowed',
]);
$alreadyBorrowed = (int) $alreadyBorrowedStmt->fetchColumn() > 0;

if ($alreadyBorrowed) {
    set_flash('warning', 'Bu kitap zaten hesabında ödünç olarak görünüyor!');
    redirect('my_loans.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dueDate = trim($_POST['due_date'] ?? '');

    if ($dueDate === '') {
        $error = 'Teslim tarihi seçilmelidir!';
    } elseif ($dueDate <= date('Y-m-d')) {
        $error = 'Teslim tarihi bugünden sonraki bir tarih olmali!';
    } else {
        try {
            db()->beginTransaction();

            $lockStmt = db()->prepare('SELECT available_copies FROM books WHERE id = :id FOR UPDATE');
            $lockStmt->execute(['id' => $bookId]);
            $currentAvailable = (int) $lockStmt->fetchColumn();

            if ($currentAvailable < 1) {
                throw new RuntimeException('Bu kitap için müsait kopya kalmadı.');
            }

            $insertStmt = db()->prepare(
                'INSERT INTO borrows (user_id, book_id, borrow_date, due_date, status)
                 VALUES (:user_id, :book_id, CURDATE(), :due_date, :status)'
            );
            $insertStmt->execute([
                'user_id' => (int) $user['id'],
                'book_id' => $bookId,
                'due_date' => $dueDate,
                'status' => 'borrowed',
            ]);

            $updateBookStmt = db()->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = :id');
            $updateBookStmt->execute(['id' => $bookId]);

            db()->commit();

            set_flash('success', 'Kitap ödünç alma işlemi başarılı.');
            redirect('my_loans.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = 'Ödünç işlemi tamamlanamadi: ' . $exception->getMessage();
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kitap Ödünç Al</h1>
    <p class="muted">Ödünç alma sırasında teslim tarihi girilir ve stok otomatik düşürülür.</p>
</section>

<section class="form-card">
    <h2><?= e($book['title']) ?></h2>
    <div class="meta-row">
        <span class="badge">Yazar: <?= e($book['author_name']) ?></span>
        <span class="badge">Kategori: <?= e($book['category_name']) ?></span>
        <span class="badge">Müsait Kopya: <?= e($book['available_copies']) ?></span>
    </div>

    <p class="muted"><?= e($book['description']) ?></p>

    <?php if ($error !== ''): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="book_id" value="<?= e($book['id']) ?>">

        <div class="form-group">
            <label for="due_date">Teslim Tarihi</label>
            <input
                class="form-control"
                id="due_date"
                name="due_date"
                type="date"
                value="<?= e($_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>"
                required
            >
        </div>

        <div class="helper-box">
            Varsayılan teslim süresi 14 gün olarak ayarlandı. İsterseniz farklı tarih seçebilirsiniz!
        </div>

        <div class="form-actions">
            <button type="submit">Ödünç Onayla</button>
            <a class="btn btn-secondary" href="<?= e(url('books.php')) ?>">Listeye Dön</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
