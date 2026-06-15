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

## Struktur Role

### 1. Kasir

Kasir bertugas menjalankan proses transaksi harian di outlet.

Fitur kasir meliputi:

* Login sebagai kasir
* Start shift
* Input petty cash awal
* Membuat order dine-in
* Membuat order take away
* Memilih meja
* Menambahkan menu ke transaksi
* Menyimpan open bill atau pending order
* Melakukan pembayaran tunai
* Melakukan pembayaran QRIS
* Melihat status stok
* Mengajukan refund
* Menutup shift atau close shift
* Mendukung mode offline sederhana

---

### 2. Manager

Manager bertugas mengelola operasional outlet.

Fitur manager meliputi:

* Dashboard manager
* Manajemen produk
* Manajemen stok bahan
* Melihat riwayat order
* Audit kasir
* Menambahkan akun kasir
* Melihat jam terlaris
* Mengelola komplain dan refund
* Mengajukan refund ke finance
* Monitoring stok kritis

---

### 3. Finance

Finance bertugas mengawasi dan memvalidasi transaksi keuangan outlet yang tercatat dalam sistem.

Pada prototype ini, sistem difokuskan pada satu outlet Bento Kopi. Oleh karena itu, seluruh data cash flow, transaksi, refund, audit shift, dan pembukuan berasal dari satu outlet tersebut.

Fitur finance meliputi:

* Dashboard utama finance
* Validasi cash flow masuk dan keluar
* Audit shift kasir
* Persetujuan atau penolakan pengajuan refund dana
* Penguncian pembukuan bulanan
* Monitoring transaksi berdasarkan periode

---

## Struktur Halaman Finance

Fitur finance dipisahkan ke beberapa halaman agar lebih rapi dan mudah dipahami.

### 1. `finance.php`

Halaman utama finance yang berfungsi sebagai dashboard dan pusat navigasi fitur finance.

Isi halaman:

* Ringkasan gross sales
* Ringkasan refund approved
* Ringkasan net sales
* Ringkasan pending order
* Menu menuju halaman cash flow
* Menu menuju halaman audit shift
* Menu menuju halaman persetujuan refund
* Menu menuju halaman pembukuan bulanan
* Menu menuju halaman transaksi

---

### 2. `finance_cashflow.php`

Halaman untuk memvalidasi cash flow outlet.

Fitur halaman:

* Menampilkan cash in
* Menampilkan cash out
* Menampilkan total transaksi tunai
* Menampilkan total transaksi QRIS
* Menampilkan net cash flow
* Menampilkan rekap cash flow harian
* Filter berdasarkan tanggal mulai dan tanggal akhir

---

### 3. `finance_audit.php`

Halaman untuk melakukan audit shift kasir.

Fitur halaman:

* Menampilkan daftar shift kasir
* Menampilkan petty cash
* Menampilkan total tunai
* Menampilkan total QRIS
* Menampilkan actual cash
* Menampilkan selisih kas
* Menampilkan status audit
* Filter berdasarkan tanggal mulai dan tanggal akhir

---

### 4. `finance_refunds.php`

Halaman untuk menyetujui atau menolak pengajuan refund.

Fitur halaman:

* Menampilkan daftar pengajuan refund
* Menampilkan nama kasir
* Menampilkan manager yang mengajukan refund
* Menampilkan nominal refund
* Menampilkan alasan refund
* Menampilkan status refund
* Tombol approve refund
* Tombol reject refund

Jika refund disetujui, status order akan berubah menjadi refunded dan nilai refund akan tercatat sebagai cash out.

---

### 5. `finance_bookkeeping.php`

Halaman untuk mengunci pembukuan bulanan.

Fitur halaman:

* Memilih periode bulan
* Melihat cash in bulanan
* Melihat cash out bulanan
* Melihat net bulanan
* Melihat status pembukuan
* Mengecek refund pending
* Mengecek shift aktif
* Mengunci pembukuan bulanan
* Melihat riwayat pembukuan terkunci

Pembukuan tidak dapat dikunci apabila masih terdapat refund pending atau shift aktif pada periode bulan yang dipilih.

