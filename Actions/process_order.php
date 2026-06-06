<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/order_service.php';

require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);
$returnSuccess = ($_POST['return_success'] ?? '') === '1';

if (!$shift) {
    $_SESSION['flash_error'] = 'Shift belum aktif. Start shift terlebih dahulu.';
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$payload = [
    'user_id' => $userId,
    'shift_id' => (int)$shift['id'],
    'open_order_id' => (int)($_POST['open_order_id'] ?? 0),
    'nomor_meja' => trim($_POST['nomor_meja'] ?? ''),
    'order_type' => $_POST['order_type'] ?? 'dine_in',
    'customer_name' => trim($_POST['customer_name'] ?? ''),
    'metode_pembayaran' => $_POST['metode_pembayaran'] ?? 'tunai',
    'nominal_diterima' => $_POST['nominal_diterima'] ?? 0,
    'status' => $_POST['status'] ?? 'paid',
    'items' => $_POST['items'] ?? []
];

try {
    $result = save_order($conn, $payload);

    $finalStatus = $result['status'] ?? $payload['status'];
    $finalType = $result['order_type'] ?? $payload['order_type'];

    if ($finalStatus === 'open') {
        $successTitle = !empty($result['is_append_open_bill'])
            ? 'Pesanan Tambahan Masuk Open Bill!'
            : 'Open Bill Dibuat!';
    } else {
        $successTitle = !empty($result['is_append_open_bill'])
            ? 'Open Bill Berhasil Dilunasi!'
            : 'Pembayaran Berhasil!';
    }

    $successSubtitle = ($finalType === 'dine_in'
        ? $payload['nomor_meja']
        : 'Takeaway - ' . $payload['customer_name']);
    $successSubtitle .= ' · ' . strtoupper($payload['metode_pembayaran']);

    $detailStmt = $conn->prepare(
        'SELECT m.nama_menu, od.jumlah, od.harga_satuan, od.subtotal
         FROM order_details od
         JOIN menu m ON od.menu_id = m.id
         WHERE od.order_id = ?
         ORDER BY od.id ASC'
    );
    $detailStmt->bind_param('i', $result['order_id']);
    $detailStmt->execute();
    $detailsResult = $detailStmt->get_result();

    $details = [];
    while ($row = $detailsResult->fetch_assoc()) {
        $details[] = $row;
    }
    $detailStmt->close();

    $_SESSION['last_order_success'] = [
        'title' => $successTitle,
        'subtitle' => $successSubtitle,
        'order_id' => $result['order_id'],
        'status' => $finalStatus,
        'total' => $result['bill_total'],
        'kembalian' => $result['kembalian'],
        'details' => $details,
        'is_append_open_bill' => $result['is_append_open_bill']
    ];

    if (!empty($result['is_append_open_bill']) && $finalStatus === 'open') {
        $_SESSION['flash_success'] =
            'Pesanan tambahan berhasil masuk ke Open Bill #' . $result['order_id'] .
            '. Tambahan: ' . rupiah($result['total']) .
            '. Total tagihan sekarang: ' . rupiah($result['bill_total']) . '.';
    } elseif (!empty($result['is_append_open_bill']) && $finalStatus === 'paid') {
        $_SESSION['flash_success'] =
            'Open Bill #' . $result['order_id'] . ' berhasil dilunasi. Total tagihan: ' .
            rupiah($result['bill_total']) . '. Kembalian: ' . rupiah($result['kembalian']) . '.';
    } else {
        $_SESSION['flash_success'] =
            'Order #' . $result['order_id'] .
            ' berhasil. Total: ' . rupiah($result['bill_total']) .
            '. Kembalian: ' . rupiah($result['kembalian']);
    }

    if (!empty($result['alerts'])) {
        $_SESSION['critical_alerts'] = $result['alerts'];
    }

    if ($returnSuccess) {
        header('Location: ' . app_url('Pages/order_success.php'));
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . app_url('Pages/kasir.php'));
exit;
