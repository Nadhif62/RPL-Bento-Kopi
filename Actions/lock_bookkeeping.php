<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

if (!finance_table_exists($conn, 'monthly_closings')) {
    $_SESSION['flash_error'] = 'Tabel monthly_closings belum tersedia.';
    header('Location: ' . app_url('Pages/finance_bookkeeping.php'));
    exit;
}

$periodMonth = trim($_POST['period_month'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$lockedBy = (int)$_SESSION['user']['id'];

if (!preg_match('/^\d{4}\-\d{2}$/', $periodMonth)) {
    $_SESSION['flash_error'] = 'Format bulan tidak valid.';
    header('Location: ' . app_url('Pages/finance_bookkeeping.php'));
    exit;
}

$conn->begin_transaction();

try {
    $checkStmt = $conn->prepare('SELECT id FROM monthly_closings WHERE period_month = ? LIMIT 1');
    $checkStmt->bind_param('s', $periodMonth);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($exists) {
        throw new Exception('Pembukuan bulan tersebut sudah dikunci.');
    }

    $pendingRefundStmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM refunds r
         JOIN orders o ON r.order_id = o.id
         WHERE r.status = "pending"
           AND DATE_FORMAT(o.tanggal, "%Y-%m") = ?'
    );
    $pendingRefundStmt->bind_param('s', $periodMonth);
    $pendingRefundStmt->execute();
    $pendingRefund = (int)($pendingRefundStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pendingRefundStmt->close();

    if ($pendingRefund > 0) {
        throw new Exception('Masih ada refund pending pada bulan tersebut. Selesaikan dulu sebelum mengunci pembukuan.');
    }

    $activeShiftStmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM shifts
         WHERE status = "active"
           AND DATE_FORMAT(mulai_shift, "%Y-%m") = ?'
    );
    $activeShiftStmt->bind_param('s', $periodMonth);
    $activeShiftStmt->execute();
    $activeShift = (int)($activeShiftStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $activeShiftStmt->close();

    if ($activeShift > 0) {
        throw new Exception('Masih ada shift aktif pada bulan tersebut.');
    }

    $insertStmt = $conn->prepare(
        'INSERT INTO monthly_closings (period_month, locked_by, notes)
         VALUES (?, ?, ?)'
    );
    $insertStmt->bind_param('sis', $periodMonth, $lockedBy, $notes);
    $insertStmt->execute();
    $insertStmt->close();

    $conn->commit();
    $_SESSION['flash_success'] = 'Pembukuan bulan ' . $periodMonth . ' berhasil dikunci.';
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . app_url('Pages/finance_bookkeeping.php?month=' . urlencode($periodMonth)));
exit;
