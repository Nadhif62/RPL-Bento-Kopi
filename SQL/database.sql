CREATE DATABASE IF NOT EXISTS bento_kopi;
USE bento_kopi;

DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS dining_tables;
DROP TABLE IF EXISTS shifts;
DROP TABLE IF EXISTS recipe_mapping;
DROP TABLE IF EXISTS menu;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('kasir','manager','finance') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_bahan VARCHAR(100) NOT NULL,
    satuan ENUM('gram','ml','pcs') NOT NULL DEFAULT 'gram',
    stok_gudang DECIMAL(10,2) NOT NULL DEFAULT 0,
    batas_kritis DECIMAL(10,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    kategori ENUM('promo','beverage','makanan','snack') NOT NULL DEFAULT 'makanan',
    harga DECIMAL(12,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE recipe_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    jumlah_dibutuhkan DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_recipe_menu FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mulai_shift DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    selesai_shift DATETIME NULL,
    petty_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    actual_cash DECIMAL(12,2) NULL,
    cash_difference DECIMAL(12,2) NULL,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_shift_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE dining_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(50) NOT NULL UNIQUE,
    table_label VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shift_id INT NOT NULL,
    nomor_meja VARCHAR(50) NOT NULL,
    order_type ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
    customer_name VARCHAR(100) NULL,
    total_bayar DECIMAL(12,2) NOT NULL DEFAULT 0,
    metode_pembayaran ENUM('tunai','qris') NOT NULL DEFAULT 'tunai',
    nominal_diterima DECIMAL(12,2) NULL,
    kembalian DECIMAL(12,2) NULL,
    status ENUM('open','paid','refunded') NOT NULL DEFAULT 'paid',
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_open_bill_meja (order_type, status, nomor_meja),
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_order_shift FOREIGN KEY (shift_id) REFERENCES shifts(id)
) ENGINE=InnoDB;

CREATE TABLE order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_detail_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_menu FOREIGN KEY (menu_id) REFERENCES menu(id)
) ENGINE=InnoDB;

CREATE TABLE refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    alasan TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    requested_by INT NULL,
    approved_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_requested_by FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_refund_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Catatan migrasi jika database lama sudah terpasang:
-- ALTER TABLE refunds MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';
-- ALTER TABLE refunds ADD COLUMN requested_by INT NULL AFTER refund_amount;
-- ALTER TABLE refunds ADD CONSTRAINT fk_refund_requested_by FOREIGN KEY (requested_by) REFERENCES users(id);
-- ALTER TABLE menu ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER harga;

-- Password semua akun demo: 123456
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('kasir', '123456', 'Kasir Demo', 'kasir'),
('manager', '123456', 'Manager Outlet', 'manager'),
('finance', '123456', 'Finance Pusat', 'finance');

INSERT INTO dining_tables (table_number, table_label, is_active) VALUES
('Meja 01', 'Meja 01', 1),
('Meja 02', 'Meja 02', 1),
('Meja 03', 'Meja 03', 1),
('Meja 04', 'Meja 04', 1),
('Meja 05', 'Meja 05', 1),
('Meja 06', 'Meja 06', 1),
('Meja 07', 'Meja 07', 1),
('Meja 08', 'Meja 08', 1),
('Meja 09', 'Meja 09', 1),
('Meja 10', 'Meja 10', 1),
('Meja 11', 'Meja 11', 1),
('Meja 12', 'Meja 12', 1);

INSERT INTO ingredients (nama_bahan, satuan, stok_gudang, batas_kritis) VALUES
('Beras', 'gram', 12000, 1000),
('Ayam', 'gram', 7000, 500),
('Kopi', 'gram', 3000, 300),
('Susu', 'ml', 6000, 400),
('Gula', 'gram', 4000, 300),
('Kentang', 'gram', 5000, 500),
('Tepung', 'gram', 3500, 400),
('Coklat', 'gram', 2000, 250),
('Matcha', 'gram', 1000, 150),
('Teh', 'gram', 1000, 150),
('Keju', 'gram', 1500, 200),
('Roti', 'pcs', 100, 20),
('Sosis', 'pcs', 80, 15),
('Mie', 'pcs', 120, 20),
('Telur', 'pcs', 150, 30),
('Pisang', 'pcs', 100, 20),
('Mozzarella', 'gram', 1500, 200),
('Saus Sambal', 'ml', 2500, 250),
('Daging', 'gram', 3500, 400),
('Selada', 'gram', 1500, 200),
('Lemon', 'pcs', 60, 10),
('Sirup Vanilla', 'ml', 1500, 200),
('Krimer', 'ml', 2000, 250),
('Bubuk Coklat', 'gram', 1200, 150),
('Nugget', 'pcs', 120, 20),
('Bawang Bombai', 'gram', 1200, 150),
('Pasta', 'gram', 2500, 250);

