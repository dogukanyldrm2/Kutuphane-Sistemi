<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my_loans.php');
}

$borrowId = (int) ($_POST['borrow_id'] ?? 0);

try {
    db()->beginTransaction();

    $stmt = db()->prepare(
        'SELECT id, book_id, status
         FROM borrows
         WHERE id = :id AND user_id = :user_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([
        'id' => $borrowId,
        'user_id' => (int) $user['id'],
    ]);

    $borrow = $stmt->fetch();

    if (!$borrow) {
        throw new RuntimeException('Ödünç kaydı bulunamadı!');
    }

    if ($borrow['status'] === 'returned') {
        throw new RuntimeException('Bu kitap zaten iade edilmiş!');
    }

    $updateBorrowStmt = db()->prepare(
        'UPDATE borrows
         SET status = :status, returned_at = NOW()
         WHERE id = :id'
    );
    $updateBorrowStmt->execute([
        'status' => 'returned',
        'id' => $borrowId,
    ]);

    $updateBookStmt = db()->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = :book_id');
    $updateBookStmt->execute(['book_id' => (int) $borrow['book_id']]);

    db()->commit();
    set_flash('success', 'Kitap iade edildi.');
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    set_flash('error', 'İade işlemi başarısız: ' . $exception->getMessage());
}

redirect('my_loans.php');
