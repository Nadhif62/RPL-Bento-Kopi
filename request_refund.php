<?php
require_once 'config.php';
require_login(['kasir']);

$orderId = (int)($_POST['order_id'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');

if ($orderId <= 0 || $alasan === '') {
    $_SESSION['flash_error'] = 'Order ID dan alasan refund wajib diisi.';
    header('Location: kasir.php');
    exit;
}

$check = $conn->prepare('SELECT id, status FROM orders WHERE id = ? LIMIT 1');
$check->bind_param('i', $orderId);
$check->execute();

$order = $check->get_result()->fetch_assoc();
$check->close();

if (!$order) {
    $_SESSION['flash_error'] = 'Order tidak ditemukan.';
    header('Location: kasir.php');
    exit;
}

if ($order['status'] === 'refunded') {
    $_SESSION['flash_error'] = 'Order ini sudah pernah direfund.';
    header('Location: kasir.php');
    exit;
}

$stmt = $conn->prepare('INSERT INTO refunds (order_id, alasan, status) VALUES (?, ?, "pending")');
$stmt->bind_param('is', $orderId, $alasan);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = 'Pengajuan refund berhasil dikirim.';

header('Location: kasir.php');
exit;