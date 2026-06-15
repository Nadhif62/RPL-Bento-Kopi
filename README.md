# RPL Bento Kopi - Point of Sales System

## Deskripsi Sistem

RPL Bento Kopi adalah sistem Point of Sales (POS) berbasis PHP Native yang dirancang untuk mendukung proses transaksi, pengelolaan shift, pengelolaan stok, pengajuan refund, validasi cash flow, serta pembukuan keuangan pada satu outlet Bento Kopi.

Sistem ini dibuat sebagai prototype untuk kebutuhan tugas Rekayasa Perangkat Lunak (RPL). Oleh karena itu, sistem difokuskan pada satu outlet agar alur utama aplikasi dapat berjalan dengan jelas, sederhana, dan mudah dipahami.

Sistem memiliki tiga role utama, yaitu:

* Kasir
* Manager
* Finance

Setiap role memiliki akses dan fungsi yang berbeda sesuai dengan tanggung jawabnya masing-masing.

---

## Tujuan Sistem

Sistem ini bertujuan untuk:

1. Membantu kasir dalam melakukan transaksi penjualan.
2. Membantu manager dalam mengelola produk, stok, kasir, dan refund.
3. Membantu finance dalam memvalidasi cash flow, mengaudit shift, menyetujui refund, dan mengunci pembukuan bulanan.
4. Menyediakan alur POS sederhana yang sesuai dengan kebutuhan prototype satu outlet.
5. Mengurangi proses pencatatan manual pada transaksi, shift, stok, dan laporan keuangan.

---

## Teknologi yang Digunakan

* PHP Native
* MySQL atau MariaDB
* HTML
* CSS
* JavaScript
* Bootstrap
* AJAX untuk beberapa kebutuhan interaktif
* phpMyAdmin untuk pengelolaan database

---

---

## Struktur Folder Project

```text
RPL-Bento-Kopi/
├── API/
│   ├── get_all_open_bills.php
│   ├── get_open_bill.php
│   └── sync_offline.php
│
├── Actions/
│   ├── close_shift.php
│   ├── lock_bookkeeping.php
│   ├── login_process.php
│   ├── logout.php
│   ├── mark_paid.php
│   ├── process_cashier.php
│   ├── process_inventory.php
│   ├── process_order.php
│   ├── process_refund.php
│   ├── request_refund.php
│   └── start_shift.php
│
├── Assets/
│   └── CSS/
│       └── app.css
│
├── Includes/
│   ├── config.php
│   ├── finance_helpers.php
│   └── order_service.php
│
├── Pages/
│   ├── ajukan_refund.php
│   ├── audit_kasir.php
│   ├── cek_order.php
│   ├── finance.php
│   ├── finance_audit.php
│   ├── finance_bookkeeping.php
│   ├── finance_cashflow.php
│   ├── finance_refunds.php
│   ├── finance_transactions.php
│   ├── index.php
│   ├── jam_terlaris.php
│   ├── kasir.php
│   ├── manage_products.php
│   ├── manage_stock.php
│   ├── manager.php
│   ├── manager_refunds.php
│   ├── menu.php
│   ├── order.php
│   ├── order_history.php
│   ├── order_success.php
│   ├── payment.php
│   ├── sales.php
│   ├── status_stock.php
│   └── tambah_kasir.php
│
├── SQL/
│   └── database.sql
│
├── index.php
└── README.md
```

---

## Database

File database tersedia pada:

```text
SQL/database.sql
```

Database utama yang digunakan adalah:

```sql
bento_kopi
```

Tabel utama dalam sistem:

* users
* ingredients
* menu
* recipe_mapping
* shifts
* dining_tables
* orders
* order_details
* refunds
* monthly_closings

---

## Tabel `users`

Tabel `users` digunakan untuk menyimpan akun pengguna sistem.

Role yang tersedia:

* kasir
* manager
* finance

Akun demo:

| Username | Password | Role    |
| -------- | -------- | ------- |
| kasir    | 123456   | kasir   |
| manager  | 123456   | manager |
| finance  | 123456   | finance |

---

## Tabel `orders`

Tabel `orders` digunakan untuk menyimpan data transaksi.

Status order:

* open
* paid
* refunded

Metode pembayaran:

* tunai
* qris

Tipe order:

* dine_in
* takeaway

---

## Tabel `shifts`

Tabel `shifts` digunakan untuk menyimpan data shift kasir.

Data yang disimpan:

* user kasir
* waktu mulai shift
* waktu selesai shift
* petty cash
* actual cash
* cash difference
* status shift

Status shift:

* active
* closed

---

## Tabel `refunds`

Tabel `refunds` digunakan untuk menyimpan pengajuan refund.

Status refund:

* pending
* approved
* rejected

Data refund digunakan oleh finance untuk memutuskan apakah pengajuan refund disetujui atau ditolak.

---

## Tabel `monthly_closings`

Tabel `monthly_closings` digunakan untuk menyimpan data pembukuan bulanan yang sudah dikunci oleh finance.

Data yang disimpan:

* periode bulan
* user finance yang mengunci pembukuan
* catatan pembukuan
* waktu penguncian pembukuan

---

## Cara Instalasi

### 1. Clone atau salin project

Letakkan folder project ke dalam direktori server lokal, misalnya:

```text
htdocs/RPL-Bento-Kopi
```

Jika menggunakan XAMPP, letakkan project di folder:

```text
C:/xampp/htdocs/
```

---

### 2. Buat database

Buka phpMyAdmin, lalu buat database dengan nama:

```sql
bento_kopi
```

---

### 3. Import database

Import file berikut ke dalam database:

```text
SQL/database.sql
```

---

### 4. Atur konfigurasi database

Buka file:

```text
Includes/config.php
```

Pastikan konfigurasi database sesuai dengan server lokal:

```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bento_kopi';
```

---

### 5. Jalankan aplikasi

Buka browser dan akses:

```text
http://localhost/RPL-Bento-Kopi/
```

---

