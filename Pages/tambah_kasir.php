<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$cashiers = $conn->query(
    'SELECT id, username, nama_lengkap, created_at
     FROM users
     WHERE role = "kasir"
     ORDER BY nama_lengkap ASC'
);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Kasir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Tambah Kasir</div>
        <span class="muted">User</span>
    </header>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="app-card">
                <h5 class="mb-3">Form Akun Kasir</h5>
                <form action="../Actions/process_cashier.php" method="post">
                    <input type="hidden" name="return_to" value="Pages/tambah_kasir.php">
                    <div class="mb-3"><label class="form-label">Nama Kasir</label><input type="text" name="nama_lengkap" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                    <button class="btn btn-primary w-100">Tambah Kasir</button>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="app-card">
                <h5 class="mb-3">Daftar Kasir</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Nama</th><th>Username</th><th>Dibuat</th></tr></thead>
                        <tbody>
                        <?php while ($c = $cashiers->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nama_lengkap']) ?></strong></td>
                                <td>@<?= htmlspecialchars($c['username']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
</body>
</html>
