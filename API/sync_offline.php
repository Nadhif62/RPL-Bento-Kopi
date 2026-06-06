<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/order_service.php';

require_login(['kasir']);

header('Content-Type: application/json');

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    echo json_encode([
        'success' => false,
        'message' => 'Shift belum aktif.',
        'synced_count' => 0,
        'success_items' => [],
        'failed_items' => []
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$orders = $payload['orders'] ?? [];

if (!is_array($orders)) {
    echo json_encode([
        'success' => false,
        'message' => 'Format order offline tidak valid.',
        'synced_count' => 0,
        'success_items' => [],
        'failed_items' => []
    ]);
    exit;
}

$success = [];
$failed = [];

foreach ($orders as $index => $order) {
    try {
        $order['user_id'] = $userId;
        $order['shift_id'] = (int)$shift['id'];

        $result = save_order($conn, $order);

        $success[] = [
            'local_index' => $index,
            'order_id' => $result['order_id'],
            'is_append_open_bill' => $result['is_append_open_bill'] ?? false,
            'bill_total' => $result['bill_total'] ?? $result['total']
        ];
    } catch (Throwable $e) {
        $failed[] = [
            'local_index' => $index,
            'message' => $e->getMessage()
        ];
    }
}

echo json_encode([
    'success' => empty($failed),
    'synced_count' => count($success),
    'success_items' => $success,
    'failed_items' => $failed
]);