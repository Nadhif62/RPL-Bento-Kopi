<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$metode = $_POST['metode_pembayaran'] ?? 'tunai';
$nominalRaw = $_POST['nominal_diterima'] ?? '';
$nominal = 0;

if (!in_array($metode, ['tunai', 'qris'], true)) {
    $metode = 'tunai';
}

if ($orderId <= 0) {
    $_SESSION['flash_error'] = 'Order tidak valid.';
    $returnTo = $_POST['return_to'] ?? 'Pages/cek_order.php';
    if (strpos($returnTo, '://') !== false) {
        $returnTo = 'Pages/cek_order.php';
    }
    header('Location: ' . app_url($returnTo));
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT id, total_bayar, status, tanggal 
         FROM orders 
         WHERE id = ? AND user_id = ? 
         FOR UPDATE'
    );
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

    if (is_period_locked($conn, $order['tanggal'])) {
        throw new Exception('Periode transaksi ini sudah dikunci pembukuan.');
    }

    $total = (float)$order['total_bayar'];

    if ($metode === 'qris') {
        $nominal = $total;
    }

    if ($metode === 'tunai') {
        $nominal = parse_numeric_input($nominalRaw, 'Nominal tunai', 0, true);
        if ($nominal < $total) {
            throw new Exception('Nominal tunai kurang dari total bayar.');
        }
    }

    $kembalian = $metode === 'tunai' ? max(0, $nominal - $total) : 0;

    // Stok open bill sudah dikurangi saat order pending dibuat/ditambah.
    // Pelunasan hanya mengubah status pembayaran agar stok tidak terpotong dua kali.

    $update = $conn->prepare(
        'UPDATE orders
         SET status = "paid", metode_pembayaran = ?, nominal_diterima = ?, kembalian = ?
         WHERE id = ? AND user_id = ?'
    );
    $update->bind_param('sddii', $metode, $nominal, $kembalian, $orderId, $userId);
    $update->execute();
    $update->close();

    audit_log($conn, 'order_mark_paid', 'orders', $orderId, 'Open bill dilunasi.');

    $conn->commit();

    $_SESSION['flash_success'] =
        'Order #' . $orderId . ' berhasil ditandai lunas. Kembalian: ' . rupiah($kembalian);
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = $e->getMessage();
}

$returnTo = $_POST['return_to'] ?? 'Pages/cek_order.php';
if (strpos($returnTo, '://') !== false) {
    $returnTo = 'Pages/cek_order.php';
}
header('Location: ' . app_url($returnTo));
exit;