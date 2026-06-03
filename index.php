<?php
require_once 'config.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . ($_SESSION['user']['role'] === 'kasir' ? 'kasir.php' : 'admin.php'));
    exit;
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Login Bento Kopi UMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
            color: #212529;
            font-family: Arial, sans-serif;
        }

        .topbar,
        .card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 16px;
            color: #212529;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .form-control {
            background: #ffffff;
            color: #212529;
            border-color: #ced4da;
        }

        .form-control:focus {
            background: #ffffff;
            color: #212529;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
        }

        .login-icon {
            width: 70px;
            height: 70px;
            background: #0d6efd;
            color: white;
            font-size: 30px;
        }

        .text-muted-custom {
            color: #6c757d;
        }
    </style>
</head>

<body>
<div class="container py-5">
    <div class="topbar d-flex justify-content-between align-items-center p-3 mb-4">
        <strong>☕ Bento Kopi UMS POS</strong>
        <span><?= date('H.i') ?></span>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="login-icon rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                            👤
                        </div>

                        <h4 class="mb-1">Masuk ke Sistem</h4>
                        <p class="text-muted-custom mb-0">Masukkan kredensial kasir/admin</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                    <?php endif; ?>

                    <form action="login_process.php" method="post">
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

                    <small class="text-muted-custom d-block mt-3">
                        Demo: kasir/admin/finance | 123456
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>