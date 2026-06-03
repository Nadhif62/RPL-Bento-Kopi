<?php

function save_order(mysqli $conn, array $payload): array
{
    $userId = (int)($payload['user_id'] ?? 0);
    $shiftId = (int)($payload['shift_id'] ?? 0);
    $nomorMeja = trim((string)($payload['nomor_meja'] ?? ''));
    $orderType = $payload['order_type'] ?? 'dine_in';
    $customerName = trim((string)($payload['customer_name'] ?? ''));
    $metodePembayaran = $payload['metode_pembayaran'] ?? 'tunai';
    $nominalDiterima = isset($payload['nominal_diterima']) && $payload['nominal_diterima'] !== ''
        ? (float)$payload['nominal_diterima']
        : null;
    $status = $payload['status'] ?? 'paid';
    $items = $payload['items'] ?? [];

    if (!in_array($orderType, ['dine_in', 'takeaway'], true)) {
        $orderType = 'dine_in';
    }

    if (!in_array($metodePembayaran, ['tunai', 'qris'], true)) {
        $metodePembayaran = 'tunai';
    }

    if (!in_array($status, ['open', 'paid'], true)) {
        $status = 'paid';
    }

    $cleanItems = [];

    foreach ($items as $menuId => $qty) {
        $menuId = (int)$menuId;
        $qty = (int)$qty;

        if ($menuId > 0 && $qty > 0) {
            $cleanItems[$menuId] = ($cleanItems[$menuId] ?? 0) + $qty;
        }
    }

    if ($userId <= 0 || $shiftId <= 0) {
        throw new Exception('Shift kasir belum aktif. Start shift terlebih dahulu.');
    }

    if ($nomorMeja === '' || empty($cleanItems)) {
        throw new Exception('Nomor meja/nama pelanggan dan minimal satu menu wajib diisi.');
    }

    $conn->begin_transaction();

    try {
        $total = 0;
        $menuData = [];

        $menuStmt = $conn->prepare('SELECT id, nama_menu, harga FROM menu WHERE id = ?');

        foreach ($cleanItems as $menuId => $qty) {
            $menuStmt->bind_param('i', $menuId);
            $menuStmt->execute();
            $menu = $menuStmt->get_result()->fetch_assoc();

            if (!$menu) {
                throw new Exception('Menu tidak ditemukan.');
            }

            $menuData[$menuId] = $menu;
            $total += ((float)$menu['harga'] * $qty);
        }

        $menuStmt->close();

        if ($status === 'paid' && $metodePembayaran === 'tunai') {
            if ($nominalDiterima === null || $nominalDiterima < $total) {
                throw new Exception('Nominal tunai tidak boleh kurang dari total bayar.');
            }
        }

        if ($metodePembayaran === 'qris') {
            $nominalDiterima = $total;
        }

        $kembalian = $metodePembayaran === 'tunai' ? max(0, (float)$nominalDiterima - $total) : 0;
        $required = [];

        $recipeStmt = $conn->prepare('SELECT ingredient_id, jumlah_dibutuhkan FROM recipe_mapping WHERE menu_id = ?');

        foreach ($cleanItems as $menuId => $qty) {
            $recipeStmt->bind_param('i', $menuId);
            $recipeStmt->execute();
            $recipes = $recipeStmt->get_result();

            while ($row = $recipes->fetch_assoc()) {
                $ingredientId = (int)$row['ingredient_id'];
                $needed = (float)$row['jumlah_dibutuhkan'] * $qty;
                $required[$ingredientId] = ($required[$ingredientId] ?? 0) + $needed;
            }
        }

        $recipeStmt->close();

        if (empty($required)) {
            throw new Exception('Mapping resep belum tersedia untuk menu yang dipilih.');
        }

        $stockStmt = $conn->prepare('SELECT id, nama_bahan, stok_gudang, batas_kritis FROM ingredients WHERE id = ? FOR UPDATE');

        foreach ($required as $ingredientId => $needed) {
            $stockStmt->bind_param('i', $ingredientId);
            $stockStmt->execute();
            $ingredient = $stockStmt->get_result()->fetch_assoc();

            if (!$ingredient) {
                throw new Exception('Bahan baku tidak ditemukan.');
            }

            if ((float)$ingredient['stok_gudang'] < $needed) {
                throw new Exception('Stok ' . $ingredient['nama_bahan'] . ' tidak cukup. Sisa: ' . $ingredient['stok_gudang']);
            }
        }

        $stockStmt->close();

        $orderStmt = $conn->prepare(
            'INSERT INTO orders
             (user_id, shift_id, nomor_meja, order_type, customer_name, total_bayar, metode_pembayaran, nominal_diterima, kembalian, status, tanggal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $orderStmt->bind_param(
            'iisssdsdds',
            $userId,
            $shiftId,
            $nomorMeja,
            $orderType,
            $customerName,
            $total,
            $metodePembayaran,
            $nominalDiterima,
            $kembalian,
            $status
        );

        $orderStmt->execute();
        $orderId = $conn->insert_id;
        $orderStmt->close();

        $detailStmt = $conn->prepare(
            'INSERT INTO order_details (order_id, menu_id, jumlah, harga_satuan, subtotal)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($cleanItems as $menuId => $qty) {
            $harga = (float)$menuData[$menuId]['harga'];
            $subtotal = $harga * $qty;
            $detailStmt->bind_param('iiidd', $orderId, $menuId, $qty, $harga, $subtotal);
            $detailStmt->execute();
        }

        $detailStmt->close();

        $criticalAlerts = [];

        $updateStmt = $conn->prepare('UPDATE ingredients SET stok_gudang = stok_gudang - ? WHERE id = ?');
        $afterStmt = $conn->prepare('SELECT nama_bahan, stok_gudang, batas_kritis FROM ingredients WHERE id = ?');

        foreach ($required as $ingredientId => $needed) {
            $updateStmt->bind_param('di', $needed, $ingredientId);
            $updateStmt->execute();

            $afterStmt->bind_param('i', $ingredientId);
            $afterStmt->execute();
            $after = $afterStmt->get_result()->fetch_assoc();

            if ((float)$after['stok_gudang'] <= (float)$after['batas_kritis']) {
                $criticalAlerts[] = $after['nama_bahan'] . ' tersisa ' . $after['stok_gudang'] . ' dan sudah masuk batas kritis.';
            }
        }

        $updateStmt->close();
        $afterStmt->close();

        $conn->commit();

        return [
            'success' => true,
            'order_id' => $orderId,
            'total' => $total,
            'kembalian' => $kembalian,
            'alerts' => $criticalAlerts
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}