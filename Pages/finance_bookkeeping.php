<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

$hasMonthlyClosingTable = finance_table_exists($conn, 'monthly_closings');
$closingHistory = null;

if ($hasMonthlyClosingTable) {
    $closingHistory = $conn->query(
        'SELECT mc.*, u.nama_lengkap AS locked_by_name
         FROM monthly_closings mc
         JOIN users u ON mc.locked_by = u.id
         ORDER BY mc.period_month DESC
         LIMIT 24'
    );
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01 00:00:00';
$monthEnd = date('Y-m-t 23:59:59', strtotime($selectedMonth . '-01'));

$monthSummaryStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS cash_in,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS cash_out,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending_order,
        COUNT(*) AS total_transaksi
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$monthSummaryStmt->bind_param('ss', $monthStart, $monthEnd);
$monthSummaryStmt->execute();
$monthSummary = $monthSummaryStmt->get_result()->fetch_assoc();
$monthSummaryStmt->close();

$pendingRefundStmt = $conn->prepare(
    'SELECT COUNT(*) AS total
     FROM refunds r
     JOIN orders o ON r.order_id = o.id
     WHERE r.status = "pending"
       AND DATE_FORMAT(o.tanggal, "%Y-%m") = ?'
);
$pendingRefundStmt->bind_param('s', $selectedMonth);
$pendingRefundStmt->execute();
$pendingRefundCount = (int)($pendingRefundStmt->get_result()->fetch_assoc()['total'] ?? 0);
$pendingRefundStmt->close();

$activeShiftStmt = $conn->prepare(
    'SELECT COUNT(*) AS total
     FROM shifts
     WHERE status = "active"
       AND DATE_FORMAT(mulai_shift, "%Y-%m") = ?'
);
$activeShiftStmt->bind_param('s', $selectedMonth);
$activeShiftStmt->execute();
$activeShiftCount = (int)($activeShiftStmt->get_result()->fetch_assoc()['total'] ?? 0);
$activeShiftStmt->close();

$isLocked = false;
if ($hasMonthlyClosingTable) {
    $lockStmt = $conn->prepare('SELECT id FROM monthly_closings WHERE period_month = ? LIMIT 1');
    $lockStmt->bind_param('s', $selectedMonth);
    $lockStmt->execute();
    $isLocked = (bool)$lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();
}

$netMonth = (float)$monthSummary['cash_in'] - (float)$monthSummary['cash_out'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance - Pembukuan Bulanan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Kunci Pembukuan Bulanan</div>
            <div class="user-line">Finalisasi periode setelah shift dan refund selesai</div>
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

    <?php if (!$hasMonthlyClosingTable): ?>
        <div class="alert alert-warning">
            Tabel <strong>monthly_closings</strong> belum ada. Jalankan SQL migration terlebih dahulu.
        </div>
    <?php endif; ?>

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-md-10">
                <label class="form-label">Periode Bulan</label>
                <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Cek</button>
            </div>
        </form>
    </section>

    <section class="row g-3 mb-3 manager-summary-row">
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Cash In Bulanan</div>
                <div class="metric-value manager-stat-value money-nowrap accent-green"><?= rupiah($monthSummary['cash_in']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Cash Out Bulanan</div>
                <div class="metric-value manager-stat-value money-nowrap accent-red"><?= rupiah($monthSummary['cash_out']) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Net Bulanan</div>
                <div class="metric-value manager-stat-value money-nowrap <?= $netMonth >= 0 ? 'accent-green' : 'accent-red' ?>"><?= rupiah($netMonth) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Status Pembukuan</div>
                <div class="metric-value manager-stat-value"><?= $isLocked ? 'Locked' : 'Open' ?></div>
            </div>
        </div>
    </section>

    <section class="app-card mb-3">
        <h5 class="mb-3 fw-bold">Validasi Sebelum Lock</h5>
        <div class="summary-row">
            <span class="muted">Refund Pending Bulan Ini</span>
            <strong class="<?= $pendingRefundCount > 0 ? 'accent-red' : 'accent-green' ?>"><?= (int)$pendingRefundCount ?></strong>
        </div>
        <div class="summary-row">
            <span class="muted">Shift Aktif Bulan Ini</span>
            <strong class="<?= $activeShiftCount > 0 ? 'accent-red' : 'accent-green' ?>"><?= (int)$activeShiftCount ?></strong>
        </div>
        <div class="summary-row border-0">
            <span class="muted">Order Pending Bulan Ini</span>
            <strong class="accent-yellow"><?= (int)$monthSummary['pending_order'] ?></strong>
        </div>

        <?php if ($hasMonthlyClosingTable): ?>
            <form action="../Actions/lock_bookkeeping.php" method="post" class="row g-3 mt-2">
                <input type="hidden" name="period_month" value="<?= htmlspecialchars($selectedMonth) ?>">
                <div class="col-md-9">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" placeholder="Contoh: pembukuan final bulan ini">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                        <?= ($isLocked || $pendingRefundCount > 0 || $activeShiftCount > 0) ? 'disabled' : '' ?>
                        onclick="return confirm('Kunci pembukuan bulan ini? Setelah dikunci, periode dianggap final.')"
                    >
                        Kunci Pembukuan
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Riwayat Pembukuan Terkunci</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Bulan</th>
                    <th>Status</th>
                    <th>Dikunci Oleh</th>
                    <th>Waktu</th>
                    <th>Catatan</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$closingHistory || $closingHistory->num_rows === 0): ?>
                    <tr><td colspan="5" class="muted">Belum ada pembukuan yang dikunci.</td></tr>
                <?php else: ?>
                    <?php while ($lock = $closingHistory->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($lock['period_month']) ?></td>
                            <td><span class="badge badge-soft-success">Locked</span></td>
                            <td><?= htmlspecialchars($lock['locked_by_name']) ?></td>
                            <td><?= date('d/m/Y H.i', strtotime($lock['locked_at'])) ?></td>
                            <td><?= htmlspecialchars($lock['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
