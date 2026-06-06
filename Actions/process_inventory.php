<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $namaBahan = trim($_POST['nama_bahan'] ?? '');
        $satuan = $_POST['satuan'] ?? 'gram';
        $stokGudang = (float)($_POST['stok_gudang'] ?? 0);
        $batasKritis = (float)($_POST['batas_kritis'] ?? 0);

        if ($namaBahan === '') {
            throw new Exception('Nama bahan wajib diisi.');
        }

        if (!in_array($satuan, ['gram', 'ml', 'pcs'], true)) {
            throw new Exception('Satuan tidak valid.');
        }

        if ($stokGudang < 0 || $batasKritis < 0) {
            throw new Exception('Stok dan batas kritis tidak boleh negatif.');
        }

        $stmt = $conn->prepare(
            'INSERT INTO ingredients (nama_bahan, satuan, stok_gudang, batas_kritis)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssdd', $namaBahan, $satuan, $stokGudang, $batasKritis);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Bahan baku baru berhasil ditambahkan.';
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $namaBahan = trim($_POST['nama_bahan'] ?? '');
        $satuan = $_POST['satuan'] ?? 'gram';
        $stokGudang = (float)($_POST['stok_gudang'] ?? 0);
        $batasKritis = (float)($_POST['batas_kritis'] ?? 0);

        if ($id <= 0 || $namaBahan === '') {
            throw new Exception('Data bahan tidak valid.');
        }

        if (!in_array($satuan, ['gram', 'ml', 'pcs'], true)) {
            throw new Exception('Satuan tidak valid.');
        }

        if ($stokGudang < 0 || $batasKritis < 0) {
            throw new Exception('Stok dan batas kritis tidak boleh negatif.');
        }

        $stmt = $conn->prepare(
            'UPDATE ingredients
             SET nama_bahan = ?,
                 satuan = ?,
                 stok_gudang = ?,
                 batas_kritis = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ssddi', $namaBahan, $satuan, $stokGudang, $batasKritis, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Data stok berhasil diperbarui.';
    } elseif ($action === 'restock') {
        $id = (int)($_POST['id'] ?? 0);
        $jumlahTambah = (float)($_POST['jumlah_tambah'] ?? 0);

        if ($id <= 0 || $jumlahTambah <= 0) {
            throw new Exception('Jumlah restock tidak valid.');
        }

        $stmt = $conn->prepare(
            'UPDATE ingredients
             SET stok_gudang = stok_gudang + ?
             WHERE id = ?'
        );
        $stmt->bind_param('di', $jumlahTambah, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_success'] = 'Restock berhasil ditambahkan.';
    } else {
        throw new Exception('Aksi inventory tidak dikenali.');
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

$returnTo = $_POST['return_to'] ?? 'Pages/manager.php';
if (strpos($returnTo, '://') !== false) {
    $returnTo = 'Pages/manager.php';
}
header('Location: ' . app_url($returnTo));
exit;