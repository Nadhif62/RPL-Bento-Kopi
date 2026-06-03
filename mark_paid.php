<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$metode = $_POST['metode_pembayaran'] ?? 'tunai';
$nominal = isset($_POST['nominal_diterima']) && $_POST['nominal_diterima'] !== ''
    ? (float)$_POST['nominal_diterima']
    : 0;

if (!in_array($metode, ['tunai', 'qris'], true)) {
    $metode = 'tunai';
}

if ($orderId <= 0) {
    $_SESSION['flash_error'] = 'Order tidak valid.';
    header('Location: sales.php');
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare('SELECT id, total_bayar, status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order tidak ditemukan.');
    }

    if ($order['status'] !== 'open') {
        throw new Exception('Order ini bukan pending.');
    }

    $total = (float)$order['total_bayar'];

    if ($metode === 'qris') {
        $nominal = $total;
    }

    if ($metode === 'tunai' && $nominal < $total) {
        throw new Exception('Nominal tunai kurang dari total bayar.');
    }

    $kembalian = $metode === 'tunai' ? max(0, $nominal - $total) : 0;

    $update = $conn->prepare(
        'UPDATE orders
         SET status = "paid", metode_pembayaran = ?, nominal_diterima = ?, kembalian = ?
         WHERE id = ? AND user_id = ?'
    );
    $update->bind_param('sddii', $metode, $nominal, $kembalian, $orderId, $userId);
    $update->execute();
    $update->close();

    $conn->commit();

    $_SESSION['flash_success'] = 'Order #' . $orderId . ' berhasil ditandai lunas. Kembalian: ' . rupiah($kembalian);
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: sales.php');
exit;