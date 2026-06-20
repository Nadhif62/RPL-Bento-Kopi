<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$action = $_POST['action'] ?? '';
$allowedCategories = ['promo','beverage','makanan','snack'];

try {
    ensure_current_period_unlocked($conn);

    if (!table_column_exists($conn, 'menu', 'is_active')) {
        $conn->query('ALTER TABLE menu ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER harga');
    }

    if ($action === 'add' || $action === 'update') {
        $nama = trim($_POST['nama_menu'] ?? '');
        $kategori = $_POST['kategori'] ?? 'makanan';
        $harga = parse_numeric_input($_POST['harga'] ?? '', 'Harga produk', 0, false);
        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($nama === '') {
            throw new Exception('Nama produk/menu wajib diisi.');
        }
        if (!in_array($kategori, $allowedCategories, true)) {
            throw new Exception('Kategori produk tidak valid.');
        }
        if ($action === 'add') {
            $stmt = $conn->prepare('INSERT INTO menu (nama_menu, kategori, harga, is_active) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssdi', $nama, $kategori, $harga, $isActive);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();
            audit_log($conn, 'product_add', 'menu', $newId, 'Produk ditambahkan: '.$nama);
            $_SESSION['flash_success'] = 'Produk berhasil ditambahkan.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID produk tidak valid.');
            $stmt = $conn->prepare('UPDATE menu SET nama_menu = ?, kategori = ?, harga = ?, is_active = ? WHERE id = ?');
            $stmt->bind_param('ssdii', $nama, $kategori, $harga, $isActive, $id);
            $stmt->execute();
            $stmt->close();
            audit_log($conn, 'product_update', 'menu', $id, 'Produk diperbarui: '.$nama);
            $_SESSION['flash_success'] = 'Produk berhasil diperbarui.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('ID produk tidak valid.');
        $stmt = $conn->prepare('UPDATE menu SET is_active = 0 WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'product_deactivate', 'menu', $id, 'Produk dinonaktifkan.');
        $_SESSION['flash_success'] = 'Produk berhasil dinonaktifkan.';
    } else {
        throw new Exception('Aksi produk tidak valid.');
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . app_url('Pages/manage_products.php'));
exit;
