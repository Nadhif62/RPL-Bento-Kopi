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

$orderType = $_POST['order_type'] ?? $_GET['type'] ?? 'dine_in';
if (!in_array($orderType, ['dine_in', 'takeaway'], true)) {
    $orderType = 'dine_in';
}

$customerName = trim($_POST['customer_name'] ?? $_GET['customer_name'] ?? '');
$nomorMeja = trim($_POST['nomor_meja'] ?? $_GET['table'] ?? '');
$openOrderId = (int)($_POST['open_order_id'] ?? $_GET['open_order_id'] ?? 0);

$menus = [];
$menusResult = $conn->query(
    'SELECT * FROM menu
     ORDER BY FIELD(kategori,"promo","beverage","makanan","snack"), nama_menu ASC'
);
while ($menu = $menusResult->fetch_assoc()) {
    $menus[] = $menu;
}

$categoryLabels = [
    'promo' => 'Promo',
    'beverage' => 'Minuman',
    'makanan' => 'Makanan',
    'snack' => 'Snack',
];
$availableCategories = [];
foreach ($menus as $menuItem) {
    $category = (string)$menuItem['kategori'];
    if (isset($categoryLabels[$category]) && !in_array($category, $availableCategories, true)) {
        $availableCategories[] = $category;
    }
}
$defaultCategory = $availableCategories[0] ?? 'beverage';

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
    $orderId = (int)$order['id'];
    $openBills[$order['nomor_meja']] = [
        'id' => $orderId,
        'nomor_meja' => $order['nomor_meja'],
        'customer_name' => $order['customer_name'],
        'total_bayar' => (float)$order['total_bayar'],
        'tanggal' => $order['tanggal'],
        'details' => []
    ];
}
$openStmt->close();

if (!empty($openBills)) {
    $detailStmt = $conn->prepare(
        'SELECT od.menu_id, m.nama_menu, od.jumlah, od.harga_satuan, od.subtotal
         FROM order_details od
         JOIN menu m ON od.menu_id = m.id
         WHERE od.order_id = ?
         ORDER BY od.id ASC'
    );

    foreach ($openBills as $tableNumber => $bill) {
        $orderId = (int)$bill['id'];
        $detailStmt->bind_param('i', $orderId);
        $detailStmt->execute();
        $detailResult = $detailStmt->get_result();
        while ($detail = $detailResult->fetch_assoc()) {
            $openBills[$tableNumber]['details'][] = [
                'menu_id' => (int)$detail['menu_id'],
                'nama_menu' => $detail['nama_menu'],
                'jumlah' => (int)$detail['jumlah'],
                'harga_satuan' => (float)$detail['harga_satuan'],
                'subtotal' => (float)$detail['subtotal']
            ];
        }
    }
    $detailStmt->close();
}

if ($orderType === 'dine_in') {
    if ($nomorMeja === '') {
        $_SESSION['flash_error'] = 'Pilih meja terlebih dahulu.';
        header('Location: ' . app_url('Pages/order.php?type=dine_in'));
        exit;
    }

    $activeBill = $openBills[$nomorMeja] ?? null;
    if ($activeBill) {
        $openOrderId = (int)$activeBill['id'];
        if ($customerName === '') {
            $customerName = (string)($activeBill['customer_name'] ?? '');
        }
    }
} else {
    $nomorMeja = '';
    $openOrderId = 0;
}

if ($customerName === '') {
    $_SESSION['flash_error'] = 'Identitas pelanggan wajib diisi.';
    header('Location: ' . app_url('Pages/order.php?type=' . $orderType));
    exit;
}

