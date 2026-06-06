<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$success = $_SESSION['last_order_success'] ?? null;
if (!$success) {
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}
unset($_SESSION['last_order_success']);

$total = 0;
foreach (($success['details'] ?? []) as $item) {
    $total += (float)$item['subtotal'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Order Berhasil - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <div class="brand">BENTO KOPI POS</div>
        <span class="muted">#<?= (int)$success['order_id'] ?></span>
    </header>

    <section class="app-card text-center">
        <div class="success-icon">✓</div>
        <h3><?= htmlspecialchars($success['title']) ?></h3>
        <p class="muted mb-4"><?= htmlspecialchars($success['subtitle']) ?></p>

        <div class="app-card text-start success-receipt">
            <div class="summary-list">
                <?php foreach (($success['details'] ?? []) as $item): ?>
                    <div class="summary-row">
                        <span><?= htmlspecialchars($item['nama_menu']) ?> <strong>x<?= (int)$item['jumlah'] ?></strong></span>
                        <strong><?= rupiah($item['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-total">
                <span>Total</span>
                <span><?= rupiah($total) ?></span>
            </div>
            <?php if (($success['status'] ?? '') === 'paid'): ?>
                <div class="summary-row mt-3 border-0">
                    <span>Kembalian</span>
                    <strong><?= rupiah($success['kembalian'] ?? 0) ?></strong>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-3 mb-0">Order tersimpan sebagai pending/open bill.</div>
            <?php endif; ?>
        </div>

        <div class="row g-3 mt-3 justify-content-center">
            <div class="col-md-4">
                <a href="order.php" class="btn btn-dark-outline w-100 py-3">Order Baru +</a>
            </div>
            <div class="col-md-4">
                <a href="kasir.php" class="btn btn-dark-outline w-100 py-3">Home ⌂</a>
            </div>
        </div>
    </section>
</div>
</body>
</html>
