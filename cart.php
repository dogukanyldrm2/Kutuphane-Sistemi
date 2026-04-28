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

if (!function_exists('calculate_rental_days')) {
    function calculate_rental_days(string $dueDate): int
    {
        $today = new DateTime(date('Y-m-d'));
        $due = DateTime::createFromFormat('Y-m-d', $dueDate);

        if (!$due) {
            return 0;
        }

        $diff = $today->diff($due);
        $days = (int) $diff->days;

        return $due > $today ? $days : 0;
    }
}

if (!function_exists('calculate_rental_price')) {
    function calculate_rental_price(float $monthlyPrice, int $days): float
    {
        if ($days <= 0) {
            return 0.0;
        }

        return round(($monthlyPrice / 30) * $days, 2);
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

        $rentalDays = calculate_rental_days($dueDate);

        if ($rentalDays <= 0) {
            set_flash('error', 'Teslim tarihi bugünden sonra olmalıdır.');
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

                $itemRentalPrice = calculate_rental_price((float) $item['price'], $rentalDays);
                $total += $itemRentalPrice;
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
                $itemRentalPrice = calculate_rental_price((float) $item['price'], $rentalDays);

                $insertBorrow->execute([
                    'user_id' => (int) $user['id'],
                    'book_id' => (int) $item['id'],
                    'due_date' => $dueDate,
                    'rental_price' => $itemRentalPrice,
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

$defaultDueDate = date('Y-m-d', strtotime('+14 days'));
$rentalDays = calculate_rental_days($defaultDueDate);

$total = 0.0;
foreach ($items as &$item) {
    $item['calculated_rental_price'] = calculate_rental_price((float) $item['price'], $rentalDays);
    $total += (float) $item['calculated_rental_price'];
}
unset($item);

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
                    <th>Aylık Fiyat</th>
                    <th>Hesaplanan Tutar</th>
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
                        <td class="monthly-price-cell" data-monthly-price="<?= e((float) $item['price']) ?>">
                            <?= e(format_money($item['price'])) ?>
                        </td>
                        <td class="calculated-price-cell">
                            <?= e(format_money($item['calculated_rental_price'])) ?>
                        </td>
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
            <h2>Toplam Tutar: <span id="total-price"><?= e(format_money($total)) ?></span></h2>
            <p class="muted">Hesaplama: aylık fiyat / 30 × gün sayısı</p>

            <form method="post" action="">
                <input type="hidden" name="action" value="checkout">

                <div class="form-group">
                    <label for="due_date">Teslim Tarihi</label>
                    <input
                        class="form-control"
                        id="due_date"
                        name="due_date"
                        type="date"
                        value="<?= e($defaultDueDate) ?>"
                        min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Gün Sayısı</label>
                    <input class="form-control" id="rental_days" type="text" value="<?= e((string) $rentalDays) ?> gün" readonly>
                </div>

                <div class="form-actions">
                    <button class="btn btn-success" type="submit">Bakiyemle Kirala</button>
                    <a class="btn" href="<?= e(url('books.php')) ?>">Kitaplara Dön</a>
                </div>
            </form>
        </section>
    <?php endif; ?>
</section>

<script>
(function () {
    const dueDateInput = document.getElementById('due_date');
    const rentalDaysInput = document.getElementById('rental_days');
    const totalPriceEl = document.getElementById('total-price');

    if (!dueDateInput || !rentalDaysInput || !totalPriceEl) {
        return;
    }

    function formatMoney(amount) {
        return amount.toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' TL';
    }

    function calculateDays(dateStr) {
        if (!dateStr) return 0;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const due = new Date(dateStr);
        due.setHours(0, 0, 0, 0);

        const diffMs = due - today;
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        return diffDays > 0 ? diffDays : 0;
    }

    function recalculate() {
        const days = calculateDays(dueDateInput.value);
        rentalDaysInput.value = days + ' gün';

        let total = 0;

        document.querySelectorAll('.monthly-price-cell').forEach(function (cell) {
            const monthlyPrice = parseFloat(cell.dataset.monthlyPrice || '0');
            const calculated = days > 0 ? ((monthlyPrice / 30) * days) : 0;
            const row = cell.closest('tr');
            const calculatedCell = row.querySelector('.calculated-price-cell');

            if (calculatedCell) {
                calculatedCell.textContent = formatMoney(calculated);
            }

            total += calculated;
        });

        totalPriceEl.textContent = formatMoney(total);
    }

    dueDateInput.addEventListener('change', recalculate);
    recalculate();
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>