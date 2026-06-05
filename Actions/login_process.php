<?php
require_once __DIR__ . '/../Includes/config.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare(
    'SELECT id, username, password, nama_lengkap, role 
     FROM users 
     WHERE username = ? 
     LIMIT 1'
);
$stmt->bind_param('s', $username);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $password !== $user['password']) {
    header('Location: ' . app_url('Pages/index.php?error=Username atau password salah'));
    exit;
}

$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'nama_lengkap' => $user['nama_lengkap'],
    'role' => $user['role']
];

if ($user['role'] === 'kasir') {
    header('Location: ' . app_url('Pages/kasir.php'));
    exit;
}

if ($user['role'] === 'manager') {
    header('Location: ' . app_url('Pages/manager.php'));
    exit;
}

if ($user['role'] === 'finance') {
    header('Location: ' . app_url('Pages/finance.php'));
    exit;
}

header('Location: ' . app_url('Pages/index.php?error=Role tidak dikenali'));
exit;
