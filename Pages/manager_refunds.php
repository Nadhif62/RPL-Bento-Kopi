<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';

$paidStmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir,
            r.id AS refund_id,
            r.status AS refund_status
     FROM orders o
     JOIN users u ON o.user_id = u.id
     LEFT JOIN refunds r ON r.order_id = o.id AND r.status = "pending"
     WHERE o.status = "paid" AND o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC
     LIMIT 50'
);
$paidStmt->bind_param('ss', $todayStart, $todayEnd);
$paidStmt->execute();
$paidOrders = $paidStmt->get_result();

$hasRequestedByColumn = table_column_exists($conn, 'refunds', 'requested_by');
if ($hasRequestedByColumn) {
    $refunds = $conn->query(
        'SELECT r.*, o.total_bayar, o.nomor_meja, o.order_type, o.customer_name, o.metode_pembayaran,
                cashier.nama_lengkap AS kasir,
                manager.nama_lengkap AS manager_name
         FROM refunds r
         JOIN orders o ON r.order_id = o.id
         JOIN users cashier ON o.user_id = cashier.id
         LEFT JOIN users manager ON r.requested_by = manager.id
         ORDER BY FIELD(r.status,"pending","approved","rejected"), r.created_at DESC'
    );
} else {
    $refunds = $conn->query(
        'SELECT r.*, o.total_bayar, o.nomor_meja, o.order_type, o.customer_name, o.metode_pembayaran,
                u.nama_lengkap AS kasir,
                NULL AS manager_name
         FROM refunds r
         JOIN orders o ON r.order_id = o.id
         JOIN users u ON o.user_id = u.id
         ORDER BY FIELD(r.status,"pending","approved","rejected"), r.created_at DESC'
    );
}

function refund_badge(string $status): string
{
    if ($status === 'approved') {
        return '<span class="badge badge-soft-success">Approved</span>';
    }
    if ($status === 'rejected') {
        return '<span class="badge badge-soft-danger">Rejected</span>';
    }
    return '<span class="badge badge-soft-warning">Pending</span>';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Komplain dan Refund</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Komplain dan Refund</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <section class="app-card mb-3">
        <h5 class="mb-3">Ajukan Refund ke Finance</h5>
        <p class="muted mb-3">Manager memilih transaksi lunas, mengisi alasan komplain/refund, lalu finance yang menyetujui atau menolak.</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Kasir</th><th>Total</th><th>Metode</th><th>Alasan Komplain/Refund</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if ($paidOrders->num_rows === 0): ?>
                    <tr><td colspan="6" class="muted">Belum ada transaksi lunas hari ini.</td></tr>
                <?php endif; ?>
                <?php while ($order = $paidOrders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$order['id'] ?> · <?= htmlspecialchars($order['order_type'] === 'takeaway' && $order['customer_name'] ? $order['customer_name'] : $order['nomor_meja']) ?></strong><br>
                            <span class="muted"><?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> · <?= date('H.i', strtotime($order['tanggal'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars($order['kasir']) ?></td>
                        <td><strong><?= rupiah($order['total_bayar']) ?></strong></td>
                        <td><?= strtoupper($order['metode_pembayaran']) ?></td>
                        <?php if ($order['refund_id']): ?>
                            <td colspan="2"><span class="badge badge-soft-warning">Sudah diajukan</span></td>
                        <?php else: ?>
                            <td colspan="2">
                                <form action="../Actions/request_refund.php" method="post" class="row g-2">
                                    <input type="hidden" name="return_to" value="Pages/manager_refunds.php">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <div class="col-md-8">
                                        <input type="text" name="alasan" class="form-control form-control-sm" placeholder="Contoh: salah input / pesanan batal" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-warning btn-sm w-100">Kirim ke Finance</button>
                                    </div>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; $paidStmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="app-card">
        <h5 class="mb-3">Riwayat Pengajuan Refund</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Kasir</th><th>Manager</th><th>Total</th><th>Alasan</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                <?php if ($refunds->num_rows === 0): ?>
                    <tr><td colspan="7" class="muted">Belum ada pengajuan refund.</td></tr>
                <?php endif; ?>
                <?php while ($refund = $refunds->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$refund['order_id'] ?></strong><br>
                            <span class="muted"><?= htmlspecialchars($refund['order_type'] === 'takeaway' && $refund['customer_name'] ? $refund['customer_name'] : $refund['nomor_meja']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($refund['kasir']) ?></td>
                        <td><?= htmlspecialchars($refund['manager_name'] ?: '-') ?></td>
                        <td><?= rupiah($refund['total_bayar']) ?></td>
                        <td><?= htmlspecialchars($refund['alasan']) ?></td>
                        <td><?= refund_badge($refund['status']) ?></td>
                        <td><?= date('d/m/Y H.i', strtotime($refund['created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
