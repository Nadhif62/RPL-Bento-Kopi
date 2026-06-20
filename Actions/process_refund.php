<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['finance']);

$refundId = (int)($_POST['refund_id'] ?? 0);
$action = $_POST['action'] ?? 'approve';
$approverId = (int)$_SESSION['user']['id'];

if ($refundId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: ' . app_url('Pages/finance.php'));
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT r.id, r.order_id, r.status, o.total_bayar, o.status AS order_status, o.tanggal
         FROM refunds r
         JOIN orders o ON r.order_id = o.id
         WHERE r.id = ?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $refundId);
    $stmt->execute();

    $refund = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$refund) {
        throw new Exception('Refund tidak ditemukan.');
    }

    if ($refund['status'] !== 'pending') {
        throw new Exception('Refund sudah diproses sebelumnya.');
    }

    if (is_period_locked($conn, $refund['tanggal'])) {
        throw new Exception('Periode transaksi ini sudah dikunci pembukuan.');
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare(
            'UPDATE refunds
             SET status = "rejected",
                 approved_by = ?,
                 approved_at = NOW()
             WHERE id = ?'
        );
        $stmt->bind_param('ii', $approverId, $refundId);
        $stmt->execute();
        $stmt->close();

        audit_log($conn, 'refund_reject', 'refunds', $refundId, 'Refund ditolak.');

        $conn->commit();
        $_SESSION['flash_success'] = 'Pengajuan refund ditolak.';
        header('Location: ' . app_url('Pages/finance.php'));
        exit;
    }

    if ($refund['order_status'] === 'refunded') {
        throw new Exception('Order sudah direfund sebelumnya.');
    }

    $amount = (float)$refund['total_bayar'];

    $upRefund = $conn->prepare(
        'UPDATE refunds
         SET status = "approved",
             refund_amount = ?,
             approved_by = ?,
             approved_at = NOW()
         WHERE id = ?'
    );
    $upRefund->bind_param('dii', $amount, $approverId, $refundId);
    $upRefund->execute();
    $upRefund->close();

    $upOrder = $conn->prepare(
        'UPDATE orders
         SET status = "refunded"
         WHERE id = ?'
    );
    $upOrder->bind_param('i', $refund['order_id']);
    $upOrder->execute();
    $upOrder->close();

    audit_log($conn, 'refund_approve', 'refunds', $refundId, 'Refund disetujui untuk order #' . $refund['order_id']);

    $conn->commit();

    $_SESSION['flash_success'] =
        'Refund disetujui. Pendapatan otomatis dikurangi ' . rupiah($amount) . '.';
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . app_url('Pages/finance.php'));
exit;
