<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC'
);
$stmt->bind_param('ss', $startDateTime, $endDateTime);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Order History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Order History</div>
        <span class="muted">Outlet</span>
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
                <thead><tr><th>ID</th><th>Info</th><th>Total</th><th>Metode</th><th>Status</th><th>Kasir</th></tr></thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?>
                    <tr><td colspan="6" class="muted">Belum ada transaksi pada rentang ini.</td></tr>
                <?php endif; ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$order['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($order['order_type'] === 'takeaway' && $order['customer_name'] ? $order['customer_name'] : $order['nomor_meja']) ?></strong><br>
                            <span class="muted"><?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> · <?= date('d/m/Y H.i', strtotime($order['tanggal'])) ?></span>
                        </td>
                        <td><?= rupiah($order['total_bayar']) ?></td>
                        <td><?= strtoupper($order['metode_pembayaran']) ?></td>
                        <td>
                            <?php if ($order['status'] === 'paid'): ?>
                                <span class="badge badge-soft-success">Lunas</span>
                            <?php elseif ($order['status'] === 'open'): ?>
                                <span class="badge badge-soft-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Refunded</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($order['kasir']) ?></td>
                    </tr>
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
