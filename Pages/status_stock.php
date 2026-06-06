<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$ingredients = $conn->query('SELECT * FROM ingredients ORDER BY nama_bahan ASC');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Status Stock</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="kasir.php" class="btn btn-dark-outline btn-sm">← Home</a>
        <div class="page-title">Status Stock</div>
        <span class="muted">Read Only</span>
    </header>

    <section class="app-card">
        <h5 class="mb-3">Stok Bahan Baku</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Stok</th>
                    <th>Batas Kritis</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($ing = $ingredients->fetch_assoc()): ?>
                    <?php $isCritical = (float)$ing['stok_gudang'] <= (float)$ing['batas_kritis']; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ing['nama_bahan']) ?></strong></td>
                        <td><?= format_stok($ing['stok_gudang'], $ing['satuan']) ?></td>
                        <td><?= format_stok($ing['batas_kritis'], $ing['satuan']) ?></td>
                        <td>
                            <?= $isCritical
                                ? '<span class="badge badge-soft-danger">Kritis</span>'
                                : '<span class="badge badge-soft-success">Aman</span>' ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
