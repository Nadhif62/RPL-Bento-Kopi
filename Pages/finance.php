<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refund_total,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "tunai" THEN total_bayar ELSE 0 END),0) AS total_tunai,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "qris" THEN total_bayar ELSE 0 END),0) AS total_qris,
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending_order
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $startDateTime, $endDateTime);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();
$netSales = (float)$summary['gross_sales'] - (float)$summary['refund_total'];

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

$transactionsStmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC
     LIMIT 30'
);
$transactionsStmt->bind_param('ss', $startDateTime, $endDateTime);
$transactionsStmt->execute();
$transactions = $transactionsStmt->get_result();

function finance_refund_badge(string $status): string
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
    <title>Finance Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Finance Dashboard</div>
            <div class="user-line"><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></div>
        </div>
        <a href="../Actions/logout.php" class="btn btn-dark-outline btn-sm">Logout</a>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-md-5"><label class="form-label">Tanggal Mulai</label><input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control"></div>
            <div class="col-md-5"><label class="form-label">Tanggal Akhir</label><input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Gross Sales</div><div class="metric-value"><?= rupiah($summary['gross_sales']) ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Refund Approved</div><div class="metric-value accent-red">- <?= rupiah($summary['refund_total']) ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Net Sales</div><div class="metric-value accent-green"><?= rupiah($netSales) ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Pending Order</div><div class="metric-value accent-yellow"><?= (int)$summary['pending_order'] ?></div></div></div>
    </div>

    <section class="app-card mb-3">
        <h5 class="mb-3">Persetujuan Refund</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Kasir</th><th>Manager</th><th>Total</th><th>Alasan</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if ($refunds->num_rows === 0): ?>
                    <tr><td colspan="7" class="muted">Belum ada pengajuan refund.</td></tr>
                <?php endif; ?>
                <?php while ($refund = $refunds->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= (int)$refund['order_id'] ?></strong><br><span class="muted"><?= htmlspecialchars($refund['order_type'] === 'takeaway' && $refund['customer_name'] ? $refund['customer_name'] : $refund['nomor_meja']) ?></span></td>
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

    <section class="app-card">
        <h5 class="mb-3">Transaksi Terbaru</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Info</th><th>Total</th><th>Metode</th><th>Status</th><th>Kasir</th></tr></thead>
                <tbody>
                <?php while ($row = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['order_type'] === 'takeaway' && $row['customer_name'] ? $row['customer_name'] : $row['nomor_meja']) ?></strong><br><span class="muted"><?= date('d/m/Y H.i', strtotime($row['tanggal'])) ?></span></td>
                        <td><?= rupiah($row['total_bayar']) ?></td>
                        <td><?= strtoupper($row['metode_pembayaran']) ?></td>
                        <td><?= $row['status'] === 'paid' ? '<span class="badge badge-soft-success">Lunas</span>' : ($row['status'] === 'open' ? '<span class="badge badge-soft-warning">Pending</span>' : '<span class="badge badge-soft-danger">Refunded</span>') ?></td>
                        <td><?= htmlspecialchars($row['kasir']) ?></td>
                    </tr>
                <?php endwhile; $transactionsStmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
