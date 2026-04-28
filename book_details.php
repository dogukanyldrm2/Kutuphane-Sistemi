<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$bookId = (int) ($_GET['id'] ?? $_POST['book_id'] ?? 0);

if ($bookId < 1) {
    set_flash('error', 'Geçersiz kitap seçimi.');
    redirect('books.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        set_flash('error', 'Lütfen 1 ile 5 arasında yıldız seçin.');
        redirect('book_details.php?id=' . $bookId);
    }

    if ($comment === '') {
        set_flash('error', 'Yorum alanı boş bırakılamaz.');
        redirect('book_details.php?id=' . $bookId);
    }

    if (mb_strlen($comment) > 1000) {
        set_flash('error', 'Yorum en fazla 1000 karakter olabilir.');
        redirect('book_details.php?id=' . $bookId);
    }

    $stmt = db()->prepare('INSERT INTO book_reviews (book_id, user_id, rating, comment) VALUES (:book_id, :user_id, :rating, :comment)');
    $stmt->execute([
        'book_id' => $bookId,
        'user_id' => (int) $user['id'],
        'rating' => $rating,
        'comment' => $comment,
    ]);

    set_flash('success', 'Yorumunuz başarıyla eklendi.');
    redirect('book_details.php?id=' . $bookId);
}

$bookStmt = db()->prepare(
    'SELECT
        b.*,
        a.name AS author_name,
        c.name AS category_name,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(r.id) AS review_count
     FROM books b
     INNER JOIN authors a ON a.id = b.author_id
     INNER JOIN categories c ON c.id = b.category_id
     LEFT JOIN book_reviews r ON r.book_id = b.id
     WHERE b.id = :id
     GROUP BY b.id, b.title, b.author_id, b.category_id, b.isbn, b.description, b.summary, b.price, b.image_path, b.total_copies, b.available_copies, b.created_at, a.name, c.name
     LIMIT 1'
);
$bookStmt->execute(['id' => $bookId]);
$book = $bookStmt->fetch();

if (!$book) {
    set_flash('error', 'Kitap bulunamadı.');
    redirect('books.php');
}

$reviewsStmt = db()->prepare(
    'SELECT r.*, u.full_name, u.username
     FROM book_reviews r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.book_id = :book_id
     ORDER BY r.created_at DESC, r.id DESC'
);
$reviewsStmt->execute(['book_id' => $bookId]);
$reviews = $reviewsStmt->fetchAll();

$borrowedStmt = db()->prepare('SELECT COUNT(*) FROM borrows WHERE user_id = :user_id AND book_id = :book_id AND status = :status');
$borrowedStmt->execute([
    'user_id' => (int) $user['id'],
    'book_id' => $bookId,
    'status' => 'borrowed',
]);
$isBorrowed = (int) $borrowedStmt->fetchColumn() > 0;

$cartStmt = db()->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id AND book_id = :book_id');
$cartStmt->execute([
    'user_id' => (int) $user['id'],
    'book_id' => $bookId,
]);
$inCart = (int) $cartStmt->fetchColumn() > 0;

