<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

$hasRequestedByColumn = table_column_exists($conn, 'refunds', 'requested_by');

if ($hasRequestedByColumn) {
    $refunds = $conn->query(
        'SELECT r.*, o.total_bayar, o.nomor_meja, o.order_type, o.customer_name, o.metode_pembayaran, o.tanggal,
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
        'SELECT r.*, o.total_bayar, o.nomor_meja, o.order_type, o.customer_name, o.metode_pembayaran, o.tanggal,
                u.nama_lengkap AS kasir,
                NULL AS manager_name
         FROM refunds r
         JOIN orders o ON r.order_id = o.id
         JOIN users u ON o.user_id = u.id
         ORDER BY FIELD(r.status,"pending","approved","rejected"), r.created_at DESC'
    );
}

$refundSummary = $conn->query(
    'SELECT
        COUNT(*) AS total_refund,
        COALESCE(SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END),0) AS pending_refund,
        COALESCE(SUM(CASE WHEN status = "approved" THEN refund_amount ELSE 0 END),0) AS approved_amount,
        COALESCE(SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END),0) AS rejected_refund
     FROM refunds'
)->fetch_assoc();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance - Persetujuan Refund</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Persetujuan Refund Dana</div>
            <div class="user-line">Approve atau reject pengajuan refund dari manager</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="finance.php" class="btn btn-dark-outline btn-sm">← Dashboard</a>
            <a href="../Actions/logout.php" class="btn btn-dark-outline btn-sm">Logout</a>
        </div>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <section class="row g-3 mb-3 manager-summary-row">
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Total Pengajuan Refund</div>
                <div class="metric-value manager-stat-value"><?= (int)$refundSummary['total_refund'] ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Refund Pending</div>
                <div class="metric-value manager-stat-value accent-yellow"><?= (int)$refundSummary['pending_refund'] ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Nominal Refund Approved</div>
                <div class="metric-value manager-stat-value money-nowrap accent-red"><?= rupiah($refundSummary['approved_amount']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Refund Rejected</div>
                <div class="metric-value manager-stat-value"><?= (int)$refundSummary['rejected_refund'] ?></div>
            </div>
        </div>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Daftar Pengajuan Refund</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Kasir</th>
                    <th>Manager</th>
                    <th>Total</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($refunds->num_rows === 0): ?>
                    <tr><td colspan="7" class="muted">Belum ada pengajuan refund.</td></tr>
                <?php endif; ?>

                <?php while ($refund = $refunds->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$refund['order_id'] ?></strong><br>
                            <span class="muted">
                                <?= htmlspecialchars($refund['order_type'] === 'takeaway' && $refund['customer_name']
                                    ? $refund['customer_name']
                                    : $refund['nomor_meja']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($refund['kasir']) ?></td>
                        <td><?= htmlspecialchars($refund['manager_name'] ?: '-') ?></td>
                        <td><?= rupiah($refund['total_bayar']) ?></td>
                        <td><?= htmlspecialchars($refund['alasan']) ?></td>
                        <td><?= finance_refund_badge($refund['status']) ?></td>
                        <td>
                            <?php if ($refund['status'] === 'pending'): ?>
                                <div class="d-flex gap-2 flex-wrap">
                                    <form action="../Actions/process_refund.php" method="post" onsubmit="return confirm('Setujui refund ini?')">
                                        <input type="hidden" name="refund_id" value="<?= (int)$refund['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <form action="../Actions/process_refund.php" method="post" onsubmit="return confirm('Tolak refund ini?')">
                                        <input type="hidden" name="refund_id" value="<?= (int)$refund['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
