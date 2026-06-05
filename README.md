# RPL-Bento-Kopi
# Bento Kopi UMS POS & Inventory

Prototype sistem **Point of Sales (POS)** dan **Inventory** untuk Bento Kopi UMS menggunakan **PHP Native, MySQL, JavaScript, HTML, CSS, Bootstrap 5**, dan **XAMPP**.

Sistem ini dibuat untuk membantu proses transaksi kasir, pengelolaan stok bahan baku, pencatatan shift, pencatatan penjualan, pengajuan refund, serta simulasi transaksi offline menggunakan LocalStorage.

## Fitur

* Login kasir, manager, dan finance
* Input order kasir
* Visual denah meja
* Metode pembayaran tunai dan QRIS
* Start shift dan close shift kasir
* Tambah akun kasir oleh manager
* Pemotongan stok bahan otomatis berdasarkan resep
* Notifikasi stok bahan kritis
* Order pending / open bill
* Pengambilan data open bill
* Pengajuan refund oleh kasir
* Approval refund oleh finance
* Refund otomatis mengurangi total pendapatan
* Riwayat transaksi dan filter tanggal
* Laporan sales harian kasir
* Simulasi offline mode menggunakan LocalStorage
* Sinkronisasi order offline ke database saat kembali online

## Teknologi

* PHP Native
* MySQL
* JavaScript
* HTML & CSS
* Bootstrap 5 CDN
* XAMPP

## Struktur Folder

```text
RPL-Bento-Kopi/
├─ index.php                 # Redirect ke halaman login
├─ Pages/                    # Halaman utama yang diakses pengguna
│  ├─ index.php              # Halaman login
│  ├─ kasir.php              # Dashboard kasir
│  ├─ manager.php            # Dashboard manager
│  ├─ finance.php            # Dashboard finance
│  ├─ sales.php              # Laporan sales harian
│  └─ shift.php              # Manajemen shift kasir
│
├─ Actions/                  # Proses form dan aksi backend
│  ├─ login_process.php
│  ├─ logout.php
│  ├─ start_shift.php
│  ├─ close_shift.php
│  ├─ process_cashier.php
│  ├─ process_inventory.php
│  ├─ process_order.php
│  ├─ process_refund.php
│  ├─ request_refund.php
│  └─ mark_paid.php
│
├─ API/                      # Endpoint JSON untuk JavaScript/fetch
│  ├─ get_all_open_bills.php
│  ├─ get_open_bill.php
│  └─ sync_offline.php
│
├─ Includes/                 # Konfigurasi dan fungsi utama
│  ├─ config.php
│  └─ order_service.php
│
├─ Assets/
│  └─ JS/
│     └─ offline_handler.js
│
├─ SQL/
  └─ database.sql
```

## Instalasi

1. Simpan project ke folder XAMPP:

```bash
D:\XAMPP\htdocs\RPL-Bento-Kopi\
```

2. Jalankan **Apache** dan **MySQL** melalui XAMPP Control Panel.

3. Buka phpMyAdmin melalui browser:

```bash
http://localhost/phpmyadmin
```

4. Import database dari file:

```bash
SQL/database.sql
```

5. Pastikan konfigurasi database pada file berikut sudah sesuai:

```bash
Includes/config.php
```

Konfigurasi default:

```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bento_kopi';
```

6. Buka aplikasi melalui browser:

```bash
http://localhost/RPL-Bento-Kopi/
```

## Akun Demo

Semua akun demo menggunakan password:

```bash
123456
```

| Username | Password | Role    |
| -------- | -------- | ------- |
| kasir    | 123456   | Kasir   |
| manager  | 123456   | Manager |
| finance  | 123456   | Finance |

## Alur Penggunaan Singkat

1. Login sebagai **kasir** untuk melakukan transaksi penjualan.
2. Kasir harus melakukan **start shift** terlebih dahulu sebelum input order.
3. Kasir dapat memilih menu, memasukkan nomor meja atau nama pelanggan, lalu menyimpan order.
4. Jika transaksi belum langsung dibayar, order dapat disimpan sebagai **open bill**.
5. Jika terjadi pembatalan transaksi, kasir dapat mengajukan **refund**.
6. Login sebagai **finance** untuk menyetujui refund.
7. Login sebagai **manager** untuk mengelola akun kasir, stok bahan baku, dan memantau data operasional.
