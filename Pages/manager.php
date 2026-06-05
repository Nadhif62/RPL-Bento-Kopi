<?php
require_once 'config.php';
require_login(['manager']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS paid_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refunded_sales
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $startDateTime, $endDateTime);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$orderStmt = $conn->prepare(
    'SELECT o.*, u.nama_lengkap AS kasir
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.tanggal BETWEEN ? AND ?
     ORDER BY o.tanggal DESC'
);
$orderStmt->bind_param('ss', $startDateTime, $endDateTime);
$orderStmt->execute();
$orders = $orderStmt->get_result();

$refunds = $conn->query(
    'SELECT r.*, o.total_bayar, o.nomor_meja, o.metode_pembayaran, u.nama_lengkap AS kasir
     FROM refunds r
     JOIN orders o ON r.order_id = o.id
     JOIN users u ON o.user_id = u.id
     ORDER BY r.created_at DESC'
);

$cashiers = $conn->query(
    'SELECT id, username, nama_lengkap, created_at
     FROM users
     WHERE role = "kasir"
     ORDER BY nama_lengkap ASC'
);

$ingredients = $conn->query(
    'SELECT * FROM ingredients ORDER BY nama_bahan ASC'
);

$criticalStmt = $conn->query(
    'SELECT COUNT(*) AS total 
     FROM ingredients 
     WHERE stok_gudang <= batas_kritis'
);
$criticalCount = $criticalStmt->fetch_assoc()['total'];

$peakStmt = $conn->prepare(
    'SELECT
        HOUR(tanggal) AS jam,
        COUNT(*) AS total_order,
        COALESCE(SUM(CASE WHEN status = "paid" THEN total_bayar ELSE 0 END),0) AS sales
     FROM orders
     WHERE tanggal BETWEEN ? AND ?
     GROUP BY HOUR(tanggal)
     ORDER BY total_order DESC, sales DESC
     LIMIT 8'
);
$peakStmt->bind_param('ss', $startDateTime, $endDateTime);
$peakStmt->execute();
$peakHours = $peakStmt->get_result();

