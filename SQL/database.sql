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
    kategori ENUM('beverage','makanan','snack') NOT NULL DEFAULT 'makanan',
    harga DECIMAL(12,2) NOT NULL
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
    status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    approved_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_user FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

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
('Beras', 'gram', 10000, 1000),
('Ayam', 'gram', 5000, 500),
('Kopi', 'gram', 2000, 300),
('Susu', 'ml', 3000, 400),
('Gula', 'gram', 2500, 300),
('Kentang', 'gram', 4000, 500),
('Tepung', 'gram', 3000, 400),
('Coklat', 'gram', 1500, 250);

INSERT INTO menu (nama_menu, kategori, harga) VALUES
('Es Kopi Susu', 'beverage', 22000),
('Kopi Hitam', 'beverage', 12000),
('Matcha Latte', 'beverage', 25000),
('Chicken Bento', 'makanan', 25000),
('Nasi Goreng', 'makanan', 28000),
('Kentang Goreng', 'snack', 18000),
('Croissant', 'snack', 20000),
('Donat Coklat', 'snack', 12000);

INSERT INTO recipe_mapping (menu_id, ingredient_id, jumlah_dibutuhkan) VALUES
(1, 3, 15), (1, 4, 120), (1, 5, 10),
(2, 3, 20), (2, 5, 8),
(3, 4, 150), (3, 5, 12),
(4, 1, 150), (4, 2, 100),
(5, 1, 180), (5, 2, 80),
(6, 6, 120),
(7, 7, 80),
(8, 7, 60), (8, 8, 30);