<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT o.*,
            r.id AS refund_id,
            r.status AS refund_status
     FROM orders o
     LEFT JOIN refunds r ON r.order_id = o.id AND r.status = "pending"
     WHERE o.user_id = ? AND o.status = "paid" AND o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC'
);
$stmt->bind_param('iss', $userId, $todayStart, $todayEnd);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ajukan Refund</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Ajukan Refund</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <section class="app-card">
        <h5 class="mb-3">Transaksi Lunas Hari Ini</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Alasan Refund</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?>
                    <tr><td colspan="5" class="muted">Belum ada transaksi lunas hari ini.</td></tr>
                <?php endif; ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$order['id'] ?> · <?= htmlspecialchars($order['order_type'] === 'takeaway' && $order['customer_name'] ? $order['customer_name'] : $order['nomor_meja']) ?></strong><br>
                            <span class="muted"><?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> · <?= date('H.i', strtotime($order['tanggal'])) ?></span>
                        </td>
                        <td><strong><?= rupiah($order['total_bayar']) ?></strong></td>
                        <td><?= strtoupper($order['metode_pembayaran']) ?></td>
                        <td colspan="2">
                            <?php if ($order['refund_id']): ?>
                                <span class="badge badge-soft-warning">Sudah diajukan</span>
                            <?php else: ?>
                                <form action="../Actions/request_refund.php" method="post" class="row g-2">
                                    <input type="hidden" name="return_to" value="Pages/ajukan_refund.php">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <div class="col-md-8">
                                        <input type="text" name="alasan" class="form-control form-control-sm" placeholder="Contoh: pesanan batal / salah input" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-warning btn-sm w-100">Ajukan</button>
                                    </div>
                                </form>
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
