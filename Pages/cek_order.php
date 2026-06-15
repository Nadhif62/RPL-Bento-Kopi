<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';
$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'dine_in', 'takeaway'], true)) {
    $type = 'all';
}

if ($type === 'all') {
    $stmt = $conn->prepare(
        'SELECT * FROM orders
         WHERE user_id = ?
           AND ((tanggal BETWEEN ? AND ?) OR status = "open")
         ORDER BY status = "open" DESC, tanggal DESC'
    );
    $stmt->bind_param('iss', $userId, $todayStart, $todayEnd);
} else {
    $stmt = $conn->prepare(
        'SELECT * FROM orders
         WHERE user_id = ?
           AND ((tanggal BETWEEN ? AND ?) OR status = "open")
           AND order_type = ?
         ORDER BY status = "open" DESC, tanggal DESC'
    );
    $stmt->bind_param('isss', $userId, $todayStart, $todayEnd, $type);
}
$stmt->execute();
$orders = $stmt->get_result();

$sumStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN tanggal BETWEEN ? AND ? AND status IN ("paid","refunded") THEN total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN tanggal BETWEEN ? AND ? AND status = "refunded" THEN total_bayar ELSE 0 END),0) AS refunded_sales,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending,
        COALESCE(SUM(CASE WHEN tanggal BETWEEN ? AND ? AND status = "paid" THEN 1 ELSE 0 END),0) AS paid_count
     FROM orders
     WHERE user_id = ?'
);
$sumStmt->bind_param('ssssssi', $todayStart, $todayEnd, $todayStart, $todayEnd, $todayStart, $todayEnd, $userId);
$sumStmt->execute();
$summary = $sumStmt->get_result()->fetch_assoc();
$sumStmt->close();
$net = (float)$summary['gross_sales'] - (float)$summary['refunded_sales'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sales / Cek Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Sales / Cek Order</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="metric-card"><div class="metric-label">Total Sales</div><div class="metric-value accent-green"><?= rupiah($net) ?></div></div></div>
        <div class="col-md-4"><div class="metric-card"><div class="metric-label">Transaksi Lunas</div><div class="metric-value"><?= (int)$summary['paid_count'] ?></div></div></div>
        <div class="col-md-4"><div class="metric-card"><div class="metric-label">Pending</div><div class="metric-value accent-yellow"><?= (int)$summary['pending'] ?></div></div></div>
    </div>

    <div class="type-tabs three">
        <a href="cek_order.php?type=all" class="type-tab <?= $type === 'all' ? 'active' : '' ?>">Semua</a>
        <a href="cek_order.php?type=dine_in" class="type-tab <?= $type === 'dine_in' ? 'active' : '' ?>">▱ Dine In</a>
        <a href="cek_order.php?type=takeaway" class="type-tab <?= $type === 'takeaway' ? 'active' : '' ?>">⌂ Takeaway</a>
    </div>

    <section class="app-card">
        <h5 class="mb-3">Riwayat Transaksi Hari Ini</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Info</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?>
                    <tr><td colspan="5" class="muted">Belum ada transaksi.</td></tr>
                <?php endif; ?>
                <?php while ($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($o['order_type'] === 'takeaway' && $o['customer_name'] ? $o['customer_name'] : $o['nomor_meja']) ?></strong><br>
                            <span class="muted"><?= $o['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> · <?= date('H.i', strtotime($o['tanggal'])) ?></span>
                        </td>
                        <td><strong><?= rupiah($o['total_bayar']) ?></strong></td>
                        <td><?= strtoupper($o['metode_pembayaran']) ?></td>
                        <td>
                            <?php if ($o['status'] === 'paid'): ?>
                                <span class="badge badge-soft-success">Lunas</span>
                            <?php elseif ($o['status'] === 'open'): ?>
                                <span class="badge badge-soft-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Refunded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['status'] === 'open'): ?>
                                <form action="../Actions/mark_paid.php" method="post" class="d-flex gap-2 flex-wrap">
                                    <input type="hidden" name="return_to" value="Pages/cek_order.php">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <select name="metode_pembayaran" class="form-select form-select-sm mark-paid-method">
                                        <option value="tunai">Tunai</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                    <input type="number" name="nominal_diterima" class="form-control form-control-sm mark-paid-nominal" placeholder="Nominal" min="0">
                                    <button class="btn btn-success btn-sm">Lunas</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
