# Sistem Manajemen Supermarket (Donezo Style) - PHP Native & MySQL

Aplikasi web sistem manajemen supermarket (CRUD) yang dibangun menggunakan **PHP Native (PDO Sederhana)** dan **Tailwind CSS via CDN**, dengan gaya desain modern terinspirasi dari **"Donezo" style** (Aksen Hijau Emerald Tua `#14532D`, kartu putih bersih `rounded-2xl`, dan background `#F5F7F6`).

---

## 🚀 Fitur Utama

1. **Dashboard Eksekutif Modern**:
   - **4 Kartu Statistik Sejajar**:
     - *Kartu 1*: Gradasi Emerald Tua (`#14532D` &rarr; `#166534`) untuk total omzet penjualan bulan berjalan.
     - *Kartu 2*: Total transaksi keseluruhan dengan ikon panah melingkar.
     - *Kartu 3*: Total produk aktif dalam inventori.
     - *Kartu 4*: Peringatan stok menipis (&le; 10) untuk kontrol restock segera.
   - **Tabel Transaksi Terbaru**: Menampilkan 5 transaksi kasir terakhir dengan quick view struk.
   - **Peringatan Stok Rendah**: Menampilkan produk dengan sisa stok minim.

2. **Katalog Produk & Inventori (`products`)**:
   - Tambah, lihat/cari (SKU, Barcode, Nama Produk), filter kategori, filter stok rendah, edit, dan hapus.
   - Otomatis menghitung potensi margin keuntungan (`harga_jual - harga_beli`).
   - Indikator badge stok: **Aman**, **Menipis (&le; 10)**, dan **Kritis (&le; 5)**.

3. **Klasifikasi Kategori (`categories`)**:
   - CRUD Kategori dengan dukungan kategori bertingkat (*Parent-Child Subcategory*).
   - Menghitung jumlah produk di setiap kategori.

4. **Mitra Pemasok / Vendor (`suppliers`)**:
   - CRUD data supplier, kode supplier, kontak person (PIC), nomor telepon, dan alamat kantor/gudang.
   - Menghitung riwayat Purchase Order dari masing-masing supplier.

5. **Pelanggan & Program Member (`customers`)**:
   - CRUD data pelanggan, nomor membership (`MBR-YYYY-XXX`), tier loyalty (Platinum VIP, Gold, Silver), poin belanja, dan total akumulasi transaksi.

6. **Petugas Karyawan & Kasir (`employees`)**:
   - CRUD data staf supermarket dengan enkripsi password aman (`password_hash` bcrypt).
   - Multi-role: **Administrator**, **Kasir POS**, dan **Staff Gudang**.
   - Status akun aktif / nonaktif.

7. **Pengadaan Barang / Purchase Order (`purchase_orders`)**:
   - CRUD PO ke supplier, tanggal pemesanan, estimasi total biaya, dan status pengadaan (*Draft*, *Diproses*, *Selesai / Diterima*, *Dibatalkan*).

8. **Kasir POS & Transaksi Penjualan (`sales` & `sale_items`)**:
   - Form kasir interaktif dengan baris produk dinamis (*multi-item cart* via Vanilla JS).
   - Kalkulasi otomatis secara realtime: *Subtotal*, *Diskon Tambahan*, *Pajak PPN*, *Grand Total*, dan *Kembalian*.
   - Tombol nominal cepat (*Quick Cash*): Uang Pas, +10k, +20k, +50k, +100k, dan Reset.
   - **Database Transaction (ACID)**: Otomatis memvalidasi stok dan **memotong stok produk pada tabel `products`**.
   - Otomatis menambah poin loyalitas member.
   - **Pratinjau & Cetak Struk Belanja**: Tampilan struk kasir thermal 80mm yang siap cetak (`window.print()`).
   - **Fitur Pembatalan Transaksi**: Mengembalikan stok produk secara otomatis jika transaksi dibatalkan.

---

## 📂 Struktur File

```text
/CRUD_supermarket
├── config/
│   └── koneksi.php              # Koneksi PDO MySQL & fungsi helper global
├── includes/
│   ├── header.php               # Header HTML, Tailwind CDN, Lucide Icons, Top Bar
│   ├── sidebar.php              # Sidebar kiri fixed dengan indikator menu aktif
│   └── footer.php               # Footer & inisialisasi script Lucide icons
├── pages/
│   ├── dashboard.php            # Dashboard metrik & ringkasan stok/penjualan
│   ├── categories.php           # CRUD Kategori & Subkategori produk
│   ├── products.php             # CRUD Katalog Produk & status inventori
│   ├── suppliers.php            # CRUD Mitra Supplier / Vendor
│   ├── customers.php            # CRUD Data Pelanggan & Member Poin
│   ├── employees.php            # CRUD Akun Karyawan & Petugas Kasir
│   ├── purchase_orders.php      # CRUD Purchase Order pengadaan barang
│   └── sales.php                # Transaksi Kasir POS, Struk & Riwayat
├── index.php                    # Redirect otomatis ke pages/dashboard.php
├── dummy_data.sql               # Skema tabel & data contoh (siap impor)
├── supermarket_db.sql           # Salinan database sql supermarket_db
└── README.md                    # Dokumentasi lengkap aplikasi
```

---

## 🛠️ Panduan Instalasi & Menjalankan

1. **Jalankan XAMPP**:
   - Buka **XAMPP Control Panel**, lalu aktifkan modul **Apache** dan **MySQL**.

2. **Impor Database**:
   - Buka phpMyAdmin di browser: `http://localhost/phpmyadmin/`
   - Buat database baru bernama `supermarket_db` (atau jika sudah ada, langsung pilih).
   - Klik tab **Import**, pilih file `dummy_data.sql` (atau `supermarket_db.sql`) yang ada di dalam folder proyek ini, lalu klik **Import / Go**.

3. **Buka Aplikasi**:
   - Akses via browser:
     ```text
     http://localhost/CRUD_supermarket/
     ```

---

## 🔑 Data Contoh Akun Karyawan

| Nama Petugas | Email | Password | Role |
| :--- | :--- | :--- | :--- |
| **Rian Pratama** | `admin@supermarket.com` | `admin123` | Administrator Utama |
| **Sarah Melati** | `kasir@supermarket.com` | `kasir123` | Kasir POS |
| **Dimas Aryo** | `gudang@supermarket.com` | `gudang123` | Staff Gudang |

---

## 🎨 Palet Desain
- **Background Utama**: `#F5F7F6`
- **Kartu Konten**: `#FFFFFF` dengan `rounded-2xl` dan border tipis halus `border-slate-200/80`
- **Warna Aksen Utama**: Hijau Emerald Tua `#14532D` (*Hover:* `#166534`)
- **Font**: Plus Jakarta Sans via Google Fonts
