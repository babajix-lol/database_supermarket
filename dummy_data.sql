-- ========================================================
-- SISTEM MANAJEMEN SUPERMARKET (CRUD)
-- Database: supermarket_db
-- ========================================================

CREATE DATABASE IF NOT EXISTS `supermarket_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `supermarket_db`;

-- Matikan cek foreign key sementara agar truncate/drop aman jika dijalankan ulang
SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABEL CATEGORIES
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `kode_kategori` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `kode_kategori` (`kode_kategori`),
  KEY `fk_category_parent` (`parent_id`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. TABEL SUPPLIERS
CREATE TABLE IF NOT EXISTS `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_supplier` varchar(30) NOT NULL,
  `nama_perusahaan` varchar(150) NOT NULL,
  `kontak_person` varchar(120) DEFAULT NULL,
  `nomor_telepon` varchar(30) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `kode_supplier` (`kode_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. TABEL CUSTOMERS
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_no` varchar(30) DEFAULT NULL,
  `tier_id` int(11) DEFAULT 1,
  `nama_lengkap` varchar(160) NOT NULL,
  `nomor_telepon` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `poin_member` int(11) DEFAULT 0,
  `tanggal_bergabung` date DEFAULT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `membership_no` (`membership_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. TABEL EMPLOYEES
CREATE TABLE IF NOT EXISTS `employees` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL DEFAULT 2, -- 1: Admin, 2: Kasir, 3: Gudang
  `nama_lengkap` varchar(160) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nomor_telepon` varchar(30) DEFAULT NULL,
  `status_aktif` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. TABEL PRODUCTS
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_member` decimal(12,2) DEFAULT NULL,
  `stok` decimal(12,3) DEFAULT 0.000,
  `satuan` varchar(10) NOT NULL DEFAULT 'Pcs',
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `fk_product_category` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. TABEL PURCHASE_ORDERS
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `po_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nomor_po` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `tanggal_pesan` date NOT NULL,
  `total_biaya` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status_pembelian` varchar(20) DEFAULT 'Draft',
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `nomor_po` (`nomor_po`),
  KEY `fk_po_supplier` (`supplier_id`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. TABEL SALES & SALE_ITEMS
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `nomor_faktur` varchar(30) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `tanggal_transaksi` timestamp NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_diskon` decimal(14,2) DEFAULT 0.00,
  `total_pajak` decimal(14,2) DEFAULT 0.00,
  `grand_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_bayar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(14,2) DEFAULT 0.00,
  `status_transaksi` varchar(20) DEFAULT 'Selesai',
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `nomor_faktur` (`nomor_faktur`),
  KEY `fk_sale_employee` (`employee_id`),
  KEY `fk_sale_customer` (`customer_id`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
  `sale_item_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `jumlah_beli` decimal(12,3) NOT NULL DEFAULT 1.000,
  `harga_satuan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `diskon_item` decimal(12,2) DEFAULT 0.00,
  `subtotal_item` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`sale_item_id`),
  KEY `fk_sale_item_sale` (`sale_id`),
  KEY `fk_sale_item_product` (`product_id`),
  CONSTRAINT `fk_sale_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- DATA CONTOH (DUMMY DATA)
-- ========================================================

-- Kategori
INSERT INTO `categories` (`category_id`, `parent_id`, `nama_kategori`, `kode_kategori`) VALUES
(1, NULL, 'Makanan & Minuman', 'CAT-FNB'),
(2, 1, 'Snack & Biskuit', 'CAT-SNK'),
(3, 1, 'Minuman Dingin', 'CAT-BEV'),
(4, NULL, 'Sayuran & Buah Segar', 'CAT-FRV'),
(5, NULL, 'Kebutuhan Rumah Tangga', 'CAT-HSD'),
(6, NULL, 'Perawatan Diri & Mandi', 'CAT-BCP')
ON DUPLICATE KEY UPDATE `nama_kategori`=VALUES(`nama_kategori`);

-- Supplier
INSERT INTO `suppliers` (`supplier_id`, `kode_supplier`, `nama_perusahaan`, `kontak_person`, `nomor_telepon`, `alamat`) VALUES
(1, 'SUP-001', 'PT Indofood Sukses Makmur Tbk', 'Budi Hartono', '081234567890', 'Jl. Jend. Sudirman Kav. 45, Jakarta'),
(2, 'SUP-002', 'PT Unilever Indonesia Tbk', 'Dewi Anggraini', '081987654321', 'Kav. Commercial Park BSD, Tangerang'),
(3, 'SUP-003', 'PT Sayur Segar Nusantara', 'Ahmad Fauzi', '085612345678', 'Jl. Raya Lembang No. 12, Bandung Barat'),
(4, 'SUP-004', 'PT Sumber Alfaria Utama', 'Rina Wijaya', '087711223344', 'Kawasan Industri Pulogadung, Jakarta Timur')
ON DUPLICATE KEY UPDATE `nama_perusahaan`=VALUES(`nama_perusahaan`);

