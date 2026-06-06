<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);
$sales = $shift ? shift_sales($conn, (int)$shift['id']) : null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manajemen Shift</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Manajemen Shift</div>
        <span class="muted"><?= date('H.i') ?></span>
    </header>

    <?php if (!$shift): ?>
        <section class="app-card login-card">
            <h4>Belum Ada Shift Aktif</h4>
            <p class="muted">Mulai shift sebelum transaksi.</p>
            <form action="../Actions/start_shift.php" method="post">
                <label class="form-label">Petty Cash / Kas Awal</label>
                <input type="number" name="petty_cash" class="form-control mb-3" value="500000" min="0" required>
                <button class="btn btn-success w-100">Start Shift</button>
            </form>
        </section>
    <?php else: ?>
        <section class="app-card">
            <h4 class="mb-4">◷ Shift Sedang Aktif</h4>
            <div class="app-card mb-3">
                <div class="summary-row"><span class="muted">Kasir</span><strong><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></strong></div>
                <div class="summary-row"><span class="muted">Mulai shift</span><strong><?= date('H.i', strtotime($shift['mulai_shift'])) ?></strong></div>
                <div class="summary-row"><span class="muted">Petty cash</span><strong><?= rupiah($shift['petty_cash']) ?></strong></div>
                <div class="summary-row"><span class="muted">Transaksi shift</span><strong><?= (int)$sales['transaksi'] ?></strong></div>
                <div class="summary-row"><span class="muted">Tunai</span><strong><?= rupiah($sales['tunai']) ?></strong></div>
                <div class="summary-row"><span class="muted">QRIS</span><strong><?= rupiah($sales['qris']) ?></strong></div>
                <div class="summary-total"><span>Total penjualan shift ini</span><span class="price"><?= rupiah($sales['net_sales']) ?></span></div>
            </div>
            <form action="../Actions/close_shift.php" method="post" onsubmit="return confirm('Tutup shift sekarang?')">
                <button class="btn btn-dark-outline w-100 py-3">Close Shift</button>
            </form>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
