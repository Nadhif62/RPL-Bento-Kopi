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

$cashierReportStmt = $conn->prepare(
    'SELECT
        u.nama_lengkap AS kasir,
        COUNT(o.id) AS total_order,
        COALESCE(SUM(CASE WHEN o.status IN ("paid","refunded") THEN o.total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN o.status = "refunded" THEN o.total_bayar ELSE 0 END),0) AS refund_total,
        COALESCE(SUM(CASE WHEN o.status = "paid" AND o.metode_pembayaran = "tunai" THEN o.total_bayar ELSE 0 END),0) AS tunai,
        COALESCE(SUM(CASE WHEN o.status = "paid" AND o.metode_pembayaran = "qris" THEN o.total_bayar ELSE 0 END),0) AS qris
     FROM users u
     LEFT JOIN orders o ON u.id = o.user_id AND o.tanggal BETWEEN ? AND ?
     WHERE u.role = "kasir"
     GROUP BY u.id, u.nama_lengkap
     ORDER BY u.nama_lengkap ASC'
);
$cashierReportStmt->bind_param('ss', $startDateTime, $endDateTime);
$cashierReportStmt->execute();
$cashierReports = $cashierReportStmt->get_result();

$transactionStmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC'
);
$transactionStmt->bind_param('ss', $startDateTime, $endDateTime);
$transactionStmt->execute();
$transactions = $transactionStmt->get_result();

$refunds = $conn->query(
    'SELECT r.*, o.total_bayar, o.nomor_meja, o.metode_pembayaran, u.nama_lengkap AS kasir
     FROM refunds r
     JOIN orders o ON r.order_id = o.id
     JOIN users u ON o.user_id = u.id
     ORDER BY r.created_at DESC'
);
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance Dashboard - Bento Kopi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#f5f7fb; }
        .card, .navbar {
            background:#fff;
            border:1px solid #dee2e6;
            border-radius:14px;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .table thead th { background:#f1f3f5; }
        .muted { color:#6c757d; }
        a { text-decoration:none; }
    </style>
</head>

<body>
<nav class="navbar m-3 p-3">
    <div>
        <strong>Finance Dashboard Bento Kopi</strong><br>
    </div>

    <div>
        <span class="me-3"><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></span>
        <a href="../Actions/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
</nav>

<div class="container-fluid px-3 pb-4">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <div class="card p-3 mb-3">
        <h5>Filter Laporan Keuangan</h5>

        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100">Tampilkan Laporan</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Gross Sales</span>
                <h3><?= rupiah($summary['gross_sales']) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Refund Approved</span>
                <h3 class="text-danger">- <?= rupiah($summary['refund_total']) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Net Sales</span>
                <h3 class="text-success"><?= rupiah($netSales) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Total Transaksi</span>
                <h3><?= (int)$summary['total_transaksi'] ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Total Tunai</span>
                <h3><?= rupiah($summary['total_tunai']) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Total QRIS</span>
                <h3><?= rupiah($summary['total_qris']) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Pending Order</span>
                <h3 class="text-warning"><?= (int)$summary['pending_order'] ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card p-3 mb-4">
                <h5>Laporan Penjualan per Kasir</h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Kasir</th>
                            <th>Total Order</th>
                            <th>Gross Sales</th>
                            <th>Refund</th>
                            <th>Net Sales</th>
                            <th>Tunai</th>
                            <th>QRIS</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($row = $cashierReports->fetch_assoc()): ?>
                            <?php $cashierNet = (float)$row['gross_sales'] - (float)$row['refund_total']; ?>
                            <tr>
                                <td><?= htmlspecialchars($row['kasir']) ?></td>
                                <td><?= (int)$row['total_order'] ?></td>
                                <td><?= rupiah($row['gross_sales']) ?></td>
                                <td class="text-danger">- <?= rupiah($row['refund_total']) ?></td>
                                <td class="text-success"><strong><?= rupiah($cashierNet) ?></strong></td>
                                <td><?= rupiah($row['tunai']) ?></td>
                                <td><?= rupiah($row['qris']) ?></td>
                            </tr>
                        <?php endwhile; $cashierReportStmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-3">
                <h5>Rekap Transaksi Keuangan</h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Kasir</th>
                            <th>Metode</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($trx = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $trx['id'] ?></td>
                                <td><?= date('d/m/Y H.i', strtotime($trx['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($trx['kasir']) ?></td>
                                <td><?= strtoupper($trx['metode_pembayaran']) ?></td>
                                <td>
                                    <?php if ($trx['status'] === 'refunded'): ?>
                                        <span class="text-decoration-line-through muted">
                                            <?= rupiah($trx['total_bayar']) ?>
                                        </span><br>
                                        <strong class="text-danger">-<?= rupiah($trx['total_bayar']) ?></strong>
                                    <?php else: ?>
                                        <strong><?= rupiah($trx['total_bayar']) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trx['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($trx['status'] === 'open'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Refunded</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; $transactionStmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-3">
                <h5>Validasi Refund Finance</h5>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($refund = $refunds->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    #<?= $refund['order_id'] ?><br>
                                    <small class="muted">
                                        <?= htmlspecialchars($refund['kasir']) ?> · <?= strtoupper($refund['metode_pembayaran']) ?>
                                    </small>
                                </td>

                                <td><?= rupiah($refund['total_bayar']) ?></td>

                                <td>
                                    <?= $refund['status'] === 'approved'
                                        ? '<span class="badge bg-success">Approved</span>'
                                        : '<span class="badge bg-warning">Pending</span>' ?>
                                </td>

                                <td>
                                    <?php if ($refund['status'] === 'pending'): ?>
                                        <form action="../Actions/process_refund.php"
                                              method="post"
                                              onsubmit="return confirm('Setujui refund dari sisi finance?')">
                                            <input type="hidden" name="refund_id" value="<?= $refund['id'] ?>">
                                            <button class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <small>Selesai</small>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="4">
                                    <small>
                                        <strong>Alasan:</strong>
                                        <?= htmlspecialchars($refund['alasan']) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <small class="muted">
                    Finance menyetujui refund dari sisi pencatatan kas/QRIS dan laporan net sales.
                </small>
            </div>
        </div>
    </div>
</div>
</body>
</html>