if (!function_exists('format_money')) {
    function format_money(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' TL';
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kitap Detayı</h1>
    <p class="muted">Kitabın detaylarını inceleyebilir, kullanıcı yorumlarını görebilir ve yeni yorum ekleyebilirsiniz.</p>
</section>

<div id="ajax-flash" style="margin: 0 0 20px 0;"></div>

<section class="book-detail-card">
    <table class="book-detail-table-fixed" role="presentation">
        <tr>
            <td class="book-detail-table-cover">
                <?php if (!empty($book['image_path'])): ?>
                    <img src="<?= e(url((string) $book['image_path'])) ?>" alt="<?= e($book['title']) ?>">
                <?php else: ?>
                    <div class="book-detail-placeholder">Kapak görseli yok</div>
                <?php endif; ?>
            </td>

            <td class="book-detail-table-content">
                <h2><?= e($book['title']) ?></h2>

                <div class="rating-row rating-row-large">
                    <span class="stars">
                        <?php
                        $roundedAverage = (int) round((float) $book['average_rating']);
                        for ($star = 1; $star <= 5; $star++):
                            echo $star <= $roundedAverage ? '★' : '☆';
                        endfor;
                        ?>
                    </span>
                    <span class="rating-text">
                        <?= e(number_format((float) $book['average_rating'], 1)) ?> / 5
                        (<?= e((int) $book['review_count']) ?> değerlendirme)
                    </span>
                </div>

                <p class="book-detail-description"><?= nl2br(e((string) $book['description'])) ?></p>

                <div class="meta-row">
                    <span class="badge">Yazar: <?= e($book['author_name']) ?></span>
                    <span class="badge">Kategori: <?= e($book['category_name']) ?></span>
                    <span class="badge">ISBN: <?= e($book['isbn']) ?></span>
                    <span class="badge badge-warning">Aylık Fiyatı: <?= e(format_money($book['price'] ?? 0)) ?></span>
                    <span class="badge <?= (int) $book['available_copies'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                        <?= (int) $book['available_copies'] > 0 ? 'Stok: ' . e($book['available_copies']) : 'Stok yok' ?>
                    </span>
                </div>

                <?php if (!$isAdmin): ?>
                    <div class="form-actions detail-actions">
                        <?php if ($isBorrowed): ?>
                            <button type="button" disabled>Zaten ödünçte</button>
                        <?php elseif ((int) $book['available_copies'] < 1): ?>
                            <button type="button" disabled>Stok yok</button>
                        <?php elseif ($inCart): ?>
                            <button type="button" class="btn btn-secondary" disabled>Sepette</button>
                            <a class="btn btn-cart-link" href="<?= e(url('cart.php')) ?>">Sepete Git</a>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('books.php?id=' . $bookId)) ?>" class="js-add-to-cart-form" style="margin:0;">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="book_id" value="<?= e($bookId) ?>">
                                <button type="submit" class="btn js-add-to-cart-button">Sepete Ekle</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>

            <td class="book-detail-table-summary">
                <div class="book-summary-inner">
                    <div class="book-summary-header">
                        <span class="book-summary-icon">📖</span>
                        <h3>Kitap Özeti</h3>
                    </div>

                    <?php if (!empty($book['summary'])): ?>
                        <p class="book-summary-text"><?= nl2br(e((string) $book['summary'])) ?></p>
                    <?php else: ?>
                        <p class="book-summary-empty">Bu kitap için henüz özel bir özet eklenmemiş.</p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
</section>

<section class="grid grid-2" style="margin-top: 22px; align-items: start;">
    <div class="form-card">
        <h2>Yorum Ekle</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="add_review">
            <input type="hidden" name="book_id" value="<?= e($bookId) ?>">

            <div class="form-group">
                <label for="rating">Puan</label>
                <select id="rating" name="rating" required>
                    <option value="">Yıldız seçin</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= (string) ($_POST['rating'] ?? '') === (string) $i ? 'selected' : '' ?>><?= $i ?> Yıldız</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="comment">Yorum</label>
                <textarea id="comment" name="comment" rows="6" maxlength="1000" placeholder="Bu kitap hakkında düşüncelerinizi yazın..." required><?= e($_POST['comment'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit">Yorumu Gönder</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Yorumlar</h2>
        </div>

        <?php if (!$reviews): ?>
            <div class="empty-state">Henüz yorum yapılmamış. İlk yorumu sen yaz.</div>
        <?php else: ?>
            <div class="review-list">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <div class="review-head">
                            <div>
                                <strong><?= e($review['full_name']) ?></strong>
                                <div class="muted small-text">@<?= e($review['username']) ?> • <?= e(date('d.m.Y H:i', strtotime((string) $review['created_at']))) ?></div>
                            </div>
                            <div class="review-rating">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <?= $star <= (int) $review['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="review-comment"><?= nl2br(e((string) $review['comment'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$isAdmin): ?>
<script>
(function () {
    const form = document.querySelector('.js-add-to-cart-form');
    const flashWrap = document.getElementById('ajax-flash');

    function showFlash(message, type) {
        if (!flashWrap) return;
        flashWrap.innerHTML = '<div class="flash flash-' + type + '">' + message + '</div>';
    }

    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const button = form.querySelector('.js-add-to-cart-button');
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Ekleniyor...';
        }

        fetch('<?= e(url('books.php')) ?>', {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                showFlash(data.message || 'Kitap sepete eklendi.', 'success');
                if (button) {
                    button.textContent = 'Sepette';
                    button.disabled = true;
                    button.classList.add('btn-secondary');
                }

                const wrap = form.parentElement;
                if (wrap && !wrap.querySelector('.btn-cart-link')) {
                    const link = document.createElement('a');
                    link.href = data.cart_url || 'cart.php';
                    link.className = 'btn btn-cart-link';
                    link.textContent = 'Sepete Git';
                    wrap.appendChild(link);
                }
            } else {
                showFlash(data.message || 'İşlem başarısız.', 'error');
                if (button) {
                    button.disabled = false;
                    button.textContent = button.dataset.originalText || 'Sepete Ekle';
                }
            }
        })
        .catch(function () {
            showFlash('İşlem sırasında bir hata oluştu.', 'error');
            if (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText || 'Sepete Ekle';
            }
        });
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
