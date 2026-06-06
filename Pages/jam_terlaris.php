<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT
        HOUR(tanggal) AS jam,
        COUNT(*) AS total_order,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS sales
     FROM orders
     WHERE tanggal BETWEEN ? AND ?
     GROUP BY HOUR(tanggal)
     ORDER BY total_order DESC, sales DESC
     LIMIT 12'
);
$stmt->bind_param('ss', $startDateTime, $endDateTime);
$stmt->execute();
$peakHours = $stmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jam Terlaris</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Jam Terlaris</div>
        <span class="muted">Peak Hour</span>
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
                <thead><tr><th>Jam</th><th>Total Order</th><th>Sales Lunas</th></tr></thead>
                <tbody>
                <?php if ($peakHours->num_rows === 0): ?>
                    <tr><td colspan="3" class="muted">Belum ada transaksi pada rentang tanggal ini.</td></tr>
                <?php endif; ?>
                <?php while ($p = $peakHours->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= str_pad((string)$p['jam'], 2, '0', STR_PAD_LEFT) ?>.00</strong></td>
                        <td><?= (int)$p['total_order'] ?> order</td>
                        <td class="price"><?= rupiah($p['sales']) ?></td>
                    </tr>
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
