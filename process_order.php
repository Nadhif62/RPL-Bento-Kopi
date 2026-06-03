<?php
require_once 'config.php';
require_once 'order_service.php';

require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    $_SESSION['flash_error'] = 'Shift belum aktif. Start shift terlebih dahulu.';
    header('Location: kasir.php');
    exit;
}

$payload = [
    'user_id' => $userId,
    'shift_id' => (int)$shift['id'],
    'nomor_meja' => trim($_POST['nomor_meja'] ?? ''),
    'order_type' => $_POST['order_type'] ?? 'dine_in',
    'customer_name' => trim($_POST['customer_name'] ?? ''),
    'metode_pembayaran' => $_POST['metode_pembayaran'] ?? 'tunai',
    'nominal_diterima' => $_POST['nominal_diterima'] ?? null,
    'status' => $_POST['status'] ?? 'paid',
    'items' => $_POST['items'] ?? []
];

try {
    $result = save_order($conn, $payload);

    $_SESSION['flash_success'] =
        'Order #' . $result['order_id'] .
        ' berhasil. Total: ' . rupiah($result['total']) .
        '. Kembalian: ' . rupiah($result['kembalian']);

    if (!empty($result['alerts'])) {
        $_SESSION['critical_alerts'] = $result['alerts'];
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: kasir.php');
exit;