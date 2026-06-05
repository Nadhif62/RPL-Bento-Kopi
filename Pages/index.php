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
</head>

<body class="bg-light">
<div class="container py-5">
    <div class="bg-white border rounded p-3 mb-4 shadow-sm d-flex justify-content-between">
        <strong>Bento Kopi UMS POS</strong>
        <span><?= date('H.i') ?></span>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white"
                             style="width:70px;height:70px;font-size:30px;">
                            👤
                        </div>

                        <h4 class="mb-1">Masuk ke Sistem</h4>
                        <p class="text-muted mb-0">Kasir, Manager, atau Finance</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                    <?php endif; ?>

                    <form action="../Actions/login_process.php" method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text"
                                   name="username"
                                   class="form-control"
                                   placeholder="Contoh: kasir"
                                   required
                                   autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   placeholder="Password"
                                   required>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            Masuk →
                        </button>
                    </form>

                    <small class="text-muted d-block mt-3">
                        Demo: kasir/manager/finance | 123456
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>