<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$ingredients = $conn->query('SELECT * FROM ingredients ORDER BY nama_bahan ASC');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kelola Stock Bahan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Kelola Stock Bahan</div>
        <span class="muted">Inventory</span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <section class="app-card mb-3">
        <h5 class="mb-3">Tambah Bahan Baru</h5>
        <form action="../Actions/process_inventory.php" method="post" class="row g-2">
            <input type="hidden" name="return_to" value="Pages/manage_stock.php">
            <input type="hidden" name="action" value="add">
            <div class="col-md-3"><input type="text" name="nama_bahan" class="form-control" placeholder="Nama bahan" required></div>
            <div class="col-md-2">
                <select name="satuan" class="form-select" required>
                    <option value="gram">gram</option>
                    <option value="ml">ml</option>
                    <option value="pcs">pcs</option>
                </select>
            </div>
            <div class="col-md-3"><input type="number" name="stok_gudang" class="form-control" placeholder="Stok awal" min="0" step="0.01" required></div>
            <div class="col-md-2"><input type="number" name="batas_kritis" class="form-control" placeholder="Batas kritis" min="0" step="0.01" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Tambah</button></div>
        </form>
    </section>

    <section class="app-card">
        <h5 class="mb-3">Daftar Bahan</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Satuan</th>
                    <th>Stok</th>
                    <th>Batas</th>
                    <th>Status</th>
                    <th>Update</th>
                    <th>Restock</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($ing = $ingredients->fetch_assoc()): ?>
                    <?php $isCritical = (float)$ing['stok_gudang'] <= (float)$ing['batas_kritis']; ?>
                    <tr>
                        <form action="../Actions/process_inventory.php" method="post">
                            <input type="hidden" name="return_to" value="Pages/manage_stock.php">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$ing['id'] ?>">
                            <td><input type="text" name="nama_bahan" class="form-control form-control-sm" value="<?= htmlspecialchars($ing['nama_bahan']) ?>" required></td>
                            <td>
                                <select name="satuan" class="form-select form-select-sm">
                                    <option value="gram" <?= $ing['satuan'] === 'gram' ? 'selected' : '' ?>>gram</option>
                                    <option value="ml" <?= $ing['satuan'] === 'ml' ? 'selected' : '' ?>>ml</option>
                                    <option value="pcs" <?= $ing['satuan'] === 'pcs' ? 'selected' : '' ?>>pcs</option>
                                </select>
                            </td>
                            <td><input type="number" name="stok_gudang" class="form-control form-control-sm" value="<?= (float)$ing['stok_gudang'] ?>" min="0" step="0.01" required></td>
                            <td><input type="number" name="batas_kritis" class="form-control form-control-sm" value="<?= (float)$ing['batas_kritis'] ?>" min="0" step="0.01" required></td>
                            <td><?= $isCritical ? '<span class="badge badge-soft-danger">Kritis</span>' : '<span class="badge badge-soft-success">Aman</span>' ?></td>
                            <td><button class="btn btn-primary btn-sm">Update</button></td>
                        </form>
                        <td>
                            <form action="../Actions/process_inventory.php" method="post" class="d-flex gap-2">
                                <input type="hidden" name="return_to" value="Pages/manage_stock.php">
                                <input type="hidden" name="action" value="restock">
                                <input type="hidden" name="id" value="<?= (int)$ing['id'] ?>">
                                <input type="number" name="jumlah_tambah" class="form-control form-control-sm" placeholder="+stok" min="0" step="0.01" required>
                                <button class="btn btn-success btn-sm">Restock</button>
                            </form>
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
