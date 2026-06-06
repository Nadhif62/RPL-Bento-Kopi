<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT
        s.id,
        s.mulai_shift,
        s.selesai_shift,
        s.petty_cash,
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
     GROUP BY s.id, s.mulai_shift, s.selesai_shift, s.petty_cash, s.status, u.nama_lengkap
     ORDER BY s.mulai_shift DESC'
);
$stmt->bind_param('ss', $startDateTime, $endDateTime);
$stmt->execute();
$shifts = $stmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Audit Kasir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Audit Kasir</div>
        <span class="muted">Shift</span>
    </header>

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-md-5"><label class="form-label">Tanggal Mulai</label><input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control"></div>
            <div class="col-md-5"><label class="form-label">Tanggal Akhir</label><input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </section>

    <section class="app-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Kasir</th><th>Mulai</th><th>Selesai</th><th>Petty Cash</th><th>Tunai</th><th>QRIS</th><th>Estimasi Setor Tunai</th><th>Status</th></tr></thead>
                <tbody>
                <?php if ($shifts->num_rows === 0): ?>
                    <tr><td colspan="8" class="muted">Belum ada data shift.</td></tr>
                <?php endif; ?>
                <?php while ($s = $shifts->fetch_assoc()): ?>
                    <?php $estimasiSetor = (float)$s['petty_cash'] + (float)$s['tunai']; ?>
                    <tr>
                        <td><?= htmlspecialchars($s['kasir']) ?></td>
                        <td><?= date('d/m/Y H.i', strtotime($s['mulai_shift'])) ?></td>
                        <td><?= $s['selesai_shift'] ? date('d/m/Y H.i', strtotime($s['selesai_shift'])) : '<span class="accent-yellow">Masih aktif</span>' ?></td>
                        <td><?= rupiah($s['petty_cash']) ?></td>
                        <td><?= rupiah($s['tunai']) ?></td>
                        <td><?= rupiah($s['qris']) ?></td>
                        <td><strong><?= rupiah($estimasiSetor) ?></strong></td>
                        <td><?= $s['status'] === 'active' ? '<span class="badge badge-soft-warning">Active</span>' : '<span class="badge badge-soft-success">Closed</span>' ?></td>
                    </tr>
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