$shiftStmt = $conn->prepare(
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
$shiftStmt->bind_param('ss', $startDateTime, $endDateTime);
$shiftStmt->execute();
$shifts = $shiftStmt->get_result();
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manager Outlet - Bento Kopi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
            color: #212529;
            font-family: Arial, sans-serif;
        }

        .card,
        .navbar {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .table thead th {
            background: #f1f3f5;
        }

        .muted {
            color: #6c757d;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>
<nav class="navbar m-3 p-3">
    <div>
        <strong>Manager Outlet Bento Kopi</strong><br>
    </div>

    <div>
        <span class="me-3"><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></span>
        <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
</nav>

<div class="container-fluid px-3 pb-4">
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
        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Total Transaksi</span>
                <h3><?= (int)$summary['total_transaksi'] ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Order Pending</span>
                <h3 class="text-warning"><?= (int)$summary['pending'] ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Sales Lunas</span>
                <h3 class="text-success"><?= rupiah($summary['paid_sales']) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Stok Kritis</span>
                <h3 class="text-danger"><?= (int)$criticalCount ?></h3>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-4">
        <h5>Filter Data Manager</h5>

        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card p-3 mb-4">
                <h5>Kelola Inventory Bahan Baku</h5>

                <form action="process_inventory.php" method="post" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="add">

                    <div class="col-md-3">
                        <input type="text" name="nama_bahan" class="form-control" placeholder="Nama bahan baru" required>
                    </div>

                    <div class="col-md-2">
                        <select name="satuan" class="form-select" required>
                            <option value="gram">gram</option>
                            <option value="ml">ml</option>
                            <option value="pcs">pcs</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <input type="number" name="stok_gudang" class="form-control" placeholder="Stok awal" min="0" step="0.01" required>
                    </div>

                    <div class="col-md-2">
                        <input type="number" name="batas_kritis" class="form-control" placeholder="Batas kritis" min="0" step="0.01" required>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Tambah</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Satuan</th>
                            <th>Stok</th>
                            <th>Batas</th>
                            <th>Tampilan</th>
                            <th>Status</th>
                            <th>Update</th>
                            <th>Restock</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($ing = $ingredients->fetch_assoc()): ?>
                            <tr>
                                <form action="process_inventory.php" method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $ing['id'] ?>">

                                    <td>
                                        <input type="text"
                                               name="nama_bahan"
                                               class="form-control form-control-sm"
                                               value="<?= htmlspecialchars($ing['nama_bahan']) ?>">
                                    </td>

                                    <td>
                                        <select name="satuan" class="form-select form-select-sm">
                                            <option value="gram" <?= $ing['satuan'] === 'gram' ? 'selected' : '' ?>>gram</option>
                                            <option value="ml" <?= $ing['satuan'] === 'ml' ? 'selected' : '' ?>>ml</option>
                                            <option value="pcs" <?= $ing['satuan'] === 'pcs' ? 'selected' : '' ?>>pcs</option>
                                        </select>
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="stok_gudang"
                                               class="form-control form-control-sm"
                                               value="<?= $ing['stok_gudang'] ?>"
                                               step="0.01">
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="batas_kritis"
                                               class="form-control form-control-sm"
                                               value="<?= $ing['batas_kritis'] ?>"
                                               step="0.01">
                                    </td>

                                    <td>
                                        <strong><?= format_stok($ing['stok_gudang'], $ing['satuan']) ?></strong><br>
                                        <small class="text-muted">
                                            Kritis: <?= format_stok($ing['batas_kritis'], $ing['satuan']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?php if ((float)$ing['stok_gudang'] <= 0): ?>
                                            <span class="badge bg-dark">Habis</span>
                                        <?php elseif ((float)$ing['stok_gudang'] <= (float)$ing['batas_kritis']): ?>
                                            <span class="badge bg-danger">Kritis</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Aman</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <button class="btn btn-success btn-sm">Simpan</button>
                                    </td>
                                </form>

                                <td>
                                    <form action="process_inventory.php" method="post" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="restock">
                                        <input type="hidden" name="id" value="<?= $ing['id'] ?>">

                                        <input type="number"
                                               name="jumlah_tambah"
                                               class="form-control form-control-sm"
                                               placeholder="+stok"
                                               min="0"
                                               step="0.01"
                                               required>

                                        <button class="btn btn-outline-primary btn-sm">Restock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="card p-3 mb-4">
                <h5>Order History Outlet</h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
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
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($order['nomor_meja']) ?></strong><br>
                                    <small class="muted">
                                        <?= $order['order_type'] === 'dine_in' ? 'Dine In' : 'Takeaway' ?> ·
                                        <?= date('d/m/Y H.i', strtotime($order['tanggal'])) ?>
                                    </small>
                                </td>
                                <td><?= rupiah($order['total_bayar']) ?></td>
                                <td><?= strtoupper($order['metode_pembayaran']) ?></td>
                                <td>
                                    <?php if ($order['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php elseif ($order['status'] === 'open'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Refunded</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order['kasir']) ?></td>
                            </tr>
                        <?php endwhile; $orderStmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-3">
                <h5>Audit Awal Kasir Berdasarkan Shift</h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Kasir</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Petty Cash</th>
                            <th>Tunai</th>
                            <th>QRIS</th>
                            <th>Estimasi Setor Tunai</th>
                            <th>Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($s = $shifts->fetch_assoc()): ?>
                            <?php $estimasiSetor = (float)$s['petty_cash'] + (float)$s['tunai']; ?>
                            <tr>
                                <td><?= htmlspecialchars($s['kasir']) ?></td>
                                <td><?= date('d/m/Y H.i', strtotime($s['mulai_shift'])) ?></td>
                                <td>
                                    <?= $s['selesai_shift']
                                        ? date('d/m/Y H.i', strtotime($s['selesai_shift']))
                                        : '<span class="text-warning">Masih aktif</span>' ?>
                                </td>
                                <td><?= rupiah($s['petty_cash']) ?></td>
                                <td><?= rupiah($s['tunai']) ?></td>
                                <td><?= rupiah($s['qris']) ?></td>
                                <td><strong><?= rupiah($estimasiSetor) ?></strong></td>
                                <td>
                                    <?= $s['status'] === 'active'
                                        ? '<span class="badge bg-warning">Active</span>'
                                        : '<span class="badge bg-success">Closed</span>' ?>
                                </td>
                            </tr>
                        <?php endwhile; $shiftStmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-3 mb-4">
                <h5>Tambah Kasir Outlet</h5>

                <form action="process_cashier.php" method="post">
                    <div class="mb-2">
                        <label class="form-label">Nama Kasir</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>

                    <button class="btn btn-primary w-100">Tambah Kasir</button>
                </form>

                <hr>

                <h6>Daftar Kasir</h6>

                <ul class="mb-0">
                    <?php while ($c = $cashiers->fetch_assoc()): ?>
                        <li>
                            <?= htmlspecialchars($c['nama_lengkap']) ?>
                            <span class="muted">(@<?= htmlspecialchars($c['username']) ?>)</span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>

            <div class="card p-3 mb-4">
                <h5>Jam Terlaris</h5>

                <?php if ($peakHours->num_rows === 0): ?>
                    <p class="muted mb-0">Belum ada transaksi pada rentang tanggal ini.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Order</th>
                                <th>Sales</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php while ($p = $peakHours->fetch_assoc()): ?>
                                <tr>
                                    <td><?= str_pad($p['jam'], 2, '0', STR_PAD_LEFT) ?>.00</td>
                                    <td><?= (int)$p['total_order'] ?></td>
                                    <td><?= rupiah($p['sales']) ?></td>
                                </tr>
                            <?php endwhile; $peakStmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card p-3">
                <h5>History Pengajuan Refund</h5>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Kasir</th>
                            <th>Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($refund = $refunds->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    #<?= $refund['order_id'] ?><br>
                                    <small><?= rupiah($refund['total_bayar']) ?></small>
                                </td>

                                <td><?= htmlspecialchars($refund['kasir']) ?></td>

                                <td>
                                    <?= $refund['status'] === 'approved'
                                        ? '<span class="badge bg-success">Approved</span>'
                                        : '<span class="badge bg-warning">Pending</span>' ?>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="3">
                                    <small>
                                        <strong>Alasan:</strong>
                                        <?= htmlspecialchars($refund['alasan']) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>