<?php
require_once 'config.php';
require_once 'order_service.php';

require_login(['kasir']);

header('Content-Type: application/json');

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    echo json_encode([
        'success' => false,
        'message' => 'Shift belum aktif.',
        'synced_count' => 0,
        'failed_items' => []
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$orders = $payload['orders'] ?? [];

$success = [];
$failed = [];

foreach ($orders as $index => $order) {
    try {
        $order['user_id'] = $userId;
        $order['shift_id'] = (int)$shift['id'];

        $result = save_order($conn, $order);

        $success[] = [
            'local_index' => $index,
            'order_id' => $result['order_id']
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