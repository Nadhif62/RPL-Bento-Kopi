# RPL Bento Kopi

## Bento Kopi UMS POS & Inventory

Prototype sistem **Point of Sales (POS)** dan **Inventory** untuk operasional Bento Kopi UMS. Aplikasi ini dibangun menggunakan **PHP Native**, **MySQL**, **JavaScript**, **HTML**, **CSS**, **Bootstrap 5 CDN**, serta dijalankan secara lokal melalui **XAMPP**.

Sistem ini berfokus pada pengelolaan transaksi kasir, pencatatan shift, pengelolaan stok bahan baku, pemotongan stok otomatis berdasarkan resep, pemantauan order pending/open bill, pencatatan refund, serta simulasi transaksi offline menggunakan **LocalStorage** sebelum disinkronkan ke database.

---

## Ringkasan Fitur

### 1. Login dan Hak Akses

Aplikasi memiliki tiga role pengguna utama:

| Role | Fungsi Utama |
| --- | --- |
| **Kasir** | Melakukan transaksi, membuka dan menutup shift, membuat order, mengecek open bill, melihat status stok, serta melakukan sinkronisasi order offline. |
| **Manager** | Memantau ringkasan operasional, mengelola stok bahan, mengelola produk/menu, melihat riwayat order, audit kasir, menambah akun kasir, melihat jam terlaris, dan mengajukan refund. |
| **Finance** | Memeriksa transaksi dan memproses persetujuan atau penolakan refund. |

---

## Fitur Kasir

- Login sebagai kasir.
- Start shift dengan input **petty cash/kas awal**.
- Dashboard kasir dengan ringkasan:
  - total transaksi shift aktif,
  - total sales,
  - open bill,
  - status shift.
- Input order berdasarkan tipe:
  - **Dine In**,
  - **Takeaway**.
- Visual denah meja untuk transaksi dine in.
- Status meja otomatis berubah ketika terdapat open bill.
- Input identitas pelanggan.
- Pemilihan menu berdasarkan kategori:
  - promo,
  - beverage/minuman,
  - makanan,
  - snack.
- Penambahan item ke open bill yang sudah ada.
- Pembayaran menggunakan:
  - tunai,
  - QRIS.
- Perhitungan nominal diterima dan kembalian.
- Fitur **Cek Order** untuk melihat transaksi, open bill, dan menandai order lunas.
- Fitur **Status Stock** untuk melihat stok bahan dan bahan yang sudah masuk batas kritis.
- Fitur **Sales Shift** untuk melihat:
  - petty cash,
  - total cash,
  - total QRIS,
  - total sales,
  - actual cash.
- Close shift langsung melalui tombol close shift.
- Order pending/open bill tetap dapat dibawa ke shift berikutnya.
- Mode offline berbasis LocalStorage.
- Sinkronisasi order offline ke database ketika kembali online.

---

## Fitur Manager

- Dashboard manager dengan ringkasan operasional.
- **Manajemen Produk** pada halaman terpisah untuk melihat produk/menu, kategori, harga, dan status aktif/nonaktif.
- **Kelola Stock Bahan** untuk menambah, memperbarui, dan melakukan restock bahan baku.
- **Order History** untuk melihat riwayat seluruh transaksi.
- **Audit Kasir** untuk memantau shift kasir, estimasi setoran, dan data closing.
- **Tambah Kasir** untuk membuat akun kasir baru.
- **Jam Terlaris** untuk melihat jam transaksi tertinggi.
- **Komplain dan Refund** untuk mengajukan refund atas transaksi yang sudah dibayar.

---

## Fitur Finance

- Dashboard finance untuk memantau transaksi dan refund.
- Melihat daftar refund yang diajukan.
- Menyetujui atau menolak refund.
- Refund yang disetujui akan mengubah status order menjadi **refunded**.
- Ringkasan data finance mencakup transaksi dan status refund.

---

## Fitur Stok dan Inventory

