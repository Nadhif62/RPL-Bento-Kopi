<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    $_SESSION['flash_error'] = 'Tidak ada shift aktif.';
    header('Location: kasir.php');
    exit;
}

$stmt = $conn->prepare('UPDATE shifts SET status = "closed", selesai_shift = NOW() WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $shift['id'], $userId);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = 'Shift berhasil ditutup.';

header('Location: kasir.php');
exit;