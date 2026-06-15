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
$expectedCash = (float)$shift['petty_cash'] + (float)$sales['tunai'];
$actualCashInput = $_POST['actual_cash'] ?? null;
$actualCash = ($actualCashInput !== null && $actualCashInput !== '') ? (float)$actualCashInput : null;

$hasActualColumn = table_column_exists($conn, 'shifts', 'actual_cash');
$hasDifferenceColumn = table_column_exists($conn, 'shifts', 'cash_difference');

if ($actualCash !== null && $hasActualColumn && $hasDifferenceColumn) {
    $cashDifference = $actualCash - $expectedCash;
    $stmt = $conn->prepare(
        'UPDATE shifts
         SET status = "closed", selesai_shift = NOW(), actual_cash = ?, cash_difference = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->bind_param('ddii', $actualCash, $cashDifference, $shift['id'], $userId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_success'] = 'Shift berhasil ditutup. Selisih kas: ' . rupiah($cashDifference) . '.';
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

header('Location: ' . app_url('Pages/kasir.php'));
exit;
