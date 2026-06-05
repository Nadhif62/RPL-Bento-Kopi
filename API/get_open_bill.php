<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

header('Content-Type: application/json');

$nomorMeja = trim($_GET['nomor_meja'] ?? '');

if ($nomorMeja === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Nomor meja kosong.'
    ]);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id, nomor_meja, order_type, customer_name, total_bayar, metode_pembayaran, status, tanggal
     FROM orders
     WHERE nomor_meja = ?
       AND status = "open"
     ORDER BY id DESC
     LIMIT 1'
);
$stmt->bind_param('s', $nomorMeja);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode([
        'success' => true,
        'has_open_bill' => false
    ]);
    exit;
}

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
$detailStmt->bind_param('i', $order['id']);
$detailStmt->execute();

$details = [];

$result = $detailStmt->get_result();

while ($row = $result->fetch_assoc()) {
    $details[] = [
        'menu_id' => (int)$row['menu_id'],
        'nama_menu' => $row['nama_menu'],
        'jumlah' => (int)$row['jumlah'],
        'harga_satuan' => (float)$row['harga_satuan'],
        'subtotal' => (float)$row['subtotal']
    ];
}

$detailStmt->close();

echo json_encode([
    'success' => true,
    'has_open_bill' => true,
    'order' => [
        'id' => (int)$order['id'],
        'nomor_meja' => $order['nomor_meja'],
        'order_type' => $order['order_type'],
        'customer_name' => $order['customer_name'],
        'total_bayar' => (float)$order['total_bayar'],
        'metode_pembayaran' => $order['metode_pembayaran'],
        'status' => $order['status'],
        'tanggal' => $order['tanggal']
    ],
    'details' => $details
]);