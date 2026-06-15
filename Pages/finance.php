<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refund_total,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS cash_in,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS cash_out,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending_order,
        COUNT(*) AS total_transaksi
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $startDateTime, $endDateTime);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$refundPending = $conn->query('SELECT COUNT(*) AS total FROM refunds WHERE status = "pending"')->fetch_assoc()['total'] ?? 0;
$activeShift = $conn->query('SELECT COUNT(*) AS total FROM shifts WHERE status = "active"')->fetch_assoc()['total'] ?? 0;
$netSales = (float)$summary['gross_sales'] - (float)$summary['refund_total'];
$netCashFlow = (float)$summary['cash_in'] - (float)$summary['cash_out'];
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
            <div class="col-md-5">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control">
            </div>
            <div class="col-md-5">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </section>

    <section class="row g-3 mb-3 manager-summary-row">
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Gross Sales</div>
                <div class="metric-value manager-stat-value money-nowrap"><?= rupiah($summary['gross_sales']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Refund Approved</div>
                <div class="metric-value manager-stat-value money-nowrap accent-red">- <?= rupiah($summary['refund_total']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Net Sales</div>
                <div class="metric-value manager-stat-value money-nowrap accent-green"><?= rupiah($netSales) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Pending Order</div>
                <div class="metric-value manager-stat-value accent-yellow"><?= (int)$summary['pending_order'] ?></div>
            </div>
        </div>
    </section>

    <section class="manager-grid">
        <a class="nav-tile primary" href="finance_cashflow.php?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>">
            <div class="nav-icon">↕</div>
            <div class="nav-title">Cash Flow</div>
            <div class="nav-desc">Validasi uang masuk, uang keluar, tunai, QRIS, dan net cash flow</div>
        </a>

        <a class="nav-tile" href="finance_audit.php?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>">
            <div class="nav-icon">◷</div>
            <div class="nav-title">Audit Shift</div>
            <div class="nav-desc">Audit petty cash, actual cash, selisih setoran, dan status shift</div>
        </a>

        <a class="nav-tile" href="finance_refunds.php">
            <div class="nav-icon">↺</div>
            <div class="nav-title">Persetujuan Refund</div>
            <div class="nav-desc"><?= (int)$refundPending ?> pengajuan refund masih pending</div>
        </a>

        <a class="nav-tile" href="finance_bookkeeping.php">
            <div class="nav-icon">▣</div>
            <div class="nav-title">Pembukuan Bulanan</div>
            <div class="nav-desc">Kunci pembukuan bulanan setelah refund dan shift selesai</div>
        </a>

        <a class="nav-tile" href="finance_transactions.php?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>">
            <div class="nav-icon">☷</div>
            <div class="nav-title">Transaksi</div>
            <div class="nav-desc">Lihat transaksi terbaru dan status pembayaran</div>
        </a>

        <div class="nav-tile">
            <div class="nav-icon">●</div>
            <div class="nav-title">Status Operasional</div>
            <div class="nav-desc">Shift aktif: <?= (int)$activeShift ?> | Net cash flow: <?= rupiah($netCashFlow) ?></div>
        </div>
    </section>
</div>
</body>
</html>