Sistem menggunakan tabel bahan baku dan mapping resep untuk mengurangi stok secara otomatis ketika order berhasil disimpan.

Alur stok:

1. Kasir memilih menu dan jumlah pesanan.
2. Sistem membaca kebutuhan bahan dari tabel `recipe_mapping`.
3. Sistem mengecek ketersediaan stok pada tabel `ingredients`.
4. Jika stok cukup, order disimpan.
5. Stok bahan otomatis dikurangi sesuai resep.
6. Jika stok berada di bawah atau sama dengan batas kritis, sistem menampilkan peringatan stok kritis.

---

## Fitur Open Bill dan Pending Order

Open bill digunakan untuk transaksi **dine in** yang belum langsung dibayar.

Ketentuan open bill:

- Open bill hanya berlaku untuk order dine in.
- Takeaway selalu diproses sebagai transaksi langsung/lunas.
- Open bill dapat ditambahkan item baru selama statusnya masih `open`.
- Open bill dapat dilunasi melalui halaman pembayaran atau halaman cek order.
- Jika kasir melakukan close shift saat masih ada open bill, sistem memberi informasi bahwa order pending akan dibawa ke shift berikutnya.

---

## Fitur Offline Mode

Aplikasi menyediakan simulasi mode offline menggunakan **LocalStorage** pada browser.

Fungsi offline mode:

- Kasir dapat mengaktifkan mode offline.
- Order disimpan sementara di LocalStorage.
- Order offline dapat dilihat sebagai antrean/pending lokal.
- Ketika koneksi kembali normal, kasir dapat melakukan sinkronisasi.
- Endpoint `API/sync_offline.php` digunakan untuk menyimpan order offline ke database.

Catatan: data offline hanya tersimpan pada browser/perangkat yang digunakan. Jika cache/local storage browser dihapus, antrean offline dapat hilang.

---

## Teknologi yang Digunakan

| Komponen | Teknologi |
| --- | --- |
| Backend | PHP Native |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript |
| UI Framework | Bootstrap 5 CDN |
| Penyimpanan Offline | Browser LocalStorage |
| Server Lokal | XAMPP |

---

## Struktur Folder Project

```text
RPL-Bento-Kopi/
├─ index.php
├─ README.md
│
├─ API/
│  ├─ get_all_open_bills.php
│  ├─ get_open_bill.php
│  └─ sync_offline.php
│
├─ Actions/
│  ├─ close_shift.php
│  ├─ login_process.php
│  ├─ logout.php
│  ├─ mark_paid.php
│  ├─ process_cashier.php
│  ├─ process_inventory.php
│  ├─ process_order.php
│  ├─ process_refund.php
│  ├─ request_refund.php
│  └─ start_shift.php
│
├─ Assets/
│  ├─ CSS/
│  │  └─ app.css
│  └─ JS/
│     ├─ app.js
│     └─ offline_handler.js
│
├─ Includes/
│  ├─ config.php
│  └─ order_service.php
│
├─ Pages/
│  ├─ ajukan_refund.php
│  ├─ audit_kasir.php
│  ├─ cek_order.php
│  ├─ finance.php
│  ├─ index.php
│  ├─ jam_terlaris.php
│  ├─ kasir.php
│  ├─ manage_products.php
│  ├─ manage_stock.php
│  ├─ manager.php
│  ├─ manager_refunds.php
│  ├─ menu.php
│  ├─ order.php
│  ├─ order_history.php
│  ├─ order_success.php
│  ├─ payment.php
│  ├─ sales.php
│  ├─ status_stock.php
│  └─ tambah_kasir.php
│
└─ SQL/
   └─ database.sql
```

---

## Struktur Database

Database default bernama:

```sql
bento_kopi
```

Tabel utama yang digunakan:

