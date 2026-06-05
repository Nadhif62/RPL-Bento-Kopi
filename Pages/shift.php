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

    <style>
        body { background:#f5f7fb; }
        .card, .navbar {
            background:#fff;
            border:1px solid #dee2e6;
            border-radius:14px;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .inner-card {
            background:#f8f9fa;
            border:1px solid #dee2e6;
            border-radius:12px;
        }
        .muted { color:#6c757d; }
        a { text-decoration:none; }
    </style>
</head>

<body>
<div class="container py-3">
    <div class="navbar p-3 mb-3">
        <a href="kasir.php" class="btn btn-outline-secondary btn-sm">← Home</a>
        <strong>Manajemen Shift</strong>
        <span></span>
    </div>

    <?php if (!$shift): ?>
        <div class="card p-4">
            <h4>Belum Ada Shift Aktif</h4>

            <form action="../Actions/start_shift.php" method="post" class="mt-3">
                <label class="form-label">Petty Cash / Kas Awal</label>
                <input type="number"
                       name="petty_cash"
                       class="form-control mb-3"
                       value="500000"
                       min="0"
                       required>

                <button class="btn btn-success">Start Shift</button>
            </form>
        </div>
    <?php else: ?>
        <div class="card p-4">
            <h4 class="mb-4">◷ Shift Sedang Aktif</h4>

            <div class="inner-card p-3 mb-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">Kasir</span>
                    <strong><?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">Mulai shift</span>
                    <strong><?= date('H.i', strtotime($shift['mulai_shift'])) ?></strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">Petty cash</span>
                    <strong><?= rupiah($shift['petty_cash']) ?></strong>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">Transaksi shift</span>
                    <strong><?= (int)$sales['transaksi'] ?></strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">Tunai</span>
                    <strong><?= rupiah($sales['tunai']) ?></strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="muted">QRIS</span>
                    <strong><?= rupiah($sales['qris']) ?></strong>
                </div>

                <div class="d-flex justify-content-between">
                    <span>Total penjualan shift ini</span>
                    <strong class="text-success"><?= rupiah($sales['net_sales']) ?></strong>
                </div>
            </div>

            <form action="../Actions/close_shift.php" method="post" onsubmit="return confirm('Tutup shift sekarang?')">
                <button class="btn btn-outline-danger w-100">Close Shift</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>