<?php
require_once __DIR__ . '/../Includes/config.php';

if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'kasir') {
        header('Location: ' . app_url('Pages/kasir.php'));
        exit;
    }
    if ($_SESSION['user']['role'] === 'manager') {
        header('Location: ' . app_url('Pages/manager.php'));
        exit;
    }
    if ($_SESSION['user']['role'] === 'finance') {
        header('Location: ' . app_url('Pages/finance.php'));
        exit;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Login Bento Kopi UMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark login-page">
<div class="app-shell login-shell">
    <section class="app-card login-card login-card-combined">
        <div class="login-topline">
            <div class="brand">BENTO KOPI POS</div>
            <span class="muted"><?= date('H.i') ?></span>
        </div>

        <div class="login-content">
            <div class="text-center mb-4">
                <div class="success-icon login-user-icon"><i class="bi bi-person"></i></div>
                <h4 class="mb-1">Masuk ke Sistem</h4>
                <p class="muted mb-0">Kasir, Manager, atau Finance</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <form action="../Actions/login_process.php" method="post">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Contoh: kasir" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <button class="btn btn-dark-outline w-100 py-3" type="submit">Masuk →</button>
            </form>

            <small class="muted d-block mt-3">Demo: kasir / manager / finance | 123456</small>
        </div>
    </section>
</div>
</body>
</html>
