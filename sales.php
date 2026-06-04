<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT * FROM orders 
     WHERE user_id = ? AND tanggal BETWEEN ? AND ? 
     ORDER BY tanggal DESC'
);
$stmt->bind_param('iss', $userId, $todayStart, $todayEnd);
$stmt->execute();
$orders = $stmt->get_result();

$sumStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refunded_sales,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending,
        COALESCE(SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END),0) AS paid_count
     FROM orders
     WHERE user_id = ? AND tanggal BETWEEN ? AND ?'
);
$sumStmt->bind_param('iss', $userId, $todayStart, $todayEnd);
$sumStmt->execute();

$summary = $sumStmt->get_result()->fetch_assoc();
$sumStmt->close();

$net = (float)$summary['gross_sales'] - (float)$summary['refunded_sales'];
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sales Harian</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#f5f7fb; }
        .card, .navbar {
            background:#fff;
            border:1px solid #dee2e6;
            border-radius:14px;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .table thead th { background:#f1f3f5; }
        .muted { color:#6c757d; }
        a { text-decoration:none; }
    </style>
</head>

<body>
<div class="container py-3">
    <div class="navbar p-3 mb-3">
        <a href="kasir.php" class="btn btn-outline-secondary btn-sm">← Home</a>
        <strong>Sales Harian Kasir</strong>
        <span></span>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Total Sales</span>
                <h3 class="text-success"><?= rupiah($net) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Transaksi Lunas</span>
                <h3><?= (int)$summary['paid_count'] ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <span class="muted">Pending</span>
                <h3 class="text-warning"><?= (int)$summary['pending'] ?></h3>
            </div>
        </div>
    </div>

    <div class="card p-3">
        <h5>Riwayat Transaksi Hari Ini</h5>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Info</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Aksi Pending</th>
                </tr>
                </thead>

                <tbody>
                <?php while ($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>

                        <td>
                            <strong><?= htmlspecialchars($o['nomor_meja']) ?></strong><br>
                            <span class="muted">
                                <?= $o['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> ·
                                <?= date('H.i', strtotime($o['tanggal'])) ?>
                            </span>
                        </td>

                        <td><?= rupiah($o['total_bayar']) ?></td>

                        <td><?= strtoupper($o['metode_pembayaran']) ?></td>

                        <td>
                            <?php if ($o['status'] === 'paid'): ?>
                                <span class="badge bg-success">Lunas</span>
                            <?php elseif ($o['status'] === 'open'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Refunded</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($o['status'] === 'open'): ?>
                                <form action="mark_paid.php" method="post" class="d-flex gap-2 flex-wrap">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">

                                    <select name="metode_pembayaran" class="form-select form-select-sm" style="width:95px">
                                        <option value="tunai">Tunai</option>
                                        <option value="qris">QRIS</option>
                                    </select>

                                    <input type="number"
                                           name="nominal_diterima"
                                           class="form-control form-control-sm"
                                           placeholder="Nominal"
                                           min="0"
                                           style="width:120px">

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
    </div>
</div>
</body>
</html>