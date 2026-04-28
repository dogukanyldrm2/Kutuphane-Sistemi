<?php
require_once __DIR__ . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Geçersiz istek');
}

$bookId = (int)($_POST['book_id'] ?? 0);

if ($bookId <= 0) {
    die('Geçersiz kitap');
}

$stmt = db()->prepare("DELETE FROM books WHERE id = :id");
$stmt->execute(['id' => $bookId]);

set_flash('success', 'Kitap silindi.');
redirect('books.php');