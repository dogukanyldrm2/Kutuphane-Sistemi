<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

if (!function_exists('format_money')) {
    function format_money(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' TL';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $response = [
        'success' => false,
        'message' => 'İşlem yapılamadı.',
        'cart_url' => url('cart.php'),
    ];

    if ($isAdmin) {
        $response['message'] = 'Admin hesapları sepet işlemi yapamaz.';
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    try {
        $stmt = db()->prepare(
            'SELECT b.id, b.available_copies,
                    EXISTS(SELECT 1 FROM borrows br WHERE br.user_id = :user_id AND br.book_id = b.id AND br.status = "borrowed") AS already_borrowed,
                    EXISTS(SELECT 1 FROM cart_items ci WHERE ci.user_id = :user_id AND ci.book_id = b.id) AS already_in_cart
             FROM books b
             WHERE b.id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $bookId,
            'user_id' => (int) $user['id'],
        ]);
        $book = $stmt->fetch();

        if (!$book) {
            throw new RuntimeException('Kitap bulunamadı.');
        }

        if ((int) $book['already_borrowed'] === 1) {
            throw new RuntimeException('Bu kitap zaten hesabınızda ödünç görünüyor.');
        }

        if ((int) $book['already_in_cart'] === 1) {
            throw new RuntimeException('Bu kitap zaten sepetinizde.');
        }

        if ((int) $book['available_copies'] < 1) {
            throw new RuntimeException('Bu kitap için stok yok.');
        }

        $insertStmt = db()->prepare('INSERT INTO cart_items (user_id, book_id) VALUES (:user_id, :book_id)');
        $insertStmt->execute([
            'user_id' => (int) $user['id'],
            'book_id' => $bookId,
        ]);

        $response['success'] = true;
        $response['message'] = 'Kitap sepete eklendi.';
    } catch (Throwable $exception) {
        $response['message'] = $exception->getMessage();
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$q = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$authorId = (int) ($_GET['author_id'] ?? 0);
$minStock = trim((string) ($_GET['min_stock'] ?? ''));
$maxStock = trim((string) ($_GET['max_stock'] ?? ''));
$minPrice = trim((string) ($_GET['min_price'] ?? ''));
$maxPrice = trim((string) ($_GET['max_price'] ?? ''));

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$authors = db()->query('SELECT id, name FROM authors ORDER BY name')->fetchAll();

$sql = '
    SELECT
        b.*,
        COALESCE(b.image_path, "") AS image_path,
        a.name AS author_name,
        c.name AS category_name
    FROM books b
    INNER JOIN authors a ON a.id = b.author_id
    INNER JOIN categories c ON c.id = b.category_id
    WHERE 1 = 1
';
$params = [];

if ($q !== '') {
    $sql .= ' AND (b.title LIKE :q OR a.name LIKE :q OR b.isbn LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

if ($categoryId > 0) {
    $sql .= ' AND b.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($authorId > 0) {
    $sql .= ' AND b.author_id = :author_id';
    $params['author_id'] = $authorId;
}

if ($minStock !== '' && is_numeric($minStock)) {
    $sql .= ' AND b.available_copies >= :min_stock';
    $params['min_stock'] = (int) $minStock;
}

if ($maxStock !== '' && is_numeric($maxStock)) {
    $sql .= ' AND b.available_copies <= :max_stock';
    $params['max_stock'] = (int) $maxStock;
}

if ($minPrice !== '' && is_numeric($minPrice)) {
    $sql .= ' AND b.price >= :min_price';
    $params['min_price'] = (float) $minPrice;
}

if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= ' AND b.price <= :max_price';
    $params['max_price'] = (float) $maxPrice;
}

$sql .= ' ORDER BY b.title ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$borrowedStmt = db()->prepare('SELECT book_id FROM borrows WHERE user_id = :user_id AND status = :status');
$borrowedStmt->execute([
    'user_id' => (int) $user['id'],
    'status' => 'borrowed',
]);
$borrowedBookIds = array_map('intval', array_column($borrowedStmt->fetchAll(), 'book_id'));

$cartBookIds = [];
if (!$isAdmin) {
    $cartIdsStmt = db()->prepare('SELECT book_id FROM cart_items WHERE user_id = :user_id');
    $cartIdsStmt->execute(['user_id' => (int) $user['id']]);
    $cartBookIds = array_map('intval', array_column($cartIdsStmt->fetchAll(), 'book_id'));
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kitaplar</h1>
    <p class="muted">
        <?php if ($isAdmin): ?>
            Admin olarak kitapları görüntüleyebilir ve yönetebilirsiniz.
        <?php else: ?>
            Kitapları filtreleyebilir, sepete ekleyebilir ve bakiyenizle kiralama yapabilirsiniz.
            Mevcut bakiye: <strong><?= e(format_money($user['balance'] ?? 0)) ?></strong>
        <?php endif; ?>
    </p>
</section>

<div id="ajax-flash" style="margin: 0 0 20px 0;"></div>

<section class="filter-card">
    <form method="get" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="q">Kitap / Yazar / ISBN</label>
                <input class="form-control" id="q" name="q" type="text" value="<?= e($q) ?>" placeholder="Örnek: Roman 12 veya Sabahattin Ali">
            </div>

            <div class="form-group">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id">
                    <option value="0">Tüm kategoriler</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="author_id">Yazar</label>
                <select id="author_id" name="author_id">
                    <option value="0">Tüm yazarlar</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= e($author['id']) ?>" <?= $authorId === (int) $author['id'] ? 'selected' : '' ?>>
                            <?= e($author['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="min_stock">Minimum Stok</label>
                <input class="form-control" id="min_stock" name="min_stock" type="number" min="0" value="<?= e($minStock) ?>" placeholder="Örn: 1">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="max_stock">Maksimum Stok</label>
                <input class="form-control" id="max_stock" name="max_stock" type="number" min="0" value="<?= e($maxStock) ?>" placeholder="Örn: 10">
            </div>

            <div class="form-group">
                <label for="min_price">Minimum Fiyat</label>
                <input class="form-control" id="min_price" name="min_price" type="number" min="0" step="0.01" value="<?= e($minPrice) ?>" placeholder="Örn: 25">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="max_price">Maksimum Fiyat</label>
                <input class="form-control" id="max_price" name="max_price" type="number" min="0" step="0.01" value="<?= e($maxPrice) ?>" placeholder="Örn: 100">
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <div class="form-actions">
                    <button type="submit">Filtrele</button>
                    <a class="btn btn-secondary" href="<?= e(url('books.php')) ?>">Temizle</a>
                </div>
            </div>
        </div>
    </form>
</section>

<section style="margin-top:20px;">
    <div class="section-title">
        <h2>Bulunan Kitaplar (<?= e(count($books)) ?>)</h2>
    </div>

    <?php if (!$books): ?>
        <div class="empty-state">Bu filtrelere uygun kitap bulunamadı.</div>
    <?php else: ?>
        <div class="book-list" id="book-list-wrap">
            <?php foreach ($books as $book): ?>
                <?php
                $bookId = (int) $book['id'];
                $isBorrowed = in_array($bookId, $borrowedBookIds, true);
                $inCart = in_array($bookId, $cartBookIds, true);
                $available = (int) $book['available_copies'] > 0;
                $imagePath = trim((string) ($book['image_path'] ?? ''));
                ?>
                <article class="book-card" data-book-id="<?= e($bookId) ?>">
                    <div class="book-card-layout">
                        <div class="book-cover">
                            <?php if ($imagePath !== ''): ?>
                                <img src="<?= e(url($imagePath)) ?>" alt="<?= e($book['title']) ?>">
                            <?php else: ?>
                                <span>Kapak görseli<br>sonra<br>eklenecek</span>
                            <?php endif; ?>
                        </div>

                        <div class="book-content">
                            <h3><?= e($book['title']) ?></h3>
                            <p class="muted"><?= e($book['description']) ?></p>

                            <div class="meta-row">
                                <span class="badge">Yazar: <?= e($book['author_name']) ?></span>
                                <span class="badge">Kategori: <?= e($book['category_name']) ?></span>
                                <span class="badge">ISBN: <?= e($book['isbn']) ?></span>
                                <span class="badge badge-warning">Fiyat: <?= e(format_money($book['price'] ?? 0)) ?></span>

                                <?php if ($available): ?>
                                    <span class="badge badge-success">Stok: <?= e($book['available_copies']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Stok yok</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isAdmin): ?>
                                <div class="form-actions book-actions cart-action-group">

                                    <?php if ($isBorrowed): ?>
                                        <button type="button" disabled>Zaten ödünçte</button>

                                    <?php elseif (!$available): ?>
                                        <button type="button" disabled>Stok yok</button>

                                    <?php elseif ($inCart): ?>
                                        <button type="button" class="btn btn-secondary" disabled>Sepette</button>
                                        <a class="btn btn-cart-link" href="<?= e(url('cart.php')) ?>">Sepete Git</a>

                                    <?php else: ?>
                                        <form method="post" action="" class="js-add-to-cart-form" style="margin:0;">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="book_id" value="<?= e($bookId) ?>">
                                            <button type="submit" class="btn js-add-to-cart-button">Sepete Ekle</button>
                                        </form>
                                    <?php endif; ?>

                                </div>

                            <?php else: ?>
                                <div class="form-actions book-actions admin-actions">

                                    <!-- GÜNCELLE -->
                                    <form method="get" action="<?= e(url('guncelle.php')) ?>" style="margin:0;">
                                        <input type="hidden" name="id" value="<?= e($bookId) ?>">
                                        <button type="submit" class="btn btn-primary">
                                            Güncelle
                                        </button>
                                    </form>

                                    <!-- SİL -->
                                    <form method="post" action="sil.php" style="margin:0;"
                                        onsubmit="return confirm('Bu kitabı silmek istediğine emin misin?');">

                                        <input type="hidden" name="book_id" value="<?= e($bookId) ?>">

                                        <button type="submit" class="btn btn-danger">
                                            Sil
                                        </button>
                                    </form>

                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!$isAdmin): ?>
<script>
(function () {
    const flashWrap = document.getElementById('ajax-flash');

    function showFlash(message, type) {
        if (!flashWrap) return;
        flashWrap.innerHTML = '<div class="flash flash-' + type + '">' + message + '</div>';
        window.setTimeout(function () {
            const flash = flashWrap.querySelector('.flash');
            if (flash) {
                flash.style.transition = 'opacity .25s ease';
                flash.style.opacity = '0';
                window.setTimeout(function () {
                    flashWrap.innerHTML = '';
                }, 250);
            }
        }, 2200);
    }

    function buildCartLink(url) {
        const link = document.createElement('a');
        link.href = url;
        link.className = 'btn btn-cart-link';
        link.textContent = 'Sepete Git';
        return link;
    }

    document.querySelectorAll('.js-add-to-cart-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const button = form.querySelector('.js-add-to-cart-button');
            const actionWrap = form.closest('.cart-action-group');

            if (button) {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Ekleniyor...';
            }

            fetch(window.location.pathname + window.location.search, {
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
                            button.classList.add('btn-secondary');
                            button.disabled = true;
                        }

                        if (actionWrap && !actionWrap.querySelector('.btn-cart-link')) {
                            actionWrap.appendChild(buildCartLink(data.cart_url || 'cart.php'));
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
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
