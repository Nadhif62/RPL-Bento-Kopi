<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    $_SESSION['flash_error'] = 'Tidak ada shift aktif.';
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$sales = shift_sales($conn, (int)$shift['id']);
$actualCash = (float)$shift['petty_cash'] + (float)$sales['tunai'];
$cashDifference = 0;

$pendingStmt = $conn->prepare(
    'SELECT COUNT(*) AS total_pending
     FROM orders
     WHERE shift_id = ? AND user_id = ? AND status = "open"'
);
$pendingStmt->bind_param('ii', $shift['id'], $userId);
$pendingStmt->execute();
$pendingCount = (int)($pendingStmt->get_result()->fetch_assoc()['total_pending'] ?? 0);
$pendingStmt->close();

$hasActualColumn = table_column_exists($conn, 'shifts', 'actual_cash');
$hasDifferenceColumn = table_column_exists($conn, 'shifts', 'cash_difference');

if ($hasActualColumn && $hasDifferenceColumn) {
    $stmt = $conn->prepare(
        'UPDATE shifts
         SET status = "closed", selesai_shift = NOW(), actual_cash = ?, cash_difference = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->bind_param('ddii', $actualCash, $cashDifference, $shift['id'], $userId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_success'] = 'Shift berhasil ditutup. Actual cash tersimpan ' . rupiah($actualCash) . '.';
} else {
    $stmt = $conn->prepare(
        'UPDATE shifts
         SET status = "closed", selesai_shift = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->bind_param('ii', $shift['id'], $userId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_success'] = 'Shift berhasil ditutup.';
}

if ($pendingCount > 0) {
    $_SESSION['flash_success'] .= ' ' . $pendingCount . ' order pending akan otomatis dibawa ke shift berikutnya saat kasir start shift lagi.';
}

// Setelah close shift, kasir tetap login dan diarahkan ke dashboard kasir agar tampil form input petty cash.
header('Location: ' . app_url('Pages/kasir.php'));
exit;
