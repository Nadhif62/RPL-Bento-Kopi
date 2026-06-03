# RPL-Bento-Kopi
# Bento Kopi UMS POS & Inventory

Prototype sistem POS dan inventory untuk Bento Kopi UMS menggunakan **PHP Native, MySQL, JavaScript, dan Bootstrap 5**.

## Fitur

* Login kasir, admin, dan finance
* Input order kasir
* Visual denah meja
* Metode pembayaran tunai dan QRIS
* Start shift dan close shift kasir
* Tambah akun kasir
* Pemotongan stok bahan otomatis berdasarkan resep
* Notifikasi stok kritis
* Order pending / open bill
* Pengajuan dan approval refund
* Refund otomatis mengurangi total pendapatan
* Riwayat transaksi dan filter tanggal
* Simulasi offline mode dengan LocalStorage

## Teknologi

* PHP Native
* MySQL
* JavaScript
* HTML & CSS
* Bootstrap 5 CDN
* XAMPP

## Instalasi

1. Simpan project ke folder:

```bash
D:\XAMPP\htdocs\RPL-Bento-Kopi\
```

2. Import database:

```bash
database.sql
```

melalui phpMyAdmin.

3. Pastikan konfigurasi database di `config.php`:

```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bento_kopi';
```

4. Jalankan Apache dan MySQL di XAMPP.

5. Buka browser:

```bash
http://localhost/RPL-Bento-Kopi/
```

## Akun Demo

Semua password:

```bash
123456
```

| Username | Role    |
| -------- | ------- |
| kasir    | Kasir   |
| admin    | Admin   |
| finance  | Finance |

