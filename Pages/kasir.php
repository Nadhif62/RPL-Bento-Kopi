<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COUNT(*) AS transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END), 0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END), 0) AS refunded_sales
     FROM orders
     WHERE user_id = ? AND tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('iss', $userId, $todayStart, $todayEnd);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$salesToday = (float)$summary['gross_sales'] - (float)$summary['refunded_sales'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kasir - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">BENTO KOPI POS</div>
            <div class="user-line">Kasir: <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($shift): ?>
                <span class="badge badge-soft-success px-3 py-2">Shift Aktif</span>
            <?php else: ?>
                <span class="badge badge-soft-warning px-3 py-2">Shift Belum Aktif</span>
            <?php endif; ?>
            <a href="../Actions/logout.php" class="btn btn-dark-outline btn-sm">Logout</a>
        </div>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['critical_alerts'])): ?>
        <div class="alert alert-warning">
            <strong>Stok Kritis:</strong>
            <ul class="mb-0">
                <?php foreach ($_SESSION['critical_alerts'] as $alert): ?>
                    <li><?= htmlspecialchars($alert) ?></li>
                <?php endforeach; unset($_SESSION['critical_alerts']); ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$shift): ?>
        <section class="app-card login-card">
            <h4 class="mb-1">Mulai Shift</h4>
            <p class="muted">Kasir wajib membuka shift sebelum melakukan transaksi.</p>
            <form action="../Actions/start_shift.php" method="post">
                <label class="form-label">Petty Cash / Kas Awal</label>
                <input type="number" name="petty_cash" class="form-control mb-3" value="500000" min="0" required>
                <button class="btn btn-success w-100">Start Shift</button>
            </form>
        </section>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Transaksi Hari Ini</div>
                    <div class="metric-value"><?= (int)$summary['transaksi'] ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Pending Bayar</div>
                    <div class="metric-value accent-yellow"><?= (int)$summary['pending'] ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">Sales Hari Ini</div>
                    <div class="metric-value accent-green"><?= rupiah($salesToday) ?></div>
                </div>
            </div>
        </div>

        <section class="home-grid">
            <a class="nav-tile primary" href="order.php">
                <div class="nav-icon">✎</div>
                <div class="nav-title">Order</div>
                <div class="nav-desc">Buat pesanan dine in atau takeaway</div>
            </a>

            <a class="nav-tile" href="cek_order.php">
                <div class="nav-icon">☷</div>
                <div class="nav-title">Sales / Cek Order</div>
                <div class="nav-desc">Lihat transaksi, pending, dan tandai lunas</div>
            </a>

            <a class="nav-tile" href="status_stock.php">
                <div class="nav-icon">▦</div>
                <div class="nav-title">Status Stock</div>
                <div class="nav-desc">Cek stok bahan dan batas kritis</div>
            </a>

            <a class="nav-tile" href="ajukan_refund.php">
                <div class="nav-icon">↺</div>
                <div class="nav-title">Ajukan Refund</div>
                <div class="nav-desc">Kirim pengajuan refund ke finance</div>
            </a>
        </section>

        <div class="wide-action">
            <a href="shift.php" class="btn btn-dark-outline btn-lg py-3">Close Shift</a>
        </div>

        <div class="offline-box">
            <div id="offlineStatus" class="alert alert-info mb-0">Mode: Online</div>
            <button type="button" id="toggleOfflineBtn" class="btn btn-dark-outline">Aktifkan Offline</button>
            <button type="button" id="syncBtn" class="btn btn-success">Sync Offline</button>
        </div>
    <?php endif; ?>
</div>
<script src="../Assets/JS/offline_handler.js"></script>
</body>
</html>
