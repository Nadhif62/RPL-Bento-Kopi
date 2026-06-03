<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

$menus = $conn->query('SELECT * FROM menu ORDER BY FIELD(kategori,"beverage","makanan","snack"), nama_menu ASC');
$ingredients = $conn->query('SELECT * FROM ingredients ORDER BY nama_bahan ASC');

$today = date('Y-m-d');
$todayStart = $today . ' 00:00:00';
$todayEnd = $today . ' 23:59:59';

$summaryStmt = $conn->prepare(
    'SELECT
        COUNT(*) AS transaksi,
        COALESCE(SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END), 0) AS gross_sales,
        COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END), 0) AS refunded_sales
     FROM orders
     WHERE user_id = ? AND tanggal BETWEEN ? AND ?'
);
$summaryStmt->bind_param('iss', $userId, $todayStart, $todayEnd);
$summaryStmt->execute();

$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$salesToday = (float)$summary['gross_sales'] - (float)$summary['refunded_sales'];

$tables = range(1, 12);
$openTables = [];

$result = $conn->query("SELECT DISTINCT nomor_meja FROM orders WHERE status = 'open' AND order_type = 'dine_in'");

while ($row = $result->fetch_assoc()) {
    $openTables[] = $row['nomor_meja'];
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kasir Bento Kopi UMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
            color: #212529;
            font-family: Arial, sans-serif;
        }

        .navbar,
        .card,
        .table-box {
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

        .stat {
            min-height: 86px;
        }

        .table-box {
            width: 88px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 700;
        }

        .empty {
            background: #d1e7dd;
            color: #0f5132;
            border-color: #badbcc;
        }

        .filled {
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }

        .menu-item {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            padding: 14px;
            color: #212529;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>
<nav class="navbar m-3 p-3">
    <strong>☕ Bento Kopi POS</strong>

    <div>
        <span class="me-3">Kasir: <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></span>

        <?php if ($shift): ?>
            <span class="badge bg-success me-2">Shift Aktif</span>
        <?php endif; ?>

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

    <?php if (!empty($_SESSION['critical_alerts'])): ?>
        <div class="alert alert-warning">
            <strong>Stok Kritis:</strong>
            <ul class="mb-0">
                <?php foreach ($_SESSION['critical_alerts'] as $alert): ?>
                    <li><?= htmlspecialchars($alert) ?></li>
                <?php endforeach; unset($_SESSION['critical_alerts']); ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$shift): ?>
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card p-4">
                    <h4>Start Shift</h4>
                    <p class="muted">Kasir wajib membuka shift sebelum membuat transaksi.</p>

                    <form action="start_shift.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Petty Cash / Kas Awal</label>
                            <input type="number"
                                   name="petty_cash"
                                   class="form-control"
                                   value="500000"
                                   min="0"
                                   required>
                        </div>

                        <button class="btn btn-success w-100">Mulai Shift</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card stat p-3">
                    <span class="muted">Transaksi Hari Ini</span>
                    <h3><?= (int)$summary['transaksi'] ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat p-3">
                    <span class="muted">Pending Bayar</span>
                    <h3 class="text-warning"><?= (int)$summary['pending'] ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat p-3">
                    <span class="muted">Sales Hari Ini</span>
                    <h3 class="text-success"><?= rupiah($salesToday) ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat p-3">
                    <span class="muted">Petty Cash</span>
                    <h3><?= rupiah($shift['petty_cash']) ?></h3>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <a href="#orderSection" class="btn btn-primary w-100 py-3">✎ Order</a>
            </div>

            <div class="col-md-6">
                <a href="sales.php" class="btn btn-outline-secondary w-100 py-3">▤ Sales / Cek Order</a>
            </div>

            <div class="col-md-6">
                <a href="shift.php" class="btn btn-outline-secondary w-100 py-3">◷ Start / Close Shift</a>
            </div>

            <div class="col-md-6">
                <button type="button" class="btn btn-warning w-100 py-3" id="toggleOfflineBtn">
                    Toggle Offline Mode
                </button>
            </div>
        </div>

        <div class="card p-3 mb-3" id="offlineStatus">
            Mode: Online
        </div>

        <div class="row g-4" id="orderSection">
            <div class="col-lg-8">
                <div class="card p-3 mb-3">
                    <h5>Denah Meja</h5>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php foreach ($tables as $table): ?>
                            <?php $isOpen = in_array('Meja ' . $table, $openTables, true); ?>

                            <div class="table-box <?= $isOpen ? 'filled' : 'empty' ?>"
                                 onclick="document.getElementById('nomor_meja').value='Meja <?= $table ?>'">
                                Meja <?= $table ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card p-3">
                    <h5>Input Order</h5>

                    <form action="process_order.php" method="post" id="orderForm">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipe Order</label>
                                <select name="order_type" class="form-select">
                                    <option value="dine_in">Dine In</option>
                                    <option value="takeaway">Takeaway</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Meja / Nama</label>
                                <input type="text"
                                       name="nomor_meja"
                                       id="nomor_meja"
                                       class="form-control"
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Nama Customer</label>
                                <input type="text"
                                       name="customer_name"
                                       class="form-control"
                                       placeholder="Opsional">
                            </div>
                        </div>

                        <div class="row g-3">
                            <?php while ($menu = $menus->fetch_assoc()): ?>
                                <div class="col-md-4">
                                    <div class="menu-item">
                                        <strong><?= htmlspecialchars($menu['nama_menu']) ?></strong><br>
                                        <span class="text-success"><?= rupiah($menu['harga']) ?></span>

                                        <input type="number"
                                               name="items[<?= $menu['id'] ?>]"
                                               data-menu-id="<?= $menu['id'] ?>"
                                               class="form-control item-qty mt-2"
                                               min="0"
                                               value="0">
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Metode Pembayaran</label>
                                <select name="metode_pembayaran" id="metode_pembayaran" class="form-select">
                                    <option value="tunai">Tunai / Cash</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Nominal Diterima</label>
                                <input type="number"
                                       name="nominal_diterima"
                                       id="nominal_diterima"
                                       class="form-control"
                                       min="0"
                                       value="0">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="paid">Lunas</option>
                                    <option value="open">Pending / Open Bill</option>
                                </select>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100 mt-3" id="payBtn">
                            Bayar / Simpan Order
                        </button>

                        <button type="button" class="btn btn-secondary w-100 mt-2" id="syncBtn">
                            Sync Offline Queue
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-3 mb-3">
                    <h5>Stok Bahan Baku</h5>

                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Stok</th>
                            <th>Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php while ($ing = $ingredients->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($ing['nama_bahan']) ?></td>
                                <td><?= $ing['stok_gudang'] ?></td>
                                <td>
                                    <?= ((float)$ing['stok_gudang'] <= (float)$ing['batas_kritis'])
                                        ? '<span class="badge bg-danger">Kritis</span>'
                                        : '<span class="badge bg-success">Aman</span>' ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card p-3">
                    <h5>Ajukan Refund</h5>

                    <form action="request_refund.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Order ID</label>
                            <input type="number" name="order_id" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alasan</label>
                            <textarea name="alasan" class="form-control" required></textarea>
                        </div>

                        <button class="btn btn-outline-danger w-100">Ajukan Refund</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="offline_handler.js"></script>

<script>
document.getElementById('metode_pembayaran')?.addEventListener('change', function () {
    const input = document.getElementById('nominal_diterima');

    if (this.value === 'qris') {
        input.value = 0;
        input.setAttribute('readonly', 'readonly');
    } else {
        input.removeAttribute('readonly');
    }
});
</script>
</body>
</html>