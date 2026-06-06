<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$startDateTime = date('Y-m-01') . ' 00:00:00';
$endDateTime = date('Y-m-d') . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS paid_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refunded_sales
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $startDateTime, $endDateTime);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$criticalCount = $conn->query(
    'SELECT COUNT(*) AS total FROM ingredients WHERE stok_gudang <= batas_kritis'
)->fetch_assoc()['total'];

$totalIngredientCount = $conn->query(
    'SELECT COUNT(*) AS total FROM ingredients'
)->fetch_assoc()['total'];

$refundCount = $conn->query(
    'SELECT COUNT(*) AS total FROM refunds WHERE status = "pending"'
)->fetch_assoc()['total'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manager - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Manager Outlet</div>
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

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Total Transaksi</div><div class="metric-value"><?= (int)$summary['total_transaksi'] ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Order Pending</div><div class="metric-value accent-yellow"><?= (int)$summary['pending'] ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Sales Lunas</div><div class="metric-value accent-green"><?= rupiah($summary['paid_sales']) ?></div></div></div>
        <div class="col-md-3"><div class="metric-card"><div class="metric-label">Refund Pending</div><div class="metric-value accent-red"><?= (int)$refundCount ?></div></div></div>
    </div>

    <section class="manager-grid">
        <a class="nav-tile primary" href="manage_stock.php">
            <div class="nav-icon">▦</div>
            <div class="nav-title">Kelola Stock Bahan</div>
            <div class="nav-desc">Tambah, update, dan restock bahan</div>
        </a>
        <a class="nav-tile" href="order_history.php">
            <div class="nav-icon">☷</div>
            <div class="nav-title">Order History</div>
            <div class="nav-desc">Riwayat semua transaksi outlet</div>
        </a>
        <a class="nav-tile" href="audit_kasir.php">
            <div class="nav-icon">◷</div>
            <div class="nav-title">Audit Kasir</div>
            <div class="nav-desc">Audit shift dan estimasi setoran</div>
        </a>
        <a class="nav-tile" href="tambah_kasir.php">
            <div class="nav-icon">＋</div>
            <div class="nav-title">Tambah Kasir</div>
            <div class="nav-desc">Buat akun kasir baru</div>
        </a>
        <a class="nav-tile" href="jam_terlaris.php">
            <div class="nav-icon">▥</div>
            <div class="nav-title">Jam Terlaris</div>
            <div class="nav-desc">Lihat jam dengan transaksi tertinggi</div>
        </a>
        <a class="nav-tile" href="manager_refunds.php">
            <div class="nav-icon">↺</div>
            <div class="nav-title">Daftar Pengajuan Refund</div>
            <div class="nav-desc">Pantau refund pending dan approved</div>
        </a>
    </section>

    <section class="app-card mt-3">
        <h5 class="mb-3 fw-bold">Stok Hari Ini</h5>
        <div class="summary-row"><span class="muted">Total bahan terdata</span><strong><?= (int)$totalIngredientCount ?> bahan</strong></div>
        <div class="summary-row border-0"><span class="muted">Stok kritis saat ini</span><strong class="accent-red"><?= (int)$criticalCount ?> bahan</strong></div>
    </section>
</div>
</body>
</html>
