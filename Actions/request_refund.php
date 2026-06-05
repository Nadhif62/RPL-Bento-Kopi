<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$orderId = (int)($_POST['order_id'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');

if ($orderId <= 0 || $alasan === '') {
    $_SESSION['flash_error'] = 'Order ID dan alasan refund wajib diisi.';
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$check = $conn->prepare(
    'SELECT id, status 
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
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

if ($order['status'] !== 'paid') {
    $_SESSION['flash_error'] = 'Refund hanya bisa diajukan untuk order yang sudah lunas.';
    header('Location: ' . app_url('Pages/kasir.php'));
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
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$stmt = $conn->prepare(
    'INSERT INTO refunds (order_id, alasan, status) 
     VALUES (?, ?, "pending")'
);
$stmt->bind_param('is', $orderId, $alasan);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = 'Pengajuan refund berhasil dikirim ke finance.';

header('Location: ' . app_url('Pages/kasir.php'));
exit;