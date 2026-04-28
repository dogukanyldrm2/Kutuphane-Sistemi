<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

$stmt = db()->prepare(
    'SELECT
        br.id,
        br.borrow_date,
        br.due_date,
        br.returned_at,
        br.rental_price,
        br.status,
        b.title,
        b.isbn,
        a.name AS author_name,
        c.name AS category_name
     FROM borrows br
     INNER JOIN books b ON b.id = br.book_id
     INNER JOIN authors a ON a.id = b.author_id
     INNER JOIN categories c ON c.id = b.category_id
     WHERE br.user_id = :user_id
     ORDER BY
        CASE WHEN br.status = "borrowed" THEN 0 ELSE 1 END,
        br.due_date ASC,
        br.id DESC'
);
$stmt->execute(['user_id' => (int) $user['id']]);
$loans = $stmt->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kiralama Geçmişi</h1>
    <p class="muted">Aktif kiralamalarınızı, teslim tarihlerinizi ve iade edilmiş kitaplarınızı buradan görebilirsiniz.</p>
</section>

<section>
    <?php if (!$loans): ?>
        <div class="empty-state">Henüz kiraladığınız bir kitap bulunmuyor.</div>
    <?php else: ?>
        <div class="loan-list">
            <?php foreach ($loans as $loan): ?>
                <?php
                $isReturned = $loan['status'] === 'returned';
                $isOverdue = !$isReturned && $loan['due_date'] < date('Y-m-d');
                ?>
                <article class="loan-card">
                    <h3><?= e($loan['title']) ?></h3>

                    <div class="meta-row">
                        <span class="badge">Yazar: <?= e($loan['author_name']) ?></span>
                        <span class="badge">Kategori: <?= e($loan['category_name']) ?></span>
                        <span class="badge">ISBN: <?= e($loan['isbn']) ?></span>
                        <span class="badge badge-warning">Ücret: <?= e(format_money($loan['rental_price'])) ?></span>

                        <?php if ($isReturned): ?>
                            <span class="badge badge-success">İade edildi</span>
                        <?php elseif ($isOverdue): ?>
                            <span class="badge badge-danger">Teslim tarihi geçti</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Aktif kiralama</span>
                        <?php endif; ?>
                    </div>

                    <p class="muted">
                        Alış Tarihi: <strong><?= e($loan['borrow_date']) ?></strong><br>
                        Teslim Tarihi: <strong><?= e($loan['due_date']) ?></strong><br>
                        İade Tarihi: <strong><?= e($loan['returned_at'] ?: '-') ?></strong>
                    </p>

                    <?php if (!$isReturned): ?>
                        <form method="post" action="<?= e(url('return_book.php')) ?>">
                            <input type="hidden" name="borrow_id" value="<?= e($loan['id']) ?>">
                            <button class="btn btn-success" type="submit">Kitabı İade Et</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
