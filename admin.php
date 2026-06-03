<?php
require_once 'config.php';
require_login(['admin', 'finance']);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$startDateTime = $start . ' 00:00:00';
$endDateTime = $end . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END),0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END),0) AS refund_total,
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END),0) AS pending
     FROM orders
     WHERE tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('ss', $startDateTime, $endDateTime);
$summaryStmt->execute();

$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$netSales = (float)$summary['gross_sales'] - (float)$summary['refund_total'];

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
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dashboard Admin Bento Kopi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
            color: #212529;
            font-family: Arial, sans-serif;
        }

        .navbar,
        .card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            color: #212529;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .form-control,
        .form-select {
            background: #ffffff;
            color: #212529;
            border-color: #ced4da;
        }

        .form-control:focus,
        .form-select:focus {
            background: #ffffff;
            color: #212529;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
        }

        .table {
            --bs-table-bg: #ffffff;
            --bs-table-color: #212529;
            --bs-table-border-color: #dee2e6;
        }

        .table thead th {
            background: #f1f3f5;
            color: #495057;
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
    <strong>☕ Dashboard Admin / Finance</strong>
    <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
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
                <span class="muted">Gross Sales</span>
                <h3><?= rupiah($summary['gross_sales']) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Refund Approved</span>
                <h3 class="text-danger">- <?= rupiah($summary['refund_total']) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Net Sales</span>
                <h3 class="text-success"><?= rupiah($netSales) ?></h3>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <span class="muted">Pending</span>
                <h3 class="text-warning"><?= (int)$summary['pending'] ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card p-3 mb-4">
                <h5>Filter Riwayat Transaksi</h5>

                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Mulai</label>
                        <input type="date"
                               name="start"
                               value="<?= htmlspecialchars($start) ?>"
                               class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Akhir</label>
                        <input type="date"
                               name="end"
                               value="<?= htmlspecialchars($end) ?>"
                               class="form-control">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>

            <div class="card p-3">
                <h5>Order History</h5>

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
                            <th>Detail</th>
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
                                        <?= date('H.i', strtotime($order['tanggal'])) ?>
                                    </small>
                                </td>

                                <td>
                                    <?php if ($order['status'] === 'refunded'): ?>
                                        <span class="text-decoration-line-through muted">
                                            <?= rupiah($order['total_bayar']) ?>
                                        </span><br>
                                        <strong class="text-danger">
                                            -<?= rupiah($order['total_bayar']) ?>
                                        </strong>
                                    <?php else: ?>
                                        <strong><?= rupiah($order['total_bayar']) ?></strong>
                                    <?php endif; ?>
                                </td>

                                <td><?= strtoupper($order['metode_pembayaran']) ?></td>

                                <td>
                                    <?php if ($order['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'open'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>

                                    <?php if ($order['status'] === 'refunded'): ?>
                                        <span class="badge bg-danger">Refunded</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($order['kasir']) ?></td>

                                <td>
                                    <?php
                                    $detail = $conn->prepare(
                                        'SELECT m.nama_menu, od.jumlah, od.subtotal
                                         FROM order_details od
                                         JOIN menu m ON od.menu_id = m.id
                                         WHERE od.order_id = ?'
                                    );
                                    $detail->bind_param('i', $order['id']);
                                    $detail->execute();

                                    $detailRows = $detail->get_result();

                                    while ($d = $detailRows->fetch_assoc()) {
                                        echo htmlspecialchars($d['nama_menu']) .
                                            ' x' . (int)$d['jumlah'] .
                                            ' — ' . rupiah($d['subtotal']) . '<br>';
                                    }

                                    $detail->close();
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; $orderStmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-3 mb-4">
                <h5>Tambah Kasir</h5>

                <form action="process_cashier.php" method="post">
                    <div class="mb-2">
                        <label class="form-label">Nama Kasir</label>
                        <input type="text"
                               name="nama_lengkap"
                               class="form-control"
                               required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Username</label>
                        <input type="text"
                               name="username"
                               class="form-control"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password"
                               name="password"
                               class="form-control"
                               minlength="6"
                               required>
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

            <div class="card p-3">
                <h5>Pengajuan Refund</h5>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($refund = $refunds->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    #<?= $refund['order_id'] ?><br>
                                    <small><?= rupiah($refund['total_bayar']) ?></small>
                                </td>

                                <td><?= htmlspecialchars($refund['alasan']) ?></td>

                                <td>
                                    <?= $refund['status'] === 'approved'
                                        ? '<span class="badge bg-success">Approved</span>'
                                        : '<span class="badge bg-warning">Pending</span>' ?>
                                </td>

                                <td>
                                    <?php if ($refund['status'] === 'pending'): ?>
                                        <form action="process_refund.php"
                                              method="post"
                                              onsubmit="return confirm('Setujui refund dan kurangi pendapatan?')">
                                            <input type="hidden" name="refund_id" value="<?= $refund['id'] ?>">
                                            <button class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <small>Selesai</small>
                                    <?php endif; ?>
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