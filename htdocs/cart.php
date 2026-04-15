<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

if (!function_exists('format_money')) {
    function format_money(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' TL';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookId = (int) ($_POST['book_id'] ?? 0);

    if ($action === 'remove' && $bookId > 0) {
        $deleteStmt = db()->prepare('DELETE FROM cart_items WHERE user_id = :user_id AND book_id = :book_id');
        $deleteStmt->execute([
            'user_id' => (int) $user['id'],
            'book_id' => $bookId,
        ]);
        set_flash('success', 'Kitap sepetten çıkarıldı.');
        redirect('cart.php');
    }

    if ($action === 'checkout') {
        $dueDate = trim($_POST['due_date'] ?? '');

        if ($dueDate === '' || $dueDate <= date('Y-m-d')) {
            set_flash('error', 'Geçerli bir teslim tarihi seçmelisiniz.');
            redirect('cart.php');
        }

        try {
            db()->beginTransaction();

            $userStmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(['id' => (int) $user['id']]);
            $lockedUser = $userStmt->fetch();

            $itemsStmt = db()->prepare(
                'SELECT b.id, b.title, b.price, b.available_copies
                 FROM cart_items ci
                 INNER JOIN books b ON b.id = ci.book_id
                 WHERE ci.user_id = :user_id
                 FOR UPDATE'
            );
            $itemsStmt->execute(['user_id' => (int) $user['id']]);
            $items = $itemsStmt->fetchAll();

            if (!$items) {
                throw new RuntimeException('Sepetiniz boş.');
            }

            $total = 0.0;
            foreach ($items as $item) {
                if ((int) $item['available_copies'] < 1) {
                    throw new RuntimeException($item['title'] . ' için stok kalmadı.');
                }
                $total += (float) $item['price'];
            }

            if ((float) $lockedUser['balance'] < $total) {
                throw new RuntimeException('Bakiye yetersiz. Toplam tutar: ' . format_money($total));
            }

            $insertBorrow = db()->prepare(
                'INSERT INTO borrows (user_id, book_id, borrow_date, due_date, rental_price, status)
                 VALUES (:user_id, :book_id, CURDATE(), :due_date, :rental_price, :status)'
            );
            $updateBook = db()->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = :id');

            foreach ($items as $item) {
                $insertBorrow->execute([
                    'user_id' => (int) $user['id'],
                    'book_id' => (int) $item['id'],
                    'due_date' => $dueDate,
                    'rental_price' => $item['price'],
                    'status' => 'borrowed',
                ]);
                $updateBook->execute(['id' => (int) $item['id']]);
            }

            $balanceStmt = db()->prepare('UPDATE users SET balance = balance - :total WHERE id = :id');
            $balanceStmt->execute([
                'total' => $total,
                'id' => (int) $user['id'],
            ]);

            $clearCart = db()->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
            $clearCart->execute(['user_id' => (int) $user['id']]);

            db()->commit();
            set_flash('success', 'Sepet başarıyla kiralandı. Toplam tutar: ' . format_money($total));
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            set_flash('error', 'Kiralama tamamlanamadı: ' . $exception->getMessage());
        }

        redirect('cart.php');
    }
}

$stmt = db()->prepare(
    'SELECT b.id, b.title, b.isbn, b.price, b.available_copies, a.name AS author_name, c.name AS category_name
     FROM cart_items ci
     INNER JOIN books b ON b.id = ci.book_id
     INNER JOIN authors a ON a.id = b.author_id
     INNER JOIN categories c ON c.id = b.category_id
     WHERE ci.user_id = :user_id
     ORDER BY ci.id DESC'
);
$stmt->execute(['user_id' => (int) $user['id']]);
$items = $stmt->fetchAll();
$total = array_reduce($items, static fn ($carry, $item) => $carry + (float) $item['price'], 0.0);

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Sepetim</h1>
    <p class="muted">Bakiyeniz: <strong><?= e(format_money($user['balance'])) ?></strong></p>
</section>

<section>
    <?= display_flash() ?>

    <?php if (!$items): ?>
        <div class="empty-state">Sepetiniz boş.</div>
    <?php else: ?>
        <div class="table-card">
            <table>
                <thead>
                <tr>
                    <th>Kitap</th>
                    <th>Yazar</th>
                    <th>Kategori</th>
                    <th>Fiyat</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['title']) ?></td>
                        <td><?= e($item['author_name']) ?></td>
                        <td><?= e($item['category_name']) ?></td>
                        <td><?= e(format_money($item['price'])) ?></td>
                        <td><?= (int) $item['available_copies'] > 0 ? 'Müsait' : 'Stok yok' ?></td>
                        <td>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="book_id" value="<?= e($item['id']) ?>">
                                <button class="btn btn-secondary" type="submit">Çıkar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="form-card" style="margin-top:20px;">
            <h2>Toplam Tutar: <?= e(format_money($total)) ?></h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="checkout">
                <div class="form-group">
                    <label for="due_date">Teslim Tarihi</label>
                    <input class="form-control" id="due_date" name="due_date" type="date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>" required>
                </div>
                <div class="form-actions">
                    <button class="btn btn-success" type="submit">Bakiyemle Kirala</button>
                    <a class="btn" href="<?= e(url('books.php')) ?>">Kitaplara Dön</a>
                </div>
            </form>
        </section>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
