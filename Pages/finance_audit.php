<?php
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/finance_helpers.php';
require_login(['finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$auditStmt = $conn->prepare(
    'SELECT
        s.id,
        s.mulai_shift,
        s.selesai_shift,
        s.petty_cash,
        s.actual_cash,
        s.cash_difference,
        s.status,
        u.nama_lengkap AS kasir,
        COUNT(o.id) AS total_order,
        COALESCE(SUM(CASE WHEN o.status = "paid" AND o.metode_pembayaran = "tunai" THEN o.total_bayar ELSE 0 END),0) AS tunai,
        COALESCE(SUM(CASE WHEN o.status = "paid" AND o.metode_pembayaran = "qris" THEN o.total_bayar ELSE 0 END),0) AS qris,
        COALESCE(SUM(CASE WHEN o.status = "refunded" THEN o.total_bayar ELSE 0 END),0) AS refund_total
     FROM shifts s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN orders o ON o.shift_id = s.id
     WHERE s.mulai_shift BETWEEN ? AND ?
     GROUP BY s.id, s.mulai_shift, s.selesai_shift, s.petty_cash, s.actual_cash, s.cash_difference, s.status, u.nama_lengkap
     ORDER BY s.mulai_shift DESC'
);
$auditStmt->bind_param('ss', $startDateTime, $endDateTime);
$auditStmt->execute();
$auditRows = $auditStmt->get_result();

$totalShift = 0;
$totalSelisih = 0;
$totalTunai = 0;
$totalActual = 0;
$rows = [];
while ($row = $auditRows->fetch_assoc()) {
    $totalShift++;
    $totalSelisih += abs((float)($row['cash_difference'] ?? 0));
    $totalTunai += (float)$row['tunai'];
    $totalActual += (float)($row['actual_cash'] ?? 0);
    $rows[] = $row;
}
$auditStmt->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance - Audit Shift</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Audit Cash Flow Shift</div>
            <div class="user-line">Audit kasir, petty cash, actual cash, dan selisih</div>
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
                <div class="metric-label">Total Shift Diaudit</div>
                <div class="metric-value manager-stat-value"><?= (int)$totalShift ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Total Tunai Shift</div>
                <div class="metric-value manager-stat-value money-nowrap"><?= rupiah($totalTunai) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Actual Cash Tersimpan</div>
                <div class="metric-value manager-stat-value money-nowrap accent-green"><?= rupiah($totalActual) ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="metric-card manager-stat-card h-100">
                <div class="metric-label">Total Selisih</div>
                <div class="metric-value manager-stat-value money-nowrap accent-red"><?= rupiah($totalSelisih) ?></div>
            </div>
        </div>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Detail Audit Shift</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Kasir</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Petty Cash</th>
                    <th>Tunai</th>
                    <th>QRIS</th>
                    <th>Actual Cash</th>
                    <th>Selisih</th>
                    <th>Status Audit</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="muted">Belum ada data audit shift.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $audit): ?>
                    <tr>
                        <td><?= htmlspecialchars($audit['kasir']) ?></td>
                        <td><?= date('d/m/Y H.i', strtotime($audit['mulai_shift'])) ?></td>
                        <td>
                            <?= $audit['selesai_shift']
                                ? date('d/m/Y H.i', strtotime($audit['selesai_shift']))
                                : '<span class="accent-yellow">Masih aktif</span>' ?>
                        </td>
                        <td><?= rupiah($audit['petty_cash']) ?></td>
                        <td><?= rupiah($audit['tunai']) ?></td>
                        <td><?= rupiah($audit['qris']) ?></td>
                        <td><?= rupiah($audit['actual_cash'] ?? 0) ?></td>
                        <td class="<?= ((float)($audit['cash_difference'] ?? 0) == 0.0) ? '' : 'accent-red' ?>">
                            <?= rupiah($audit['cash_difference'] ?? 0) ?>
                        </td>
                        <td><?= finance_audit_badge($audit['status'], $audit['cash_difference'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
