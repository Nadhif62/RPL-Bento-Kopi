<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);

$userId = (int)$_SESSION['user']['id'];
$pettyCash = 0;

if (active_shift($conn, $userId)) {
    $_SESSION['flash_error'] = 'Masih ada shift aktif.';
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

$conn->begin_transaction();

try {
    ensure_current_period_unlocked($conn);
    $pettyCash = parse_numeric_input($_POST['petty_cash'] ?? '', 'Kas awal', 0, true);
    $stmt = $conn->prepare(
        'INSERT INTO shifts (user_id, petty_cash, status, mulai_shift)
         VALUES (?, ?, "active", NOW())'
    );
    $stmt->bind_param('id', $userId, $pettyCash);
    $stmt->execute();
    $newShiftId = (int)$conn->insert_id;
    $stmt->close();

    // Order pending/open bill dari shift sebelumnya otomatis dibawa ke shift baru.
    // Tujuannya agar tagihan yang belum lunas tetap bisa diselesaikan pada shift berikutnya.
    $movePending = $conn->prepare(
        'UPDATE orders
         SET shift_id = ?
         WHERE user_id = ?
           AND status = "open"
           AND shift_id <> ?'
    );
    $movePending->bind_param('iii', $newShiftId, $userId, $newShiftId);
    $movePending->execute();
    $movedPending = $movePending->affected_rows;
    $movePending->close();

    $conn->commit();

    $_SESSION['flash_success'] = 'Shift berhasil dimulai.';
    if ($movedPending > 0) {
        $_SESSION['flash_success'] .= ' ' . $movedPending . ' order pending berhasil dibawa ke shift ini.';
    }
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = 'Gagal memulai shift: ' . $e->getMessage();
}

header('Location: ' . app_url('Pages/kasir.php'));
exit;
