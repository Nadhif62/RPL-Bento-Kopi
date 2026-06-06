<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$refunds = $conn->query(
    'SELECT r.*, o.total_bayar, o.nomor_meja, o.order_type, o.customer_name, o.metode_pembayaran, u.nama_lengkap AS kasir
     FROM refunds r
     JOIN orders o ON r.order_id = o.id
     JOIN users u ON o.user_id = u.id
     ORDER BY r.created_at DESC'
);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Pengajuan Refund</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../Assets/CSS/app.css" rel="stylesheet">
</head>
<body class="app-dark">
<div class="app-shell">
    <header class="topbar">
        <a href="manager.php" class="btn btn-dark-outline btn-sm">← Manager</a>
        <div class="page-title">Daftar Pengajuan Refund</div>
        <span class="muted">Monitoring</span>
    </header>

    <section class="app-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Order</th><th>Kasir</th><th>Total</th><th>Alasan</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                <?php if ($refunds->num_rows === 0): ?>
                    <tr><td colspan="6" class="muted">Belum ada pengajuan refund.</td></tr>
                <?php endif; ?>
                <?php while ($refund = $refunds->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$refund['order_id'] ?></strong><br>
                            <span class="muted"><?= htmlspecialchars($refund['order_type'] === 'takeaway' && $refund['customer_name'] ? $refund['customer_name'] : $refund['nomor_meja']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($refund['kasir']) ?></td>
                        <td><?= rupiah($refund['total_bayar']) ?></td>
                        <td><?= htmlspecialchars($refund['alasan']) ?></td>
                        <td><?= $refund['status'] === 'approved' ? '<span class="badge badge-soft-success">Approved</span>' : '<span class="badge badge-soft-warning">Pending</span>' ?></td>
                        <td><?= date('d/m/Y H.i', strtotime($refund['created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
