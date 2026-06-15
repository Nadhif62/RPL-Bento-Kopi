<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$transactionsStmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC'
);
$transactionsStmt->bind_param('ss', $startDateTime, $endDateTime);
$transactionsStmt->execute();
$transactions = $transactionsStmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Finance - Transaksi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <div class="brand">Transaksi Finance</div>
            <div class="user-line">Monitoring transaksi dan status pembayaran</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="finance.php" class="btn btn-dark-outline btn-sm">← Dashboard</a>
            <a href="../Actions/logout.php" class="btn btn-dark-outline btn-sm">Logout</a>
        </div>
    </header>

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-md-5"><label class="form-label">Tanggal Mulai</label><input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control"></div>
            <div class="col-md-5"><label class="form-label">Tanggal Akhir</label><input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Daftar Transaksi</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Info</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Kasir</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($transactions->num_rows === 0): ?>
                    <tr><td colspan="6" class="muted">Belum ada transaksi pada periode ini.</td></tr>
                <?php endif; ?>
                <?php while ($row = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['order_type'] === 'takeaway' && $row['customer_name'] ? $row['customer_name'] : $row['nomor_meja']) ?></strong><br>
                            <span class="muted"><?= date('d/m/Y H.i', strtotime($row['tanggal'])) ?></span>
                        </td>
                        <td><?= rupiah($row['total_bayar']) ?></td>
                        <td><?= strtoupper($row['metode_pembayaran']) ?></td>
                        <td>
                            <?= $row['status'] === 'paid'
                                ? '<span class="badge badge-soft-success">Lunas</span>'
                                : ($row['status'] === 'open'
                                    ? '<span class="badge badge-soft-warning">Pending</span>'
                                    : '<span class="badge badge-soft-danger">Refunded</span>') ?>
                        </td>
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
