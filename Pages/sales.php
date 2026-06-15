<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$shift = active_shift($conn, $userId);
$sales = $shift ? shift_sales($conn, (int)$shift['id']) : null;
$pettyCash = $shift ? (float)$shift['petty_cash'] : 0;
$cashTotal = $sales ? (float)$sales['tunai'] : 0;
$qrisTotal = $sales ? (float)$sales['qris'] : 0;
$expectedCash = $pettyCash + $cashTotal;
$hasActualColumn = table_column_exists($conn, 'shifts', 'actual_cash');
$storedActualCash = ($hasActualColumn && $shift && array_key_exists('actual_cash', $shift) && $shift['actual_cash'] !== null)
    ? (float)$shift['actual_cash']
    : null;
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
            <h4 class="mb-2">Belum Ada Shift Aktif</h4>
            <p class="muted">Sales shift hanya bisa dilihat setelah kasir melakukan start shift.</p>
            <a href="kasir.php" class="btn btn-success w-100">Start Shift</a>
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
            <div class="mb-3">
                <h5 class="mb-0">Ringkasan Uang Shift</h5>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="metric-card">
                        <div class="metric-label">Petty Cash</div>
                        <div class="metric-value accent-yellow"><?= rupiah($pettyCash) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-card">
                        <div class="metric-label">Cash</div>
                        <div class="metric-value accent-green"><?= rupiah($cashTotal) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-card">
                        <div class="metric-label">Actual Sistem</div>
                        <div class="metric-value"><?= rupiah($expectedCash) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-card">
                        <div class="metric-label">QRIS</div>
                        <div class="metric-value accent-green"><?= rupiah($qrisTotal) ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="app-card">
            <h5 class="mb-3">Input Actual Cash</h5>
                        <form action="../Actions/close_shift.php" method="post" onsubmit="return confirm('Simpan actual cash dan tutup shift sekarang?')">
                <input type="hidden" id="expectedCashValue" value="<?= (float)$expectedCash ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Actual Cash</label>
                        <input id="actualCashInput" type="number" name="actual_cash" class="form-control" min="0" required value="<?= $storedActualCash !== null ? htmlspecialchars((string)$storedActualCash) : '' ?>" placeholder="Contoh: 625000">
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card py-3">
                            <div class="metric-label">Selisih Kas</div>
                            <div id="cashDifferencePreview" class="metric-value">Rp 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-dark-outline w-100 py-3">Simpan & Close Shift</button>
                    </div>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
<script>
(function () {
    const input = document.getElementById('actualCashInput');
    const expected = Number(document.getElementById('expectedCashValue')?.value || 0);
    const preview = document.getElementById('cashDifferencePreview');

    function formatRupiah(value) {
        const number = Number(value || 0);
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(number).replace('IDR', 'Rp').trim();
    }

    function updateDifference() {
        if (!input || !preview) return;
        const actual = Number(input.value || 0);
        const diff = actual - expected;
        preview.textContent = formatRupiah(diff);
        preview.classList.toggle('accent-green', diff === 0);
        preview.classList.toggle('accent-yellow', diff > 0);
        preview.classList.toggle('accent-red', diff < 0);
    }

    input?.addEventListener('input', updateDifference);
    updateDifference();
})();
</script>
</body>
</html>
