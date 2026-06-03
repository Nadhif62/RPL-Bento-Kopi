<?php
require_once 'config.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare('SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ? LIMIT 1');
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

header('Location: ' . ($user['role'] === 'kasir' ? 'kasir.php' : 'admin.php'));
exit;