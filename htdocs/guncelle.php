<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_admin();

$error = '';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Geçersiz kitap ID');
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$authors = db()->query('SELECT id, name FROM authors ORDER BY name')->fetchAll();

$stmt = db()->prepare('SELECT * FROM books WHERE id = :id');
$stmt->execute(['id' => $id]);
$book = $stmt->fetch();

if (!$book) {
    die('Kitap bulunamadı');
}

$currentAuthorName = 'Bilinmiyor';
foreach ($authors as $author) {
    if ((int) $author['id'] === (int) $book['author_id']) {
        $currentAuthorName = $author['name'];
        break;
    }
}

$currentCategoryName = 'Bilinmiyor';
foreach ($categories as $category) {
    if ((int) $category['id'] === (int) $book['category_id']) {
        $currentCategoryName = $category['name'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $authorId = (int) ($_POST['author_id'] ?? $book['author_id']);
    $categoryId = (int) ($_POST['category_id'] ?? $book['category_id']);
    $newAuthor = trim($_POST['new_author'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    $totalCopies = max(1, (int) ($_POST['total_copies'] ?? $book['total_copies']));
    $price = round((float) ($_POST['price'] ?? 0), 2);
    $imagePath = trim($_POST['image_path'] ?? '');

    if ($title === '' || $isbn === '') {
        $error = 'Kitap adı ve ISBN zorunludur.';
    } elseif ($price <= 0) {
        $error = 'Kitap fiyatı 0 dan büyük olmalıdır.';
    } else {
        try {
            db()->beginTransaction();

            if ($authorId === 0 && $newAuthor !== '') {
                $stmt = db()->prepare('INSERT INTO authors (name) VALUES (:name)');
                $stmt->execute(['name' => $newAuthor]);
                $authorId = (int) db()->lastInsertId();
            }

            if ($categoryId === 0 && $newCategory !== '') {
                $stmt = db()->prepare('INSERT INTO categories (name) VALUES (:name)');
                $stmt->execute(['name' => $newCategory]);
                $categoryId = (int) db()->lastInsertId();
            }

            if ($authorId === 0 || $categoryId === 0) {
                throw new RuntimeException('Yazar ve kategori seçimi zorunludur.');
            }

            $oldTotalCopies = (int) $book['total_copies'];
            $oldAvailableCopies = (int) $book['available_copies'];
            $borrowedCopies = max(0, $oldTotalCopies - $oldAvailableCopies);

            if ($totalCopies < $borrowedCopies) {
                throw new RuntimeException('Toplam stok sayısı şu an ödünçte olan kitaplardan az olamaz. Minimum: ' . $borrowedCopies);
            }

            $newAvailableCopies = max(0, $totalCopies - $borrowedCopies);

            $stmt = db()->prepare(
                'UPDATE books
                 SET title = :title,
                     author_id = :author_id,
                     category_id = :category_id,
                     isbn = :isbn,
                     description = :description,
                     price = :price,
                     image_path = :image_path,
                     total_copies = :total_copies,
                     available_copies = :available_copies
                 WHERE id = :id'
            );

            $stmt->execute([
                'title' => $title,
                'author_id' => $authorId,
                'category_id' => $categoryId,
                'isbn' => $isbn,
                'description' => $description,
                'price' => $price,
                'image_path' => $imagePath !== '' ? $imagePath : null,
                'total_copies' => $totalCopies,
                'available_copies' => $newAvailableCopies,
                'id' => $id,
            ]);

            db()->commit();

            set_flash('success', 'Kitap başarıyla güncellendi.');
            redirect('books.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = 'Güncelleme hatası: ' . $exception->getMessage();
        }
    }
}

$selectedAuthorId = (int) ($_POST['author_id'] ?? $book['author_id']);
$selectedCategoryId = (int) ($_POST['category_id'] ?? $book['category_id']);
$currentTitle = $_POST['title'] ?? $book['title'];
$currentImage = trim((string) ($_POST['image_path'] ?? ($book['image_path'] ?? '')));
$currentDescription = trim((string) ($_POST['description'] ?? $book['description']));
$currentIsbn = (string) ($_POST['isbn'] ?? $book['isbn']);
$currentPrice = (float) ($_POST['price'] ?? $book['price']);
$currentTotalCopies = (int) ($_POST['total_copies'] ?? $book['total_copies']);
$borrowedCopies = max(0, (int) $book['total_copies'] - (int) $book['available_copies']);
$currentAvailableCopies = max(0, $currentTotalCopies - $borrowedCopies);

$previewAuthorName = $currentAuthorName;
if ($selectedAuthorId !== 0) {
    foreach ($authors as $author) {
        if ((int) $author['id'] === $selectedAuthorId) {
            $previewAuthorName = $author['name'];
            break;
        }
    }
}
if ($selectedAuthorId === 0 && trim((string) ($_POST['new_author'] ?? '')) !== '') {
    $previewAuthorName = trim((string) $_POST['new_author']);
}

$previewCategoryName = $currentCategoryName;
if ($selectedCategoryId !== 0) {
    foreach ($categories as $category) {
        if ((int) $category['id'] === $selectedCategoryId) {
            $previewCategoryName = $category['name'];
            break;
        }
    }
}
if ($selectedCategoryId === 0 && trim((string) ($_POST['new_category'] ?? '')) !== '') {
    $previewCategoryName = trim((string) $_POST['new_category']);
}

require_once __DIR__ . '/partials/header.php';
?>

<section class="hero">
    <h1>Kitap Güncelle</h1>
    <p class="muted">Kitap bilgilerini düzenleyebilir, yazar ve kategori değiştirebilir, stok sayısını güncelleyebilirsin.</p>
</section>

<section class="grid grid-2">
    <div class="form-card">
        <div class="section-title">
            <div>
                <h2>Güncelleme Formu</h2>
                <p class="muted">Alanları düzenleyip değişiklikleri kaydet.</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="title">Kitap Adı</label>
                    <input class="form-control" id="title" name="title" type="text" value="<?= e($currentTitle) ?>" required>
                </div>

                <div class="form-group">
                    <label for="isbn">ISBN</label>
                    <input class="form-control" id="isbn" name="isbn" type="text" value="<?= e($currentIsbn) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Kiralama Fiyatı</label>
                    <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="<?= e((string) $currentPrice) ?>" required>
                </div>

                <div class="form-group">
                    <label for="total_copies">Toplam Stok</label>
                    <input class="form-control" id="total_copies" name="total_copies" type="number" min="1" value="<?= e((string) $currentTotalCopies) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="image_path">Kapak Görseli Yolu</label>
                <input class="form-control" id="image_path" name="image_path" type="text" value="<?= e($currentImage) ?>" placeholder="Boş bırakılabilir">
            </div>

            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="5"><?= e($currentDescription) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="author_id">Mevcut Yazar</label>
                    <select class="form-control" id="author_id" name="author_id">
                        <option value="0">Seç veya aşağıda yeni yazar gir</option>
                        <?php foreach ($authors as $author): ?>
                            <option value="<?= e($author['id']) ?>" <?= $selectedAuthorId === (int) $author['id'] ? 'selected' : '' ?>>
                                <?= e($author['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_author">Yeni Yazar</label>
                    <input class="form-control" id="new_author" name="new_author" type="text" value="<?= e($_POST['new_author'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Mevcut Kategori</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="0">Seç veya aşağıda yeni kategori gir</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= $selectedCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_category">Yeni Kategori</label>
                    <input class="form-control" id="new_category" name="new_category" type="text" value="<?= e($_POST['new_category'] ?? '') ?>">
                </div>
            </div>

            <div class="helper-box">
                <strong>Durum Özeti</strong>
                <div class="meta-row" style="margin-top:10px;">
                    <span class="badge">Stok: <?= e((string) $book['available_copies']) ?></span>
                    <span class="badge">Toplam: <?= e((string) $book['total_copies']) ?></span>
                    <span class="badge badge-warning">Ödünçte: <?= e((string) $borrowedCopies) ?></span>
                </div>
                <p class="muted" style="margin-bottom:0;">Toplam stok sayısı, ödünçte olan kitap sayısından düşük olamaz.</p>
            </div>

            <div class="form-actions">
                <button type="submit">Güncelle</button>
                <a class="btn btn-secondary" href="<?= e(url('books.php')) ?>">İptal</a>
            </div>
        </form>
    </div>

    <aside class="card">
        <div class="section-title">
            <div>
                <h2>Kitabın Tüm Bilgileri</h2>
                <p class="muted">Sol tarafta bulunan kitabın son bilgileri!.</p>
            </div>
        </div>

        <div class="book-card-layout">
            <div class="book-cover">
                <?php if ($currentImage !== ''): ?>
                    <img src="<?= e($currentImage) ?>" alt="<?= e($currentTitle) ?>">
                <?php else: ?>
                    <span>Kapak görseli yok</span>
                <?php endif; ?>
            </div>

            <div class="book-content">
                <h3><?= e($currentTitle) ?></h3>

                <div class="meta-row" style="margin-bottom:10px;">
                    <span class="badge">ID: <?= e((string) $book['id']) ?></span>
                    <span class="badge">ISBN: <?= e($currentIsbn) ?></span>
                    <span class="badge badge-success">Fiyat: <?= e(number_format($currentPrice, 2, ',', '.')) ?> TL</span>
                </div>

                <div class="meta-row" style="margin-bottom:10px;">
                    <span class="badge">Yazar: <?= e($previewAuthorName) ?></span>
                    <span class="badge">Kategori: <?= e($previewCategoryName) ?></span>
                </div>

                <div class="meta-row" style="margin-bottom:10px;">
                    <span class="badge">Toplam Stok: <?= e((string) $currentTotalCopies) ?></span>
                    <span class="badge">Mevcut Stok: <?= e((string) $currentAvailableCopies) ?></span>
                    <span class="badge badge-warning">Ödünçte: <?= e((string) $borrowedCopies) ?></span>
                </div>

              
                   
                </div>
            </div>
        </div>
    </aside>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