-- Pelanggan / Member
INSERT INTO `customers` (`customer_id`, `membership_no`, `tier_id`, `nama_lengkap`, `nomor_telepon`, `email`, `poin_member`, `tanggal_bergabung`) VALUES
(1, 'MBR-2024-001', 1, 'Budi Santoso', '081223344550', 'budi.santoso@gmail.com', 180, '2024-01-15'),
(2, 'MBR-2024-002', 2, 'Siti Nurhaliza', '081334455661', 'siti.nur@gmail.com', 95, '2024-03-20'),
(3, 'MBR-2024-003', 3, 'Agus Setiawan', '081556677882', 'agus.setiawan@yahoo.com', 40, '2024-06-10'),
(4, 'MBR-2024-004', 3, 'Diana Putri', '081889900113', 'diana.putri@hotmail.com', 65, '2024-07-01')
ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`);

-- Karyawan (Password: admin123, kasir123, gudang123)
INSERT INTO `employees` (`employee_id`, `role_id`, `nama_lengkap`, `email`, `password`, `nomor_telepon`, `status_aktif`) VALUES
(1, 1, 'Rian Pratama (Admin)', 'admin@supermarket.com', '$2y$10$AXZL9pzF8aNoXamfaEcVeOc/07kWOpRARwP.ILajKO5qNVXtwLK2q', '081122334455', 1),
(2, 2, 'Sarah Melati (Kasir)', 'kasir@supermarket.com', '$2y$10$mtyt3G1OyDieQ8EUeKTxAObLjz.xmSPgbpmuCvmyzf4GyEIgbMb6G', '081223344667', 1),
(3, 3, 'Dimas Aryo (Gudang)', 'gudang@supermarket.com', '$2y$10$mtyt3G1OyDieQ8EUeKTxAObLjz.xmSPgbpmuCvmyzf4GyEIgbMb6G', '081334455778', 1)
ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`);

-- Produk
INSERT INTO `products` (`product_id`, `sku`, `barcode`, `nama_produk`, `category_id`, `harga_beli`, `harga_jual`, `harga_member`, `stok`, `satuan`) VALUES
(1, 'SKU-INDO-01', '899999900001', 'Indomie Goreng Spesial 85g', 2, 2700.00, 3500.00, 3300.00, 120.000, 'Pcs'),
(2, 'SKU-MYS-01', '899999900002', 'Minyak Goreng Bimoli 2L', 1, 32000.00, 37500.00, 36000.00, 45.000, 'Pch'),
(3, 'SKU-BYS-01', '899999900003', 'Beras Ramos Super 5Kg', 1, 65000.00, 74000.00, 72000.00, 30.000, 'Sak'),
(4, 'SKU-SML-01', '899999900004', 'Susu Ultra Milk Cokelat 1L', 3, 16500.00, 19500.00, 18500.00, 50.000, 'Kotak'),
(5, 'SKU-MNG-01', '899999900005', 'Mangga Harum Manis Segar', 4, 22000.00, 28000.00, 26500.00, 8.000, 'Kg'),
(6, 'SKU-SBN-01', '899999900006', 'Sabun Cair Lifebuoy 450ml', 6, 21000.00, 26000.00, 24500.00, 5.000, 'Pch'),
(7, 'SKU-DET-01', '899999900007', 'Deterjen Rinso Matic 1.8Kg', 5, 48000.00, 55000.00, 53000.00, 15.000, 'Bag'),
(8, 'SKU-ROTI-01', '899999900008', 'Roti Tawar Kupas Sari Roti', 1, 13000.00, 16000.00, 15000.00, 6.000, 'Bks'),
(9, 'SKU-COCA-01', '899999900009', 'Coca-Cola Botol Dingin 390ml', 3, 5000.00, 6500.00, 6000.00, 60.000, 'Btl'),
(10, 'SKU-TISU-01', '899999900010', 'Tisu Wajah Paseo 250 Sheets', 5, 12000.00, 15500.00, 14500.00, 25.000, 'Pcs')
ON DUPLICATE KEY UPDATE `nama_produk`=VALUES(`nama_produk`);

-- Purchase Orders
INSERT INTO `purchase_orders` (`po_id`, `nomor_po`, `supplier_id`, `tanggal_pesan`, `total_biaya`, `status_pembelian`) VALUES
(1, 'PO-20241101-001', 1, '2024-11-01', 1500000.00, 'Selesai'),
(2, 'PO-20241115-002', 2, '2024-11-15', 3250000.00, 'Diproses'),
(3, 'PO-20241120-003', 3, '2024-11-20', 850000.00, 'Draft')
ON DUPLICATE KEY UPDATE `nomor_po`=VALUES(`nomor_po`);

-- Transaksi Penjualan (Sales)
INSERT INTO `sales` (`sale_id`, `nomor_faktur`, `employee_id`, `customer_id`, `tanggal_transaksi`, `subtotal`, `total_diskon`, `total_pajak`, `grand_total`, `total_bayar`, `kembalian`, `status_transaksi`) VALUES
(1, 'INV-20241120-001', 2, 1, '2024-11-20 10:30:00', 128500.00, 5000.00, 0.00, 123500.00, 150000.00, 26500.00, 'Selesai'),
(2, 'INV-20241121-002', 2, 2, '2024-11-21 14:15:00', 42000.00, 0.00, 0.00, 42000.00, 50000.00, 8000.00, 'Selesai')
ON DUPLICATE KEY UPDATE `nomor_faktur`=VALUES(`nomor_faktur`);

-- Rincian Item Penjualan (Sale Items)
INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `jumlah_beli`, `harga_satuan`, `diskon_item`, `subtotal_item`) VALUES
(1, 1, 2, 1.000, 37500.00, 0.00, 37500.00),
(2, 1, 3, 1.000, 74000.00, 5000.00, 69000.00),
(3, 1, 1, 4.000, 3500.00, 0.00, 14000.00),
(4, 1, 9, 1.000, 6500.00, 0.00, 6500.00),
(5, 2, 6, 1.000, 26000.00, 0.00, 26000.00),
(6, 2, 8, 1.000, 16000.00, 0.00, 16000.00)
ON DUPLICATE KEY UPDATE `sale_item_id`=VALUES(`sale_item_id`);

SET FOREIGN_KEY_CHECKS = 1;
