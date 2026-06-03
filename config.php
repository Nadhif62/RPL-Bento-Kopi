<?php
session_start();

date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bento_kopi';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

function require_login(array $roles = []): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }

    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: index.php?error=Akses ditolak');
        exit;
    }
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function active_shift(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM shifts WHERE user_id = ? AND status = "active" ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $shift ?: null;
}

function shift_sales(mysqli $conn, int $shiftId): array
{
    $stmt = $conn->prepare(
        'SELECT
            COUNT(*) AS transaksi,
            COALESCE(SUM(CASE WHEN status IN ("paid","refunded") THEN total_bayar ELSE 0 END), 0) AS gross_sales,
            COALESCE(SUM(CASE WHEN status = "refunded" THEN total_bayar ELSE 0 END), 0) AS refunded_sales,
            COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "tunai" THEN total_bayar ELSE 0 END), 0) AS tunai,
            COALESCE(SUM(CASE WHEN status = "paid" AND metode_pembayaran = "qris" THEN total_bayar ELSE 0 END), 0) AS qris
         FROM orders
         WHERE shift_id = ?'
    );
    $stmt->bind_param('i', $shiftId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $row['net_sales'] = (float)$row['gross_sales'] - (float)$row['refunded_sales'];
    return $row;
}