---

### 6. `finance_transactions.php`

Halaman untuk melihat daftar transaksi.

Fitur halaman:

* Menampilkan ID transaksi
* Menampilkan informasi order
* Menampilkan total pembayaran
* Menampilkan metode pembayaran
* Menampilkan status transaksi
* Menampilkan nama kasir
* Filter berdasarkan tanggal mulai dan tanggal akhir

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

## Alur Sistem

### 1. Alur Login

1. User membuka halaman login.
2. User memasukkan username dan password.
3. Sistem memvalidasi data login.
4. Sistem mengarahkan user ke halaman sesuai role.
5. Jika role kasir, user masuk ke halaman kasir.
6. Jika role manager, user masuk ke halaman manager.
7. Jika role finance, user masuk ke halaman finance.

---

### 2. Alur Shift Kasir

1. Kasir login ke sistem.
2. Kasir melakukan start shift.
3. Kasir memasukkan petty cash awal.
4. Kasir melakukan transaksi selama shift berlangsung.
5. Kasir dapat membuat order, open bill, pembayaran, dan pengajuan refund.
6. Setelah selesai, kasir melakukan close shift.
7. Sistem menghitung actual cash.
8. Sistem menyimpan data shift sebagai bahan audit manager dan finance.

---

### 3. Alur Order

1. Kasir memilih menu order.
2. Kasir memilih tipe order, yaitu dine-in atau take away.
3. Jika dine-in, kasir memilih meja.
4. Jika take away, kasir dapat mengisi nama customer.
5. Kasir menambahkan menu ke cart.
6. Kasir dapat menyimpan order sebagai open bill atau langsung melakukan pembayaran.
7. Jika pembayaran selesai, order berstatus paid.
8. Jika order belum dibayar, order berstatus open.

---

### 4. Alur Open Bill

1. Kasir membuat order dine-in.
2. Order disimpan sebagai open bill.
3. Meja terkait akan dianggap memiliki order aktif.
4. Kasir dapat membuka kembali order tersebut.
5. Kasir dapat menambahkan item atau melanjutkan pembayaran.
6. Setelah dibayar, status order berubah menjadi paid.

---

### 5. Alur Refund

1. Kasir atau manager menemukan transaksi yang perlu direfund.
2. Refund diajukan melalui sistem.
3. Manager dapat memantau pengajuan refund.
4. Finance membuka halaman persetujuan refund.
5. Finance dapat menyetujui atau menolak refund.
6. Jika disetujui, order berubah menjadi refunded.
7. Nilai refund masuk sebagai cash out pada laporan finance.

---

### 6. Alur Cash Flow Finance

1. Finance membuka halaman cash flow.
2. Finance memilih periode tanggal.
3. Sistem menampilkan cash in dari transaksi paid.
4. Sistem menampilkan cash out dari transaksi refunded.
5. Sistem menampilkan total transaksi tunai.
6. Sistem menampilkan total transaksi QRIS.
7. Sistem menghitung net cash flow.
8. Finance dapat menggunakan data tersebut sebagai validasi keuangan outlet.

---

### 7. Alur Audit Shift Finance

1. Finance membuka halaman audit shift.
2. Finance memilih periode tanggal.
3. Sistem menampilkan data shift kasir.
4. Sistem menampilkan petty cash, tunai, QRIS, actual cash, dan selisih.
5. Finance dapat melihat apakah terdapat selisih kas.
6. Finance dapat menggunakan data audit untuk mengevaluasi kesesuaian transaksi dan setoran.

---

### 8. Alur Penguncian Pembukuan Bulanan

1. Finance membuka halaman pembukuan bulanan.
2. Finance memilih periode bulan.
3. Sistem mengecek cash in, cash out, dan net cash flow bulanan.
4. Sistem mengecek apakah masih ada refund pending.
5. Sistem mengecek apakah masih ada shift aktif.
6. Jika tidak ada refund pending dan shift aktif, finance dapat mengunci pembukuan.
7. Setelah pembukuan dikunci, periode tersebut dianggap final.
8. Refund pada periode yang sudah dikunci tidak dapat diproses.

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