| Tabel | Fungsi |
| --- | --- |
| `users` | Menyimpan data akun kasir, manager, dan finance. |
| `ingredients` | Menyimpan data bahan baku, satuan, stok gudang, dan batas kritis. |
| `menu` | Menyimpan data menu, kategori, harga, dan status aktif. |
| `recipe_mapping` | Menyimpan kebutuhan bahan untuk setiap menu. |
| `shifts` | Menyimpan data shift kasir, petty cash, actual cash, dan status shift. |
| `dining_tables` | Menyimpan data meja dine in. |
| `orders` | Menyimpan data transaksi/order. |
| `order_details` | Menyimpan rincian menu pada setiap order. |
| `refunds` | Menyimpan data pengajuan dan status refund. |

---

## Cara Instalasi

### 1. Salin Project ke Folder XAMPP

Letakkan folder project pada direktori `htdocs`.

Contoh:

```bash
D:\XAMPP\htdocs\RPL-Bento-Kopi\
```

### 2. Jalankan XAMPP

Aktifkan service berikut melalui XAMPP Control Panel:

- Apache
- MySQL

### 3. Import Database

Buka phpMyAdmin melalui browser:

```text
http://localhost/phpmyadmin
```

Lalu import file database berikut:

```text
SQL/database.sql
```

File tersebut akan membuat database `bento_kopi`, tabel utama, akun demo, data meja, data bahan baku, data menu, dan mapping resep awal.

### 4. Cek Konfigurasi Database

Buka file:

```text
Includes/config.php
```

Konfigurasi default:

```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bento_kopi';
```

Sesuaikan konfigurasi tersebut jika username, password, atau nama database berbeda.

### 5. Jalankan Aplikasi

Buka aplikasi melalui browser:

```text
http://localhost/RPL-Bento-Kopi/
```

Aplikasi akan diarahkan ke halaman login.

---

## Akun Demo

Semua akun demo menggunakan password:

```text
123456
```

| Username | Password | Role |
| --- | --- | --- |
| `kasir` | `123456` | Kasir |
| `manager` | `123456` | Manager |
| `finance` | `123456` | Finance |

---

## Alur Penggunaan Singkat

### Alur Kasir

1. Login sebagai `kasir`.
2. Input petty cash untuk melakukan start shift.
3. Pilih menu **Order**.
4. Pilih tipe transaksi: **Dine In** atau **Takeaway**.
5. Untuk dine in, pilih meja dan isi identitas pelanggan.
6. Pilih menu dan jumlah pesanan.
7. Lanjut ke pembayaran.
8. Pilih status transaksi:
   - bayar langsung,
   - simpan sebagai open bill.
9. Jika bayar langsung, pilih metode pembayaran tunai atau QRIS.
10. Setelah transaksi selesai, cek ringkasan melalui menu **Sales Shift**.
11. Tekan **Close Shift** ketika operasional kasir selesai.

### Alur Manager

1. Login sebagai `manager`.
2. Buka dashboard manager.
3. Kelola stok bahan melalui **Kelola Stock Bahan**.
4. Kelola produk/menu melalui **Manajemen Produk**.
5. Cek seluruh transaksi melalui **Order History**.
6. Audit aktivitas kasir melalui **Audit Kasir**.
7. Tambahkan akun kasir melalui **Tambah Kasir**.
8. Pantau jam transaksi tertinggi melalui **Jam Terlaris**.
9. Ajukan refund melalui **Komplain dan Refund** jika diperlukan.

### Alur Finance

1. Login sebagai `finance`.
2. Buka dashboard finance.
3. Periksa daftar refund yang masuk.
4. Pilih aksi **approve** atau **reject**.
5. Jika refund disetujui, status transaksi akan berubah menjadi `refunded`.

---

## Catatan Status Transaksi

| Status | Keterangan |
| --- | --- |
| `open` | Order dine in masih pending/open bill dan belum lunas. |
| `paid` | Order sudah dibayar. |
| `refunded` | Order sudah disetujui untuk refund. |

---


