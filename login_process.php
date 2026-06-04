<?php
require_once 'config.php';

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

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: index.php?error=Username atau password salah');
    exit;
}

$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'nama_lengkap' => $user['nama_lengkap'],
    'role' => $user['role']
];

if ($user['role'] === 'kasir') {
    header('Location: kasir.php');
    exit;
}

if ($user['role'] === 'manager') {
    header('Location: manager.php');
    exit;
}

if ($user['role'] === 'finance') {
    header('Location: finance.php');
    exit;
}

header('Location: index.php?error=Role tidak dikenali');
exit;