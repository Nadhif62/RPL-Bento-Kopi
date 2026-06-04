<?php
require_once 'config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

$menus = $conn->query(
    'SELECT * FROM menu 
     ORDER BY FIELD(kategori,"beverage","makanan","snack"), nama_menu ASC'
);

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

$result = $conn->query(
    "SELECT DISTINCT nomor_meja 
     FROM orders 
     WHERE status = 'open' AND order_type = 'dine_in'"
);

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

        .card,
        .navbar,
        .table-box {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
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
        }

        .filled {
            background: #f8d7da;
            color: #842029;
        }

        .selected-table {
            outline: 3px solid #0d6efd;
        }

        .menu-item {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .table thead th {
            background: #f1f3f5;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>
<nav class="navbar m-3 p-3">
    <strong>Bento Kopi POS</strong>

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
                    <p class="text-muted">Kasir wajib membuka shift sebelum membuat transaksi.</p>

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
                <div class="card p-3">
                    <span class="text-muted">Transaksi Hari Ini</span>
                    <h3><?= (int)$summary['transaksi'] ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted">Pending Bayar</span>
                    <h3 class="text-warning"><?= (int)$summary['pending'] ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted">Sales Hari Ini</span>
                    <h3 class="text-success"><?= rupiah($salesToday) ?></h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card p-3">
                    <span class="text-muted">Petty Cash</span>
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
                            <?php
                            $tableName = 'Meja ' . $table;
                            $isOpen = in_array($tableName, $openTables, true);
                            ?>

                            <div class="table-box <?= $isOpen ? 'filled' : 'empty' ?>"
                                 data-table="<?= htmlspecialchars($tableName) ?>"
                                 onclick="selectTable('<?= htmlspecialchars($tableName) ?>', this)">
                                Meja <?= $table ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card p-3 mb-3 d-none" id="openBillCard">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h5 class="mb-0">Open Bill Aktif</h5>
                            <small class="text-muted" id="openBillInfo"></small>
                        </div>

                        <span class="badge bg-warning text-dark">Belum Dibayar</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                            </thead>

                            <tbody id="openBillDetails"></tbody>
                        </table>
                    </div>

                    <h5 class="text-end mb-0">
                        Total Bill: <span id="openBillTotal">Rp 0</span>
                    </h5>

                    <small class="text-muted">
                        Input jumlah menu di bawah hanya untuk pesanan tambahan.
                    </small>
                </div>

                <div class="card p-3">
                    <h5>Input Order / Tambah Pesanan</h5>

                    <form action="process_order.php" method="post" id="orderForm">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipe Order</label>
                                <select name="order_type" id="order_type" class="form-select">
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
                                       id="customer_name"
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
                                               data-menu-name="<?= htmlspecialchars($menu['nama_menu']) ?>"
                                               data-menu-price="<?= $menu['harga'] ?>"
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
                                <select name="status" id="status" class="form-select">
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

                    <table class="table table-sm align-middle">
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

                                <td>
                                    <strong><?= format_stok($ing['stok_gudang'], $ing['satuan']) ?></strong><br>
                                    <small class="text-muted">
                                        Batas: <?= format_stok($ing['batas_kritis'], $ing['satuan']) ?>
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
const MODE_KEY_LOCAL = 'bento_offline_mode_v4';
const OPEN_BILL_CACHE_KEY_LOCAL = 'bento_open_bills_cache_v1';

function isOfflineModeLocal() {
    return localStorage.getItem(MODE_KEY_LOCAL) === '1';
}

function getOpenBillCacheLocal() {
    return JSON.parse(localStorage.getItem(OPEN_BILL_CACHE_KEY_LOCAL) || '[]');
}

function formatRupiah(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(value);
}

function resetAdditionInputs() {
    document.querySelectorAll('.item-qty').forEach(input => {
        input.value = 0;
    });
}

function hasSelectedMenu() {
    let selected = false;

    document.querySelectorAll('.item-qty').forEach(input => {
        const qty = parseInt(input.value, 10);

        if (qty > 0) {
            selected = true;
        }
    });

    return selected;
}

function setPaymentReadonly() {
    const metode = document.getElementById('metode_pembayaran');
    const nominal = document.getElementById('nominal_diterima');

    if (!metode || !nominal) return;

    if (metode.value === 'qris') {
        nominal.value = 0;
        nominal.setAttribute('readonly', 'readonly');
    } else {
        nominal.removeAttribute('readonly');
    }
}

function renderNoOpenBill() {
    const openBillCard = document.getElementById('openBillCard');
    const openBillInfo = document.getElementById('openBillInfo');
    const openBillDetails = document.getElementById('openBillDetails');
    const openBillTotal = document.getElementById('openBillTotal');

    openBillCard.classList.add('d-none');
    openBillDetails.innerHTML = '';
    openBillTotal.textContent = 'Rp 0';
    openBillInfo.textContent = '';

    document.getElementById('status').value = 'paid';
    document.getElementById('metode_pembayaran').value = 'tunai';
    document.getElementById('customer_name').value = '';
    document.getElementById('nominal_diterima').value = 0;

    setPaymentReadonly();
}

function renderOpenBill(data, sourceLabel = 'Online') {
    const order = data.order;

    document.getElementById('status').value = order.status;
    document.getElementById('metode_pembayaran').value = order.metode_pembayaran;
    document.getElementById('customer_name').value = order.customer_name || '';
    document.getElementById('nominal_diterima').value = 0;

    setPaymentReadonly();

    const openBillCard = document.getElementById('openBillCard');
    const openBillInfo = document.getElementById('openBillInfo');
    const openBillDetails = document.getElementById('openBillDetails');
    const openBillTotal = document.getElementById('openBillTotal');

    openBillDetails.innerHTML = '';

    openBillInfo.textContent =
        sourceLabel +
        ' · Order #' + order.id +
        ' · ' + order.nomor_meja +
        ' · Metode: ' + order.metode_pembayaran.toUpperCase();

    data.details.forEach(item => {
        const tr = document.createElement('tr');

        tr.innerHTML = `
            <td>${item.nama_menu}</td>
            <td>x${item.jumlah}</td>
            <td>${formatRupiah(item.subtotal)}</td>
        `;

        openBillDetails.appendChild(tr);
    });

    openBillTotal.textContent = formatRupiah(order.total_bayar);
    openBillCard.classList.remove('d-none');
}

async function loadOpenBillForTable(tableName, forceOffline = false) {
    const openBillCard = document.getElementById('openBillCard');
    const openBillInfo = document.getElementById('openBillInfo');
    const openBillDetails = document.getElementById('openBillDetails');
    const openBillTotal = document.getElementById('openBillTotal');

    openBillCard.classList.add('d-none');
    openBillDetails.innerHTML = '';
    openBillTotal.textContent = 'Rp 0';
    openBillInfo.textContent = '';

    if (isOfflineModeLocal() || forceOffline) {
        const cache = getOpenBillCacheLocal();

        const cachedBill = cache.find(item => {
            return item.order.nomor_meja === tableName &&
                item.order.status === 'open';
        });

        if (!cachedBill) {
            renderNoOpenBill();
            return;
        }

        renderOpenBill(cachedBill, 'Offline Cache');
        return;
    }

    try {
        const response = await fetch('get_open_bill.php?nomor_meja=' + encodeURIComponent(tableName));
        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Gagal mengambil open bill.');
            return;
        }

        if (!data.has_open_bill) {
            renderNoOpenBill();
            return;
        }

        renderOpenBill({
            order: data.order,
            details: data.details
        }, 'Online');
    } catch (error) {
        alert('Gagal membaca open bill. Pastikan server aktif.');
    }
}

async function selectTable(tableName, element) {
    document.querySelectorAll('.table-box').forEach(box => {
        box.classList.remove('selected-table');
    });

    if (element) {
        element.classList.add('selected-table');
    }

    document.getElementById('nomor_meja').value = tableName;
    document.getElementById('order_type').value = 'dine_in';

    resetAdditionInputs();

    await loadOpenBillForTable(tableName);
}

window.loadOpenBillForTable = loadOpenBillForTable;

document.getElementById('metode_pembayaran')?.addEventListener('change', setPaymentReadonly);

document.getElementById('orderForm')?.addEventListener('submit', function (event) {
    if (event.defaultPrevented) {
        return;
    }

    const nomorMeja = document.getElementById('nomor_meja').value.trim();

    if (nomorMeja === '') {
        event.preventDefault();
        alert('Meja atau nama pelanggan wajib diisi.');
        return;
    }

    if (!hasSelectedMenu()) {
        event.preventDefault();
        alert('Belum ada menu yang dipesan. Isi jumlah minimal 1 pada salah satu menu.');
        return;
    }
});

setPaymentReadonly();
</script>
</body>
</html>