<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_admin();

$error = '';
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$authors = db()->query('SELECT id, name FROM authors ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $authorId = (int) ($_POST['author_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $newAuthor = trim($_POST['new_author'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    $totalCopies = max(1, (int) ($_POST['total_copies'] ?? 1));
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

            $stmt = db()->prepare(
                'INSERT INTO books (title, author_id, category_id, isbn, description, price, image_path, total_copies, available_copies)
                 VALUES (:title, :author_id, :category_id, :isbn, :description, :price, :image_path, :total_copies, :available_copies)'
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
                'available_copies' => $totalCopies,
            ]);

            db()->commit();

            set_flash('success', 'Yeni kitap başarıyla eklendi.');
            redirect('books.php');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = 'Kitap eklenemedi: ' . $exception->getMessage();
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Kitap Ekle</h1>
    <p class="muted">Admin olarak yeni kitap, fiyat ve görsel bilgisi ekleyebilirsin.</p>
</section>

<section class="form-card">
    <h2>Yeni Kitap Formu</h2>

    <?php if ($error !== ''): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="title">Kitap Adı</label>
                <input class="form-control" id="title" name="title" type="text" value="<?= e($_POST['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="isbn">ISBN</label>
                <input class="form-control" id="isbn" name="isbn" type="text" value="<?= e($_POST['isbn'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Kiralama Fiyatı</label>
                <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="<?= e($_POST['price'] ?? '25.00') ?>" required>
            </div>

            <div class="form-group">
                <label for="image_path">Kapak Görseli Yolu</label>
                <input class="form-control" id="image_path" name="image_path" type="text" value="<?= e($_POST['image_path'] ?? '') ?>" placeholder="Şimdilik boş bırakılabilir">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Açıklama</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="author_id">Mevcut Yazar</label>
                <select id="author_id" name="author_id">
                    <option value="0">Seç veya aşağıda yeni yazar gir</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= e($author['id']) ?>"><?= e($author['name']) ?></option>
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
                <select id="category_id" name="category_id">
                    <option value="0">Seç veya aşağıda yeni kategori gir</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="new_category">Yeni Kategori</label>
                <input class="form-control" id="new_category" name="new_category" type="text" value="<?= e($_POST['new_category'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="total_copies">Toplam Kopya</label>
            <input class="form-control" id="total_copies" name="total_copies" type="number" min="1" value="<?= e($_POST['total_copies'] ?? '3') ?>" required>
        </div>

        <div class="form-actions">
            <button type="submit">Kaydet</button>
            <a class="btn btn-secondary" href="<?= e(url('books.php')) ?>">İptal</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
