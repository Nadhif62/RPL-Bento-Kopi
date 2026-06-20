<?php

function save_order(mysqli $conn, array $payload): array
{
    $userId = (int)($payload['user_id'] ?? 0);
    $shiftId = (int)($payload['shift_id'] ?? 0);
    $nomorMeja = trim((string)($payload['nomor_meja'] ?? ''));
    $orderType = $payload['order_type'] ?? 'dine_in';
    $customerName = trim((string)($payload['customer_name'] ?? ''));
    $metodePembayaran = $payload['metode_pembayaran'] ?? 'tunai';
    $nominalRaw = $payload['nominal_diterima'] ?? '';
    $nominalDiterima = 0;
    $status = $payload['status'] ?? 'paid';
    $openOrderId = (int)($payload['open_order_id'] ?? 0);
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


    // Open bill hanya berlaku untuk dine in. Takeaway secara default transaksi langsung/lunas.
    if ($orderType === 'takeaway') {
        $status = 'paid';
        $openOrderId = 0;
        if ($nomorMeja === '') {
            $nomorMeja = $customerName !== '' ? $customerName : 'Takeaway';
        }
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

    if ($orderType === 'dine_in' && $nomorMeja === '') {
        throw new Exception('Nomor meja dine in wajib dipilih.');
    }

    if ($customerName === '' && $openOrderId <= 0) {
        throw new Exception('Identitas/nama pelanggan wajib diisi.');
    }

    if (empty($cleanItems) && $openOrderId <= 0) {
        throw new Exception('Pilih minimal satu menu terlebih dahulu.');
    }

    $conn->begin_transaction();

    try {
        $totalTambahan = 0;
        $menuData = [];
        $required = [];

        if (!empty($cleanItems)) {
            $menuStmt = $conn->prepare(
                'SELECT id, nama_menu, harga, is_active 
                 FROM menu 
                 WHERE id = ? AND is_active = 1'
            );

            foreach ($cleanItems as $menuId => $qty) {
                $menuStmt->bind_param('i', $menuId);
                $menuStmt->execute();

                $menu = $menuStmt->get_result()->fetch_assoc();

                if (!$menu) {
                    throw new Exception('Menu tidak ditemukan.');
                }

                $menuData[$menuId] = $menu;
                $totalTambahan += ((float)$menu['harga'] * $qty);
            }

            $menuStmt->close();

            $recipeStmt = $conn->prepare(
                'SELECT ingredient_id, jumlah_dibutuhkan 
                 FROM recipe_mapping 
                 WHERE menu_id = ?'
            );

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

            $stockStmt = $conn->prepare(
                'SELECT id, nama_bahan, satuan, stok_gudang, batas_kritis 
                 FROM ingredients 
                 WHERE id = ? 
                 FOR UPDATE'
            );

            foreach ($required as $ingredientId => $needed) {
                $stockStmt->bind_param('i', $ingredientId);
                $stockStmt->execute();

                $ingredient = $stockStmt->get_result()->fetch_assoc();

                if (!$ingredient) {
                    throw new Exception('Bahan baku tidak ditemukan.');
                }

                if ((float)$ingredient['stok_gudang'] < $needed) {
                    throw new Exception(
                        'Stok ' . $ingredient['nama_bahan'] .
                        ' tidak cukup. Sisa: ' .
                        format_stok($ingredient['stok_gudang'], $ingredient['satuan'])
                    );
                }
            }

            $stockStmt->close();
        }

        $isAppendOpenBill = false;
        $orderId = 0;
        $existingTotal = 0;
        $billTotal = $totalTambahan;
        $kembalian = 0;

        $openOrder = null;

        if ($orderType === 'dine_in') {
            if ($openOrderId > 0) {
                $openStmt = $conn->prepare(
                    'SELECT id, total_bayar, customer_name
                     FROM orders
                     WHERE id = ?
                       AND nomor_meja = ?
                       AND order_type = "dine_in"
                       AND status = "open"
                     LIMIT 1
                     FOR UPDATE'
                );
                $openStmt->bind_param('is', $openOrderId, $nomorMeja);
            } else {
                $openStmt = $conn->prepare(
                    'SELECT id, total_bayar, customer_name
                     FROM orders
                     WHERE nomor_meja = ?
                       AND order_type = "dine_in"
                       AND status = "open"
                     ORDER BY id DESC
                     LIMIT 1
                     FOR UPDATE'
                );
                $openStmt->bind_param('s', $nomorMeja);
            }

            $openStmt->execute();
            $openOrder = $openStmt->get_result()->fetch_assoc();
            $openStmt->close();

            if ($openOrderId > 0 && !$openOrder) {
                throw new Exception('Open bill meja ini tidak ditemukan atau sudah lunas. Refresh halaman meja terlebih dahulu.');
            }
        }

        if ($openOrder) {
            $isAppendOpenBill = true;
            $orderId = (int)$openOrder['id'];
            $existingTotal = (float)$openOrder['total_bayar'];
            $billTotal = $existingTotal + $totalTambahan;

            if ($customerName === '') {
                $customerName = (string)($openOrder['customer_name'] ?? '');
            }

            if ($status === 'paid') {
                if ($metodePembayaran === 'qris') {
                    $nominalDiterima = $billTotal;
                }

                if ($metodePembayaran === 'tunai') {
                    $nominalDiterima = parse_numeric_input($nominalRaw, 'Nominal tunai', 0, true);
                    if ($nominalDiterima < $billTotal) {
                        throw new Exception('Nominal tunai tidak boleh kurang dari total tagihan open bill.');
                    }
                }

                $kembalian = $metodePembayaran === 'tunai'
                    ? max(0, $nominalDiterima - $billTotal)
                    : 0;

                $updateOrder = $conn->prepare(
                    'UPDATE orders
                     SET total_bayar = ?,
                         customer_name = CASE WHEN ? != "" THEN ? ELSE customer_name END,
                         metode_pembayaran = ?,
                         nominal_diterima = ?,
                         kembalian = ?,
                         status = "paid"
                     WHERE id = ?'
                );
                $updateOrder->bind_param(
                    'dsssddi',
                    $billTotal,
                    $customerName,
                    $customerName,
                    $metodePembayaran,
                    $nominalDiterima,
                    $kembalian,
                    $orderId
                );
                $updateOrder->execute();
                $updateOrder->close();
            } else {
                $updateOrder = $conn->prepare(
                    'UPDATE orders
                     SET total_bayar = ?,
                         customer_name = CASE WHEN ? != "" THEN ? ELSE customer_name END
                     WHERE id = ?'
                );
                $updateOrder->bind_param('dssi', $billTotal, $customerName, $customerName, $orderId);
                $updateOrder->execute();
                $updateOrder->close();
            }
        } else {
            if (empty($cleanItems)) {
                throw new Exception('Tidak ada menu tambahan untuk disimpan.');
            }

            if ($metodePembayaran === 'qris') {
                $nominalDiterima = $status === 'paid' ? $totalTambahan : 0;
            }

            if ($status === 'paid' && $metodePembayaran === 'tunai') {
                $nominalDiterima = parse_numeric_input($nominalRaw, 'Nominal tunai', 0, true);
                if ($nominalDiterima < $totalTambahan) {
                    throw new Exception('Nominal tunai tidak boleh kurang dari total bayar.');
                }
            }

            $kembalian = $metodePembayaran === 'tunai'
                ? max(0, $nominalDiterima - $totalTambahan)
                : 0;

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
                $totalTambahan,
                $metodePembayaran,
                $nominalDiterima,
                $kembalian,
                $status
            );

            $orderStmt->execute();
            $orderId = $conn->insert_id;
            $orderStmt->close();
        }

        foreach ($cleanItems as $menuId => $qty) {
            $harga = (float)$menuData[$menuId]['harga'];
            $subtotal = $harga * $qty;

            $detailCheck = $conn->prepare(
                'SELECT id 
                 FROM order_details 
                 WHERE order_id = ? AND menu_id = ?
                 LIMIT 1'
            );
            $detailCheck->bind_param('ii', $orderId, $menuId);
            $detailCheck->execute();

            $existingDetail = $detailCheck->get_result()->fetch_assoc();
            $detailCheck->close();

            if ($existingDetail) {
                $updateDetail = $conn->prepare(
                    'UPDATE order_details
                     SET jumlah = jumlah + ?,
                         subtotal = subtotal + ?
                     WHERE id = ?'
                );
                $detailId = (int)$existingDetail['id'];
                $updateDetail->bind_param('idi', $qty, $subtotal, $detailId);
                $updateDetail->execute();
                $updateDetail->close();
            } else {
                $detailStmt = $conn->prepare(
                    'INSERT INTO order_details (order_id, menu_id, jumlah, harga_satuan, subtotal)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $detailStmt->bind_param('iiidd', $orderId, $menuId, $qty, $harga, $subtotal);
                $detailStmt->execute();
                $detailStmt->close();
            }
        }

        $criticalAlerts = [];

        // Stok dikurangi saat item disimpan, baik langsung lunas maupun pending/open bill.
        // Dengan cara ini, open bill merepresentasikan stok yang sudah terpakai/direservasi.
        if (!empty($required)) {
            $updateStmt = $conn->prepare(
                'UPDATE ingredients 
                 SET stok_gudang = stok_gudang - ? 
                 WHERE id = ?'
            );

            $afterStmt = $conn->prepare(
                'SELECT nama_bahan, satuan, stok_gudang, batas_kritis 
                 FROM ingredients 
                 WHERE id = ?'
            );

            foreach ($required as $ingredientId => $needed) {
                $updateStmt->bind_param('di', $needed, $ingredientId);
                $updateStmt->execute();

                $afterStmt->bind_param('i', $ingredientId);
                $afterStmt->execute();

                $after = $afterStmt->get_result()->fetch_assoc();

                if ((float)$after['stok_gudang'] <= (float)$after['batas_kritis']) {
                    $criticalAlerts[] =
                        $after['nama_bahan'] .
                        ' tersisa ' .
                        format_stok($after['stok_gudang'], $after['satuan']) .
                        ' dan sudah masuk batas kritis.';
                }
            }

            $updateStmt->close();
            $afterStmt->close();
        }

        audit_log($conn, $status === 'paid' ? 'order_paid' : 'order_open', 'orders', $orderId, 'Order disimpan melalui POS.');

        $conn->commit();

        return [
            'success' => true,
            'order_id' => $orderId,
            'total' => $totalTambahan,
            'existing_total' => $existingTotal,
            'bill_total' => $billTotal,
            'kembalian' => $kembalian,
            'alerts' => $criticalAlerts,
            'is_append_open_bill' => $isAppendOpenBill,
            'status' => $status,
            'order_type' => $orderType
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
