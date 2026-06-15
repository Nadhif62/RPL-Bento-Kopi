<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS cash_in,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS cash_out,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "tunai" THEN total_bayar ELSE 0 END),0) AS total_tunai,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "qris" THEN total_bayar ELSE 0 END),0) AS total_qris,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending_order,
        COUNT(*) AS total_transaksi
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$stmt->bind_param('ss', $startDateTime, $endDateTime);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$netCashFlow = (float)$summary['cash_in'] - (float)$summary['cash_out'];

$dailyStmt = $conn->prepare(
    'SELECT
        DATE(tanggal) AS tanggal_transaksi,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS cash_in,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS cash_out,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "tunai" THEN total_bayar ELSE 0 END),0) AS tunai,
        COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "qris" THEN total_bayar ELSE 0 END),0) AS qris,
        COUNT(*) AS total_transaksi
     FROM orders
     WHERE tanggal BETWEEN ? AND ?
     GROUP BY DATE(tanggal)
     ORDER BY tanggal_transaksi DESC'
);
$dailyStmt->bind_param('ss', $startDateTime, $endDateTime);
$dailyStmt->execute();
$dailyRows = $dailyStmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance - Cash Flow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Validasi Cash Flow</div>
            <div class="user-line">Uang masuk, uang keluar, tunai, dan QRIS</div>
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

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-md-5"><label class="form-label">Tanggal Mulai</label><input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control"></div>
            <div class="col-md-5"><label class="form-label">Tanggal Akhir</label><input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </section>

    <section class="row g-3 mb-3 manager-summary-row">
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Cash In</div>
                <div class="metric-value manager-stat-value money-nowrap accent-green"><?= rupiah($summary['cash_in']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Cash Out</div>
                <div class="metric-value manager-stat-value money-nowrap accent-red"><?= rupiah($summary['cash_out']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Tunai Masuk</div>
                <div class="metric-value manager-stat-value money-nowrap"><?= rupiah($summary['total_tunai']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">QRIS Masuk</div>
                <div class="metric-value manager-stat-value money-nowrap"><?= rupiah($summary['total_qris']) ?></div>
            </div>
        </div>
    </section>

    <section class="app-card mb-3">
        <h5 class="mb-3 fw-bold">Ringkasan Validasi</h5>
        <div class="summary-row">
            <span class="muted">Net Cash Flow Periode</span>
            <strong class="<?= $netCashFlow >= 0 ? 'accent-green' : 'accent-red' ?>"><?= rupiah($netCashFlow) ?></strong>
        </div>
        <div class="summary-row">
            <span class="muted">Total Transaksi Periode</span>
            <strong><?= (int)$summary['total_transaksi'] ?> transaksi</strong>
        </div>
        <div class="summary-row border-0">
            <span class="muted">Pending Order</span>
            <strong class="accent-yellow"><?= (int)$summary['pending_order'] ?> order</strong>
        </div>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Rekap Cash Flow Harian</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Cash In</th>
                    <th>Cash Out</th>
                    <th>Net</th>
                    <th>Tunai</th>
                    <th>QRIS</th>
                    <th>Transaksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($dailyRows->num_rows === 0): ?>
                    <tr><td colspan="7" class="muted">Belum ada data cash flow.</td></tr>
                <?php endif; ?>
                <?php while ($row = $dailyRows->fetch_assoc()): ?>
                    <?php $dailyNet = (float)$row['cash_in'] - (float)$row['cash_out']; ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($row['tanggal_transaksi'])) ?></td>
                        <td><?= rupiah($row['cash_in']) ?></td>
                        <td class="accent-red"><?= rupiah($row['cash_out']) ?></td>
                        <td class="<?= $dailyNet >= 0 ? 'accent-green' : 'accent-red' ?>"><?= rupiah($dailyNet) ?></td>
                        <td><?= rupiah($row['tunai']) ?></td>
                        <td><?= rupiah($row['qris']) ?></td>
                        <td><?= (int)$row['total_transaksi'] ?></td>
                    </tr>
                <?php endwhile; $dailyStmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
