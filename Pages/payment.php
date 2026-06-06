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

$orderType = $_POST['order_type'] ?? 'dine_in';
if (!in_array($orderType, ['dine_in', 'takeaway'], true)) {
    $orderType = 'dine_in';
}

$customerName = trim($_POST['customer_name'] ?? '');
$nomorMeja = trim($_POST['nomor_meja'] ?? '');
$openOrderId = (int)($_POST['open_order_id'] ?? 0);
$localOfflineTotal = isset($_POST['local_offline_total']) ? (float)$_POST['local_offline_total'] : 0;
$forceOpenBecauseOfflineQueue = $localOfflineTotal > 0;

if ($orderType === 'takeaway') {
    if ($customerName === '') {
        $_SESSION['flash_error'] = 'Nama pelanggan takeaway wajib diisi.';
        header('Location: ' . app_url('Pages/order.php?type=takeaway'));
        exit;
    }
    $nomorMeja = 'Takeaway - ' . $customerName;
    $openOrderId = 0;
} else {
    if ($nomorMeja === '') {
        $_SESSION['flash_error'] = 'Nomor meja dine in wajib dipilih.';
        header('Location: ' . app_url('Pages/order.php?type=dine_in'));
        exit;
    }
}

$existingOrder = null;
$existingDetails = [];
$existingTotal = 0;

if ($orderType === 'dine_in' && $openOrderId > 0) {
    $openStmt = $conn->prepare(
        'SELECT id, nomor_meja, customer_name, total_bayar, tanggal
         FROM orders
         WHERE id = ?
           AND nomor_meja = ?
           AND order_type = "dine_in"
           AND status = "open"
         LIMIT 1'
    );
    $openStmt->bind_param('is', $openOrderId, $nomorMeja);
    $openStmt->execute();
    $existingOrder = $openStmt->get_result()->fetch_assoc();
    $openStmt->close();

    if (!$existingOrder) {
        $_SESSION['flash_error'] = 'Open bill pada meja ini tidak ditemukan atau sudah lunas.';
        header('Location: ' . app_url('Pages/order.php?type=dine_in'));
        exit;
    }

    $existingTotal = (float)$existingOrder['total_bayar'];
    if ($customerName === '' && !empty($existingOrder['customer_name'])) {
        $customerName = $existingOrder['customer_name'];
    }

    $detailStmt = $conn->prepare(
        'SELECT m.nama_menu, od.jumlah, od.harga_satuan, od.subtotal
         FROM order_details od
         JOIN menu m ON od.menu_id = m.id
         WHERE od.order_id = ?
         ORDER BY od.id ASC'
    );
    $detailStmt->bind_param('i', $openOrderId);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();
    while ($row = $detailResult->fetch_assoc()) {
        $existingDetails[] = $row;
    }
    $detailStmt->close();
}

if ($customerName === '') {
    $_SESSION['flash_error'] = 'Identitas/nama pelanggan wajib diisi.';
    header('Location: ' . app_url('Pages/order.php?type=' . $orderType));
    exit;
}

$rawItems = $_POST['items'] ?? [];
$items = [];
foreach ($rawItems as $menuId => $qty) {
    $menuId = (int)$menuId;
    $qty = (int)$qty;
    if ($menuId > 0 && $qty > 0) {
        $items[$menuId] = ($items[$menuId] ?? 0) + $qty;
    }
}

if (empty($items) && !$existingOrder) {
    $_SESSION['flash_error'] = 'Pilih minimal satu menu terlebih dahulu.';
    header('Location: ' . app_url('Pages/order.php?type=' . $orderType));
    exit;
}

$details = [];
$additionalTotal = 0;

if (!empty($items)) {
    $menuStmt = $conn->prepare('SELECT id, nama_menu, harga FROM menu WHERE id = ?');

    foreach ($items as $menuId => $qty) {
        $menuStmt->bind_param('i', $menuId);
        $menuStmt->execute();
        $menu = $menuStmt->get_result()->fetch_assoc();

        if ($menu) {
            $subtotal = (float)$menu['harga'] * $qty;
            $additionalTotal += $subtotal;
            $details[] = [
                'id' => (int)$menu['id'],
                'nama_menu' => $menu['nama_menu'],
                'harga' => (float)$menu['harga'],
                'qty' => $qty,
                'subtotal' => $subtotal,
            ];
        }
    }
    $menuStmt->close();
}

if (empty($details) && !$existingOrder) {
    $_SESSION['flash_error'] = 'Data menu tidak ditemukan.';
    header('Location: ' . app_url('Pages/order.php?type=' . $orderType));
    exit;
}

