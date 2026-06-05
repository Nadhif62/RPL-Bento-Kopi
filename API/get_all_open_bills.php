<?php
require_once 'config.php';
require_login(['kasir']);

header('Content-Type: application/json');

$stmt = $conn->prepare(
    'SELECT 
        id,
        nomor_meja,
        order_type,
        customer_name,
        total_bayar,
        metode_pembayaran,
        status,
        tanggal
     FROM orders
     WHERE status = "open"
     ORDER BY id DESC'
);

$stmt->execute();
$ordersResult = $stmt->get_result();

$openBills = [];

while ($order = $ordersResult->fetch_assoc()) {
    $orderId = (int)$order['id'];

    $detailStmt = $conn->prepare(
        'SELECT 
            od.menu_id,
            m.nama_menu,
            od.jumlah,
            od.harga_satuan,
            od.subtotal
         FROM order_details od
         JOIN menu m ON od.menu_id = m.id
         WHERE od.order_id = ?
         ORDER BY m.nama_menu ASC'
    );

    $detailStmt->bind_param('i', $orderId);
    $detailStmt->execute();

    $detailsResult = $detailStmt->get_result();
    $details = [];

    while ($detail = $detailsResult->fetch_assoc()) {
        $details[] = [
            'menu_id' => (int)$detail['menu_id'],
            'nama_menu' => $detail['nama_menu'],
            'jumlah' => (int)$detail['jumlah'],
            'harga_satuan' => (float)$detail['harga_satuan'],
            'subtotal' => (float)$detail['subtotal']
        ];
    }

    $detailStmt->close();

    $openBills[] = [
        'order' => [
            'id' => $orderId,
            'nomor_meja' => $order['nomor_meja'],
            'order_type' => $order['order_type'],
            'customer_name' => $order['customer_name'],
            'total_bayar' => (float)$order['total_bayar'],
            'metode_pembayaran' => $order['metode_pembayaran'],
            'status' => $order['status'],
            'tanggal' => $order['tanggal']
        ],
        'details' => $details
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'open_bills' => $openBills
]);