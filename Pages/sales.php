<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);
$sales = $shift ? shift_sales($conn, (int)$shift['id']) : null;
$pettyCash = $shift ? (float)$shift['petty_cash'] : 0;
$cashTotal = $sales ? (float)$sales['tunai'] : 0;
$qrisTotal = $sales ? (float)$sales['qris'] : 0;
$totalSales = $cashTotal + $qrisTotal;
$actualCash = $pettyCash + $cashTotal;
$pendingCount = 0;

if ($shift) {
    $pendingStmt = $conn->prepare(
        'SELECT COUNT(*) AS total_pending
         FROM orders
         WHERE shift_id = ? AND user_id = ? AND status = "open"'
    );
    $pendingStmt->bind_param('ii', $shift['id'], $userId);
    $pendingStmt->execute();
    $pendingCount = (int)($pendingStmt->get_result()->fetch_assoc()['total_pending'] ?? 0);
    $pendingStmt->close();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sales Shift - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Sales Shift</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <?php if (!$shift): ?>
        <section class="app-card login-card">
            <h4 class="mb-2">Mulai Shift</h4>
            <p class="muted">Kasir wajib membuka shift sebelum melihat sales dan melakukan closing.</p>

            <form action="../Actions/start_shift.php" method="post">
                <label class="form-label">Petty Cash / Kas Awal</label>
                <input type="number" name="petty_cash" class="form-control mb-3" value="500000" min="0" required>
                <button class="btn btn-success w-100">Start Shift</button>
            </form>
        </section>
    <?php else: ?>
        <section class="app-card mb-3 compact-order-head">
            <div class="compact-order-item">
                <div class="compact-order-label">Kasir</div>
                <strong class="compact-order-value"><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></strong>
            </div>
            <div class="compact-order-item text-md-end">
                <div class="compact-order-label">Mulai Shift</div>
                <strong class="compact-order-value"><?= date('H.i', strtotime($shift['mulai_shift'])) ?></strong>
            </div>
        </section>

        <section class="app-card mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-3">
                <div>
                    <h5 class="mb-1">Ringkasan Sales Shift</h5>
                    <div class="muted small">Semua nominal dihitung otomatis dari petty cash dan transaksi pada shift aktif.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card metric-card-compact">
                        <div class="metric-label">Petty Cash</div>
                        <div class="metric-value accent-yellow"><?= rupiah($pettyCash) ?></div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card metric-card-compact">
                        <div class="metric-label">Total Cash</div>
                        <div class="metric-value accent-green"><?= rupiah($cashTotal) ?></div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card metric-card-compact">
                        <div class="metric-label">Total QRIS</div>
                        <div class="metric-value accent-green"><?= rupiah($qrisTotal) ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-6">
                    <div class="metric-card metric-card-compact">
                        <div class="metric-label">Total Sales</div>
                        <div class="metric-value"><?= rupiah($totalSales) ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-12">
                    <div class="metric-card metric-card-compact metric-card-highlight">
                        <div class="metric-label">Actual Cash</div>
                        <div class="metric-value"><?= rupiah($actualCash) ?></div>
                    </div>
                </div>
            </div>
        </section>

        <form class="close-shift-action" action="../Actions/close_shift.php" method="post" onsubmit="return confirm('<?= $pendingCount > 0 ? 'Tutup shift sekarang? ' . $pendingCount . ' order pending akan dibawa ke shift berikutnya.' : 'Tutup shift sekarang?' ?>')">
            <button class="btn btn-danger close-shift-btn" type="submit">Close Shift</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
