<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$pettyCash = (float)($_POST['petty_cash'] ?? 0);

if (active_shift($conn, $userId)) {
    $_SESSION['flash_error'] = 'Masih ada shift aktif.';
    header('Location: kasir.php');
    exit;
}

$stmt = $conn->prepare(
    'INSERT INTO shifts (user_id, petty_cash, status, mulai_shift) 
     VALUES (?, ?, "active", NOW())'
);
$stmt->bind_param('id', $userId, $pettyCash);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = 'Shift berhasil dimulai.';

header('Location: kasir.php');
exit;