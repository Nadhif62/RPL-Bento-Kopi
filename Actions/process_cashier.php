<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$username = trim($_POST['username'] ?? '');
$nama = trim($_POST['nama_lengkap'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $nama === '' || strlen($password) < 6) {
    $_SESSION['flash_error'] = 'Username, nama kasir, dan password minimal 6 karakter wajib diisi.';
    header('Location: ' . app_url('Pages/manager.php'));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'kasir';

try {
    $stmt = $conn->prepare(
        'INSERT INTO users (username, password, nama_lengkap, role)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('ssss', $username, $hash, $nama, $role);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_success'] = 'Kasir baru berhasil ditambahkan.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Gagal menambah kasir. Username mungkin sudah dipakai.';
}

header('Location: ' . app_url('Pages/manager.php'));
exit;