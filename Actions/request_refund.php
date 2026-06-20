<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$returnTo = $_POST['return_to'] ?? 'Pages/manager_refunds.php';
if (strpos($returnTo, '://') !== false || strpos($returnTo, '/') === 0) {
    $returnTo = 'Pages/manager_refunds.php';
}

$orderId = (int)($_POST['order_id'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');
$managerId = (int)$_SESSION['user']['id'];

if ($orderId <= 0 || $alasan === '') {
    $_SESSION['flash_error'] = 'Order dan alasan refund wajib diisi.';
    header('Location: ' . app_url($returnTo));
    exit;
}

$check = $conn->prepare(
    'SELECT id, status, total_bayar, tanggal
     FROM orders
     WHERE id = ?
     LIMIT 1'
);
$check->bind_param('i', $orderId);
$check->execute();
$order = $check->get_result()->fetch_assoc();
$check->close();

if (!$order) {
    $_SESSION['flash_error'] = 'Order tidak ditemukan.';
    header('Location: ' . app_url($returnTo));
    exit;
}

if (is_period_locked($conn, $order['tanggal'])) {
    $_SESSION['flash_error'] = 'Periode transaksi ini sudah dikunci pembukuan.';
    header('Location: ' . app_url($returnTo));
    exit;
}

if ($order['status'] !== 'paid') {
    $_SESSION['flash_error'] = 'Refund hanya bisa diajukan untuk order yang sudah lunas.';
    header('Location: ' . app_url($returnTo));
    exit;
}

$dup = $conn->prepare(
    'SELECT id
     FROM refunds
     WHERE order_id = ? AND status = "pending"
     LIMIT 1'
);
$dup->bind_param('i', $orderId);
$dup->execute();
$existing = $dup->get_result()->fetch_assoc();
$dup->close();

if ($existing) {
    $_SESSION['flash_error'] = 'Order ini sudah memiliki pengajuan refund pending.';
    header('Location: ' . app_url($returnTo));
    exit;
}

$hasRequestedByColumn = table_column_exists($conn, 'refunds', 'requested_by');
$refundAmount = (float)$order['total_bayar'];

if ($hasRequestedByColumn) {
    $stmt = $conn->prepare(
        'INSERT INTO refunds (order_id, alasan, status, refund_amount, requested_by)
         VALUES (?, ?, "pending", ?, ?)'
    );
    $stmt->bind_param('isdi', $orderId, $alasan, $refundAmount, $managerId);
} else {
    $stmt = $conn->prepare(
        'INSERT INTO refunds (order_id, alasan, status, refund_amount)
         VALUES (?, ?, "pending", ?)'
    );
    $stmt->bind_param('isd', $orderId, $alasan, $refundAmount);
}

$stmt->execute();
$refundId = $conn->insert_id;
$stmt->close();
audit_log($conn, 'refund_request', 'refunds', $refundId, 'Pengajuan refund order #' . $orderId);

$_SESSION['flash_success'] = 'Pengajuan refund berhasil dikirim ke finance.';

header('Location: ' . app_url($returnTo));
exit;