$backUrl = 'order.php?type=' . urlencode($orderType);
if ($orderType === 'dine_in' && $nomorMeja !== '') {
    $backUrl .= '&table=' . urlencode($nomorMeja);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pilih Menu - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-dark-outline btn-sm">← Kembali</a>
        <div class="page-title">Pilih Menu</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <form id="menuOrderForm" action="payment.php" method="post" data-order-type="<?= htmlspecialchars($orderType) ?>" data-initial-table="<?= htmlspecialchars($nomorMeja) ?>">
        <input type="hidden" name="order_type" value="<?= htmlspecialchars($orderType) ?>">
        <input type="hidden" id="selectedTableInput" name="nomor_meja" value="<?= htmlspecialchars($nomorMeja) ?>">
        <input type="hidden" id="openOrderIdInput" name="open_order_id" value="<?= (int)$openOrderId ?>">
        <input type="hidden" id="localOfflineTotalInput" name="local_offline_total" value="0">
        <input type="hidden" id="customerNameInput" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">

        <section class="app-card mb-3 compact-order-head">
            <div class="compact-order-item">
                <div class="compact-order-label">Pesanan</div>
                <strong class="compact-order-value"><?= htmlspecialchars($orderType === 'dine_in' ? 'Dine In - ' . $nomorMeja : 'Takeaway') ?></strong>
            </div>
            <div class="compact-order-item text-md-end">
                <div class="compact-order-label">Pelanggan</div>
                <strong class="compact-order-value"><?= htmlspecialchars($customerName) ?></strong>
            </div>
        </section>

        <?php if ($orderType === 'dine_in'): ?>
            <section id="currentBillBox" class="current-bill-box app-card mb-3" style="display:none;">
                <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                    <div>
                        <strong id="currentBillTitle">Open Bill</strong>
                        <div id="currentBillCustomer" class="muted"></div>
                        <div id="currentBillNotice" class="form-text"></div>
                    </div>
                    <div class="text-end">
                        <div class="muted">Tagihan Lama</div>
                        <strong id="existingBillTotal">Rp 0</strong>
                    </div>
                </div>
                <div id="existingBillDetails" class="summary-list mt-3"></div>
            </section>
        <?php endif; ?>

        <section class="menu-layout">
            <div class="app-card">
                <div class="category-tabs">
                    <?php foreach ($availableCategories as $category): ?>
                        <button type="button" class="category-tab <?= $category === $defaultCategory ? 'active' : '' ?>" data-category-filter="<?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($categoryLabels[$category]) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="menu-scroll">
                    <div class="menu-grid">
                    <?php foreach ($menus as $menu): ?>
                        <?php $inputId = 'qty_' . (int)$menu['id']; ?>
                        <article class="menu-card" data-category="<?= htmlspecialchars($menu['kategori']) ?>" style="<?= $menu['kategori'] === $defaultCategory ? '' : 'display:none;' ?>">
                            <div class="menu-name"><?= htmlspecialchars($menu['nama_menu']) ?></div>
                            <div class="menu-price"><?= rupiah($menu['harga']) ?></div>
                            <div class="qty-control">
                                <button type="button" class="btn btn-dark-outline" data-qty-action="minus" data-target="<?= $inputId ?>">−</button>
                                <input id="<?= $inputId ?>"
                                       type="number"
                                       class="form-control item-qty"
                                       value="0"
                                       min="0"
                                       data-menu-id="<?= (int)$menu['id'] ?>"
                                       data-menu-name="<?= htmlspecialchars($menu['nama_menu']) ?>"
                                       data-menu-price="<?= (float)$menu['harga'] ?>">
                                <button type="button" class="btn btn-dark-outline" data-qty-action="plus" data-target="<?= $inputId ?>">+</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <aside class="summary-card app-card sticky-summary">
                <h5 class="mb-3 fw-bold">▤ Pesanan</h5>

                <div class="summary-scroll">
                    <div id="emptyOrderText" class="muted">Belum ada menu dipilih.</div>
                    <div id="orderSummaryList" class="summary-list"></div>
                </div>

                <div class="summary-footer">
                    <?php if ($orderType === 'dine_in'): ?>
                        <div class="summary-row border-0 py-1">
                            <span>Tagihan Lama</span>
                            <strong id="summaryExistingTotal">Rp 0</strong>
                        </div>
                        <div class="summary-row border-0 py-1">
                            <span>Tambahan</span>
                            <strong id="summaryAdditionalTotal">Rp 0</strong>
                        </div>
                    <?php endif; ?>
                    <div class="summary-total">
                        <span><?= $orderType === 'dine_in' ? 'Total Nanti' : 'Total' ?></span>
                        <span id="orderSummaryTotal" class="price">Rp 0</span>
                    </div>
                    <div id="hiddenItemsContainer"></div>
                    <button id="goPaymentBtn" class="btn btn-dark-outline w-100 mt-3" disabled>Lanjut ke Pembayaran →</button>
                </div>
            </aside>
        </section>
    </form>
</div>
<script>
window.BENTO_OPEN_BILLS = <?= json_encode($openBills, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../Assets/JS/offline_handler.js"></script>
<script src="../Assets/JS/app.js"></script>
</body>
</html>
