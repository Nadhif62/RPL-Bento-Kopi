<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

function ensure_menu_status_column_for_page(mysqli $conn): bool
{
    if (table_column_exists($conn, 'menu', 'is_active')) {
        return true;
    }

    $conn->query('ALTER TABLE menu ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER harga');
    return table_column_exists($conn, 'menu', 'is_active');
}

$hasStatusColumn = ensure_menu_status_column_for_page($conn);
$categoryLabels = [
    'promo' => 'Promo',
    'beverage' => 'Minuman',
    'makanan' => 'Makanan',
    'snack' => 'Snack',
];

$summarySql = $hasStatusColumn
    ? 'SELECT COUNT(*) AS total_produk,
              COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END),0) AS produk_aktif,
              COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END),0) AS produk_nonaktif
       FROM menu'
    : 'SELECT COUNT(*) AS total_produk,
              COUNT(*) AS produk_aktif,
              0 AS produk_nonaktif
       FROM menu';
$summary = $conn->query($summarySql)->fetch_assoc();

$productsSql = $hasStatusColumn
    ? 'SELECT * FROM menu ORDER BY FIELD(kategori,"promo","beverage","makanan","snack"), nama_menu ASC'
    : 'SELECT * FROM menu ORDER BY FIELD(kategori,"promo","beverage","makanan","snack"), nama_menu ASC';
$products = $conn->query($productsSql);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manajemen Produk - Bento Kopi POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Manajemen Produk</div>
        <span class="muted">Kelola Menu</span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-label">Total Produk</div>
                <div class="metric-value"><?= (int)$summary['total_produk'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-label">Produk Aktif</div>
                <div class="metric-value accent-green"><?= (int)$summary['produk_aktif'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric-card">
                <div class="metric-label">Produk Nonaktif</div>
                <div class="metric-value accent-red"><?= (int)$summary['produk_nonaktif'] ?></div>
            </div>
        </div>
    </div>

    <section class="app-card mb-3">
        <h5 class="mb-3 fw-bold">Tambah Produk/Menu Baru</h5>
        <form action="../Actions/process_product.php" method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="add">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Nama Produk/Menu</label>
                <input type="text" name="nama_menu" class="form-control" placeholder="Contoh: Es Kopi Susu" required>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Kategori</label>
                <select name="kategori" class="form-select" required>
                    <?php foreach ($categoryLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Harga</label>
                <input type="number" name="harga" class="form-control" placeholder="22000" min="1" step="500" required>
            </div>
            <?php if ($hasStatusColumn): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-12">
                    <button class="btn btn-primary w-100">Tambah</button>
                </div>
            <?php else: ?>
                <div class="col-lg-4 col-md-12">
                    <button class="btn btn-primary w-100">Tambah</button>
                </div>
            <?php endif; ?>
        </form>
    </section>

    <section class="app-card">
        <h5 class="mb-3 fw-bold">Daftar Produk/Menu</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Produk/Menu</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <?php if ($hasStatusColumn): ?>
                        <th>Status</th>
                    <?php endif; ?>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($products->num_rows === 0): ?>
                    <tr><td colspan="<?= $hasStatusColumn ? 5 : 4 ?>" class="muted">Belum ada produk/menu.</td></tr>
                <?php endif; ?>
                <?php while ($product = $products->fetch_assoc()): ?>
                    <?php
                        $productId = (int)$product['id'];
                        $updateFormId = 'update-product-' . $productId;
                        $isActive = !$hasStatusColumn || (int)$product['is_active'] === 1;
                    ?>
                    <tr>
                        <td>
                            <input form="<?= $updateFormId ?>" type="text" name="nama_menu" class="form-control form-control-sm" value="<?= htmlspecialchars($product['nama_menu']) ?>" required>
                        </td>
                        <td>
                            <select form="<?= $updateFormId ?>" name="kategori" class="form-select form-select-sm" required>
                                <?php foreach ($categoryLabels as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $product['kategori'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input form="<?= $updateFormId ?>" type="number" name="harga" class="form-control form-control-sm" value="<?= (float)$product['harga'] ?>" min="1" step="500" required>
                        </td>
                        <?php if ($hasStatusColumn): ?>
                            <td>
                                <select form="<?= $updateFormId ?>" name="is_active" class="form-select form-select-sm">
                                    <option value="1" <?= $isActive ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= !$isActive ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <form id="<?= $updateFormId ?>" action="../Actions/process_product.php" method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $productId ?>">
                                    <button class="btn btn-primary btn-sm" type="submit">Update</button>
                                </form>
                                <form action="../Actions/process_product.php" method="post" onsubmit="return confirm('Hapus/nonaktifkan produk ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $productId ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                </form>
                            </div>
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
