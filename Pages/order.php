<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);

if (!$shift) {
    $_SESSION['flash_error'] = 'Shift belum aktif. Start shift terlebih dahulu.';
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$type = $_GET['type'] ?? 'dine_in';
if (!in_array($type, ['dine_in', 'takeaway'], true)) {
    $type = 'dine_in';
}

$selectedTable = trim($_GET['table'] ?? '');

$tables = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'dining_tables'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $tableResult = $conn->query('SELECT table_number, table_label FROM dining_tables WHERE is_active = 1 ORDER BY id ASC');
    while ($row = $tableResult->fetch_assoc()) {
        $tables[] = $row;
    }
}

if (empty($tables)) {
    for ($i = 1; $i <= 12; $i++) {
        $number = 'Meja ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $tables[] = [
            'table_number' => $number,
            'table_label' => $number
        ];
    }
}

$openBills = [];
$openStmt = $conn->prepare(
    'SELECT id, nomor_meja, customer_name, total_bayar, tanggal
     FROM orders
     WHERE order_type = "dine_in" AND status = "open"
     ORDER BY id ASC'
);
$openStmt->execute();
$openResult = $openStmt->get_result();
while ($order = $openResult->fetch_assoc()) {
    $openBills[$order['nomor_meja']] = [
        'id' => (int)$order['id'],
        'nomor_meja' => $order['nomor_meja'],
        'customer_name' => $order['customer_name'],
        'total_bayar' => (float)$order['total_bayar'],
        'tanggal' => $order['tanggal'],
        'details' => []
    ];
}
$openStmt->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Order - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Order Baru</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <div class="type-tabs">
        <a href="order.php?type=dine_in" class="type-tab <?= $type === 'dine_in' ? 'active' : '' ?>">▱ Dine In</a>
        <a href="order.php?type=takeaway" class="type-tab <?= $type === 'takeaway' ? 'active' : '' ?>">⌂ Takeaway</a>
    </div>

    <form id="orderSetupForm" action="menu.php" method="post" data-order-type="<?= htmlspecialchars($type) ?>" data-initial-table="<?= htmlspecialchars($selectedTable) ?>">
        <input type="hidden" name="order_type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" id="selectedTableInput" name="nomor_meja" value="<?= $type === 'dine_in' ? htmlspecialchars($selectedTable) : '' ?>">
        <input type="hidden" id="openOrderIdInput" name="open_order_id" value="0">

        <section class="app-card mb-3">
            <h5 class="mb-3 fw-bold">Informasi Pesanan</h5>
            <?php if ($type === 'dine_in'): ?>
                <div class="order-info-grid">
                    <div class="order-field compact-field">
                        <label class="form-label">Nama / Identitas Pelanggan</label>
                        <input id="setupCustomerNameInput" type="text" name="customer_name" class="form-control" placeholder="Contoh: Rara" required>
                    </div>
                    <div class="order-field compact-field">
                        <label class="form-label">Meja Dipilih</label>
                        <div id="selectedTableText" class="selected-table-box">Belum memilih meja</div>
                    </div>
                </div>

                <div class="table-grid mt-3" id="tableGrid">
                    <?php foreach ($tables as $table): ?>
                        <?php
                            $number = $table['table_number'];
                            $label = $table['table_label'] ?: $number;
                            $bill = $openBills[$number] ?? null;
                            $busy = $bill !== null;
                        ?>
                        <button type="button"
                                class="table-card <?= $busy ? 'busy' : 'free' ?>"
                                data-table-number="<?= htmlspecialchars($number) ?>"
                                data-open-order-id="<?= $busy ? (int)$bill['id'] : 0 ?>"
                                data-customer-name="<?= $busy ? htmlspecialchars($bill['customer_name'] ?? '') : '' ?>">
                            <span class="table-label"><?= htmlspecialchars($label) ?></span>
                            <span class="table-status"><?= $busy ? 'Open Bill' : 'Tersedia' ?></span>
                            <?php if ($busy): ?>
                                <span class="table-customer"><?= htmlspecialchars($bill['customer_name'] ?: '-') ?></span>
                                <span class="table-total"><?= rupiah($bill['total_bayar']) ?></span>
                            <?php else: ?>
                                <span class="table-total">Kosong</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="order-info-grid single">
                    <div class="order-field compact-field">
                        <label class="form-label">Nama / Identitas Pelanggan</label>
                        <input id="setupCustomerNameInput" type="text" name="customer_name" class="form-control" placeholder="Contoh: Rara" required>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <button id="goMenuBtn" class="btn btn-dark-outline w-100 py-3">Lanjut Pilih Menu →</button>
    </form>
</div>
<script>
window.BENTO_OPEN_BILLS = <?= json_encode($openBills, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.BENTO_TABLES = <?= json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../Assets/JS/offline_handler.js"></script>
<script src="../Assets/JS/app.js"></script>
</body>
</html>