$grandTotal = $existingTotal + $additionalTotal;
if ($forceOpenBecauseOfflineQueue) {
    // Antrean offline belum masuk database, jadi pembayaran lunas dinonaktifkan agar kasir tidak menagih total yang belum lengkap.
}
$backUrl = 'order.php?type=' . urlencode($orderType);
if ($orderType === 'dine_in') {
    $backUrl .= '&table=' . urlencode($nomorMeja);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pembayaran - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-dark-outline btn-sm">← Kembali</a>
        <div class="page-title">Pembayaran</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <form id="orderFinalizeForm" action="../Actions/process_order.php" method="post" data-total="<?= (float)$grandTotal ?>">
        <input type="hidden" name="return_success" value="1">
        <input type="hidden" name="order_type" value="<?= htmlspecialchars($orderType) ?>">
        <input type="hidden" name="open_order_id" value="<?= (int)$openOrderId ?>">
        <input type="hidden" name="nomor_meja" value="<?= htmlspecialchars($nomorMeja) ?>">
        <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
        <?php foreach ($details as $item): ?>
            <input type="hidden" name="items[<?= (int)$item['id'] ?>]" value="<?= (int)$item['qty'] ?>">
        <?php endforeach; ?>

        <div class="row g-3">
            <div class="col-lg-6">
                <section class="app-card h-100">
                    <h5 class="mb-3">Ringkasan Pesanan</h5>

                    <?php if ($existingOrder): ?>
                        <div class="alert alert-warning">
                            <strong>Open Bill Aktif</strong><br>
                            <?= htmlspecialchars($nomorMeja) ?> sudah punya tagihan terbuka sebesar <strong><?= rupiah($existingTotal) ?></strong>.
                        </div>
                        <div class="muted mb-2">Pesanan sebelumnya</div>
                        <div class="summary-list">
                            <?php foreach ($existingDetails as $item): ?>
                                <div class="summary-row">
                                    <span><?= htmlspecialchars($item['nama_menu']) ?> <strong>x<?= (int)$item['jumlah'] ?></strong></span>
                                    <strong><?= rupiah($item['subtotal']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="muted mb-2"><?= $existingOrder ? 'Tambahan pesanan' : 'Pesanan baru' ?></div>
                    <div class="summary-list">
                        <?php if (empty($details)): ?>
                            <div class="muted">Tidak ada tambahan menu. Tagihan lama akan diproses.</div>
                        <?php endif; ?>
                        <?php foreach ($details as $item): ?>
                            <div class="summary-row">
                                <span><?= htmlspecialchars($item['nama_menu']) ?> <strong>x<?= (int)$item['qty'] ?></strong></span>
                                <strong><?= rupiah($item['subtotal']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($existingOrder): ?>
                        <div class="summary-row mt-3">
                            <span>Tagihan sebelumnya</span>
                            <strong><?= rupiah($existingTotal) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Tambahan</span>
                            <strong><?= rupiah($additionalTotal) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="summary-total mt-3">
                        <span>Total Bayar</span>
                        <span class="price"><?= rupiah($grandTotal) ?></span>
                    </div>
                    <div class="app-card mt-3 p-3">
                        <div class="muted mb-1">Info</div>
                        <strong>
                            <?= $orderType === 'dine_in' ? 'Dine In' : 'Takeaway' ?> —
                            <?= htmlspecialchars($orderType === 'dine_in' ? $nomorMeja : $customerName) ?>
                        </strong>
                        <div class="muted">Atas nama: <?= htmlspecialchars($customerName) ?></div>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="app-card mb-3">
                    <h5 class="mb-3">Status Pembayaran</h5>
                    <?php if ($orderType === 'takeaway'): ?>
                        <input type="hidden" name="status" value="paid">
                        <div class="status-pill"><strong>Lunas</strong></div>
                    <?php else: ?>
                        <?php if ($forceOpenBecauseOfflineQueue): ?>
                            <div class="alert alert-warning">
                                Antrean offline: <strong><?= rupiah($localOfflineTotal) ?></strong>. Sync dulu sebelum lunas.
                            </div>
                        <?php endif; ?>
                        <div class="status-group">
                            <label class="status-pill <?= $forceOpenBecauseOfflineQueue ? 'disabled-pill' : '' ?>">
                                <input type="radio" name="status" value="paid" <?= $forceOpenBecauseOfflineQueue ? 'disabled' : 'checked' ?>>
                                <strong>Lunas</strong>
                            </label>
                            <label class="status-pill">
                                <input type="radio" name="status" value="open" <?= $forceOpenBecauseOfflineQueue ? 'checked' : '' ?>>
                                <strong>Pending / Open Bill</strong>
                            </label>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="app-card mb-3">
                    <h5 class="mb-3">Metode Pembayaran</h5>
                    <div class="d-grid gap-2">
                        <label class="payment-method">
                            <input type="radio" name="metode_pembayaran" value="tunai" checked>
                            <strong>💵 Tunai (Cash)</strong>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="metode_pembayaran" value="qris">
                            <strong>▣ QRIS</strong>
                        </label>
                    </div>
                </section>

                <section id="cashInputBox" class="app-card mb-3">
                    <label class="form-label">Nominal Diterima</label>
                    <input id="nominalDiterima" type="number" name="nominal_diterima" class="form-control" min="0" placeholder="Contoh: 100000">
                    <div id="changePreview" class="mt-2 fw-bold">Kembalian: Rp 0</div>
                </section>

                <section class="app-card mb-3">
                    <div id="offlineStatus" class="alert alert-info mb-3">Mode: Online</div>
                    <button class="btn btn-dark-outline w-100 py-3">Konfirmasi Pembayaran</button>
                </section>
            </div>
        </div>
    </form>
</div>
<script src="../Assets/JS/offline_handler.js"></script>
<script src="../Assets/JS/app.js"></script>
</body>
</html>
