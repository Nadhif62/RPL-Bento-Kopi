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

function role_dashboard_url(string $role): string
{
    if ($role === 'kasir') {
        return app_url('Pages/kasir.php');
    }
    if ($role === 'manager') {
        return app_url('Pages/manager.php');
    }
    if ($role === 'finance') {
        return app_url('Pages/finance.php');
    }

    return app_url('Pages/index.php');
}

function require_login(array $roles = []): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . app_url('Pages/index.php?error=' . urlencode('Silakan login terlebih dahulu.')));
        exit;
    }

    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        $_SESSION['flash_error'] = 'Akses ditolak. Akun Anda tidak memiliki hak akses ke halaman tersebut.';
        header('Location: ' . role_dashboard_url((string) $_SESSION['user']['role']));
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
function ensure_audit_logs_table(mysqli $conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NOT NULL,
        entity_id INT NULL,
        description TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_entity (entity_type, entity_id),
        INDEX idx_audit_created (created_at)
    ) ENGINE=InnoDB');
}

function audit_log(mysqli $conn, string $action, string $entityType, ?int $entityId = null, string $description = ''): void
{
    try {
        ensure_audit_logs_table($conn);
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
        $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issis', $userId, $action, $entityType, $entityId, $description);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Audit tidak boleh menghentikan transaksi utama.
    }
}

function is_period_locked(mysqli $conn, string $dateTime): bool
{
    if (!table_column_exists($conn, 'monthly_closings', 'period_month')) {
        return false;
    }
    $period = date('Y-m', strtotime($dateTime));
    $stmt = $conn->prepare('SELECT id FROM monthly_closings WHERE period_month = ? LIMIT 1');
    $stmt->bind_param('s', $period);
    $stmt->execute();
    $locked = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $locked;
}


function parse_numeric_input($value, string $label, float $min = 0, bool $allowZero = true): float
{
    if ($value === null || trim((string)$value) === '') {
        throw new Exception($label . ' wajib diisi.');
    }

    $normalized = str_replace(['.', ','], ['', '.'], trim((string)$value));

    if (!is_numeric($normalized)) {
        throw new Exception($label . ' harus berupa angka yang valid.');
    }

    $number = (float)$normalized;

    if ($number < $min) {
        throw new Exception($label . ' tidak boleh kurang dari ' . angka_bersih($min) . '.');
    }

    if (!$allowZero && $number <= 0) {
        throw new Exception($label . ' harus lebih dari 0.');
    }

    return $number;
}

function ensure_current_period_unlocked(mysqli $conn): void
{
    if (is_period_locked($conn, date('Y-m-d H:i:s'))) {
        throw new Exception('Periode pembukuan bulan ini sudah dikunci. Perubahan data tidak dapat dilakukan.');
    }
}

function init_order_token(): string
{
    if (!isset($_SESSION['order_tokens']) || !is_array($_SESSION['order_tokens'])) {
        $_SESSION['order_tokens'] = [];
    }

    if (count($_SESSION['order_tokens']) > 20) {
        $_SESSION['order_tokens'] = array_slice($_SESSION['order_tokens'], -20, null, true);
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['order_tokens'][$token] = time();
    return $token;
}

function consume_order_token(string $token): bool
{
    if ($token === '' || !isset($_SESSION['order_tokens']) || !is_array($_SESSION['order_tokens'])) {
        return false;
    }

    if (!array_key_exists($token, $_SESSION['order_tokens'])) {
        return false;
    }

    unset($_SESSION['order_tokens'][$token]);
    return true;
}