INSERT INTO menu (nama_menu, kategori, harga) VALUES
('Promo Hemat Kopi Susu', 'promo', 18000),
('Promo Latte + Croissant', 'promo', 32000),
('Promo Nasi Goreng + Es Teh', 'promo', 33000),
('Promo Bento + Lemon Tea', 'promo', 36000),
('Promo Kentang + Kopi Hitam', 'promo', 25000),
('Promo Matcha + Donat', 'promo', 32000),
('Promo Roti Bakar + Americano', 'promo', 27000),
('Promo Mie Goreng + Es Kopi Susu', 'promo', 34000),
('Promo Sosis Bakar + Milk Tea', 'promo', 29000),
('Promo Pisang Coklat + Cafe Latte', 'promo', 30000),
('Es Kopi Susu', 'beverage', 22000),
('Kopi Hitam', 'beverage', 12000),
('Matcha Latte', 'beverage', 25000),
('Americano', 'beverage', 18000),
('Cafe Latte', 'beverage', 24000),
('Cappuccino', 'beverage', 24000),
('Es Teh Manis', 'beverage', 10000),
('Lemon Tea', 'beverage', 15000),
('Chocolate Latte', 'beverage', 23000),
('Milk Tea', 'beverage', 21000),
('Chicken Bento', 'makanan', 25000),
('Nasi Goreng', 'makanan', 28000),
('Mie Goreng', 'makanan', 22000),
('Nasi Ayam Crispy', 'makanan', 27000),
('Rice Bowl Ayam', 'makanan', 26000),
('Bento Beef', 'makanan', 32000),
('Nasi Telur', 'makanan', 18000),
('Chicken Katsu', 'makanan', 29000),
('Ayam Geprek', 'makanan', 25000),
('Nasi Sosis', 'makanan', 20000),
('Kentang Goreng', 'snack', 18000),
('Croissant', 'snack', 20000),
('Donat Coklat', 'snack', 12000),
('Roti Bakar Coklat', 'snack', 17000),
('Sosis Bakar', 'snack', 15000),
('Pisang Coklat', 'snack', 16000),
('Tahu Crispy', 'snack', 14000),
('Onion Ring', 'snack', 16000),
('Nugget', 'snack', 17000),
('Mozzarella Stick', 'snack', 21000);

INSERT INTO recipe_mapping (menu_id, ingredient_id, jumlah_dibutuhkan) VALUES
(1, 3, 12), (1, 4, 100), (1, 5, 10),
(2, 3, 12), (2, 4, 120), (2, 12, 1), (2, 7, 60),
(3, 1, 150), (3, 2, 50), (3, 10, 8), (3, 5, 8),
(4, 1, 150), (4, 2, 100), (4, 21, 1), (4, 10, 8),
(5, 6, 120), (5, 3, 18), (5, 5, 8),
(6, 9, 12), (6, 4, 120), (6, 7, 50), (6, 8, 25),
(7, 12, 2), (7, 8, 20), (7, 3, 18),
(8, 14, 1), (8, 15, 1), (8, 3, 15), (8, 4, 100),
(9, 13, 2), (9, 10, 10), (9, 4, 120),
(10, 16, 1), (10, 8, 20), (10, 3, 12), (10, 4, 120),
(11, 3, 15), (11, 4, 120), (11, 5, 10),
(12, 3, 20), (12, 5, 8),
(13, 9, 12), (13, 4, 150), (13, 5, 12),
(14, 3, 18), (14, 5, 8),
(15, 3, 12), (15, 4, 140), (15, 23, 40),
(16, 3, 12), (16, 4, 120), (16, 23, 40),
(17, 10, 8), (17, 5, 10),
(18, 10, 8), (18, 21, 1), (18, 5, 10),
(19, 24, 12), (19, 4, 150), (19, 5, 12),
(20, 10, 10), (20, 4, 120), (20, 22, 20),
(21, 1, 150), (21, 2, 100),
(22, 1, 180), (22, 2, 80), (22, 15, 1),
(23, 14, 1), (23, 15, 1), (23, 18, 15),
(24, 1, 150), (24, 2, 100), (24, 7, 40),
(25, 1, 150), (25, 2, 90), (25, 20, 20),
(26, 1, 160), (26, 19, 90),
(27, 1, 150), (27, 15, 1),
(28, 1, 150), (28, 2, 100), (28, 7, 40),
(29, 1, 150), (29, 2, 90), (29, 18, 20),
(30, 1, 150), (30, 13, 2),
(31, 6, 120),
(32, 7, 80), (32, 11, 15),
(33, 7, 60), (33, 8, 30),
(34, 12, 2), (34, 8, 20),
(35, 13, 2), (35, 18, 15),
(36, 16, 1), (36, 8, 20),
(37, 7, 50), (37, 18, 15),
(38, 26, 40), (38, 7, 50),
(39, 25, 5),
(40, 17, 60), (40, 7, 50);
