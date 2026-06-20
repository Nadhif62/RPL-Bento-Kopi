<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager', 'kasir']);

$currentUser = $_SESSION['user'];
$currentRole = $currentUser['role'];
$currentUserId = (int)$currentUser['id'];

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$cashierId = (int)($_GET['kasir_id'] ?? 0);
$paymentMethod = $_GET['metode_pembayaran'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = date('Y-m-d');
}
if (!in_array($paymentMethod, ['', 'tunai', 'qris'], true)) {
    $paymentMethod = '';
}

$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$where = ['o.tanggal BETWEEN ? AND ?'];
$types = 'ss';
$params = [$startDateTime, $endDateTime];

// Kasir hanya melihat riwayat miliknya sendiri. Manager dapat memfilter semua kasir.
if ($currentRole === 'kasir') {
    $where[] = 'o.user_id = ?';
    $types .= 'i';
    $params[] = $currentUserId;
} elseif ($cashierId > 0) {
    $where[] = 'o.user_id = ?';
    $types .= 'i';
    $params[] = $cashierId;
}

if ($paymentMethod !== '') {
    $where[] = 'o.metode_pembayaran = ?';
    $types .= 's';
    $params[] = $paymentMethod;
}

$sql = 'SELECT o.*, u.nama_lengkap AS kasir
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY o.tanggal DESC';

$stmt = $conn->prepare($sql);
$bindParams = [$types];
foreach ($params as $key => $value) {
    $bindParams[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);
$stmt->execute();
$orders = $stmt->get_result();

$cashiers = null;
if ($currentRole === 'manager') {
    $cashiers = $conn->query('SELECT id, nama_lengkap FROM users WHERE role = "kasir" ORDER BY nama_lengkap ASC');
}

$backUrl = $currentRole === 'kasir' ? 'kasir.php' : 'manager.php';
$backLabel = $currentRole === 'kasir' ? '← Kasir' : '← Manager';
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
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-dark-outline btn-sm"><?= htmlspecialchars($backLabel) ?></a>
        <div class="page-title">Order History</div>
        <span class="muted">Riwayat Transaksi</span>
    </header>

    <section class="app-card mb-3">
        <form method="get" class="row g-3">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control">
            </div>
            <?php if ($currentRole === 'manager'): ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Kasir</label>
                    <select name="kasir_id" class="form-select">
                        <option value="0">Semua Kasir</option>
                        <?php while ($cashier = $cashiers->fetch_assoc()): ?>
                            <option value="<?= (int)$cashier['id'] ?>" <?= $cashierId === (int)$cashier['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cashier['nama_lengkap']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Kasir</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['nama_lengkap']) ?>" disabled>
                </div>
            <?php endif; ?>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Metode</label>
                <select name="metode_pembayaran" class="form-select">
                    <option value="" <?= $paymentMethod === '' ? 'selected' : '' ?>>Semua</option>
                    <option value="tunai" <?= $paymentMethod === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                    <option value="qris" <?= $paymentMethod === 'qris' ? 'selected' : '' ?>>QRIS</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-12 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </section>

    <section class="app-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Info</th><th>Total</th><th>Metode</th><th>Status</th><th>Kasir</th></tr></thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?>
                    <tr><td colspan="6" class="muted">Belum ada transaksi pada filter ini.</td></tr>
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
