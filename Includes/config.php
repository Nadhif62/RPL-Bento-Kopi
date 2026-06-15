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

function app_base_path(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = trim($scriptDir, '/');

    if ($scriptDir === '') {
        return '/';
    }

    $parts = explode('/', $scriptDir);
    $lastPart = end($parts);

    if (in_array($lastPart, ['Pages', 'Actions', 'API', 'Includes'], true)) {
        array_pop($parts);
    }

    if (empty($parts)) {
        return '/';
    }

    return '/' . implode('/', $parts) . '/';
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', app_base_path());
}

function app_url(string $path = ''): string
{
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}

function require_login(array $roles = []): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . app_url('Pages/index.php'));
        exit;
    }

    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: ' . app_url('Pages/index.php?error=Akses ditolak'));
        exit;
    }
}



function table_column_exists(mysqli $conn, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $tableName = '`' . $conn->real_escape_string($table) . '`';
    $columnName = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function active_shift(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT * FROM shifts 
         WHERE user_id = ? AND status = "active" 
         ORDER BY id DESC LIMIT 1'
    );
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
function angka_bersih($angka): string
{
    $angka = (float)$angka;

    if (floor($angka) == $angka) {
        return number_format($angka, 0, ',', '.');
    }

    return number_format($angka, 2, ',', '.');
}

function format_stok($jumlah, string $satuan): string
{
    $jumlah = (float)$jumlah;

    if ($satuan === 'gram') {
        if ($jumlah >= 1000) {
            return angka_bersih($jumlah / 1000) . ' kg';
        }

        return angka_bersih($jumlah) . ' gram';
    }

    if ($satuan === 'ml') {
        if ($jumlah >= 1000) {
            return angka_bersih($jumlah / 1000) . ' liter';
        }

        return angka_bersih($jumlah) . ' ml';
    }

    if ($satuan === 'pcs') {
        return angka_bersih($jumlah) . ' pcs';
    }

    return angka_bersih($jumlah) . ' ' . $satuan;
}