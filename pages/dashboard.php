<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Dashboard Supermarket';

// 1. Ambil Statistik Kartu
// Kartu 1: Total Omzet / Penjualan Bulan Ini
$stmtOmzet = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) AS total_omzet, COUNT(sale_id) AS total_transaksi FROM sales WHERE MONTH(tanggal_transaksi) = MONTH(CURRENT_DATE()) AND YEAR(tanggal_transaksi) = YEAR(CURRENT_DATE())");
$statSales = $stmtOmzet->fetch();
$totalOmzet = $statSales['total_omzet'];
$totalTransaksiBulanIni = $statSales['total_transaksi'];

// Kartu 2: Total Keseluruhan Transaksi
$stmtTotalTrx = $pdo->query("SELECT COUNT(*) AS total FROM sales");
$totalTransaksi = $stmtTotalTrx->fetch()['total'];

// Kartu 3: Total Produk
$stmtTotalProduk = $pdo->query("SELECT COUNT(*) AS total FROM products");
$totalProduk = $stmtTotalProduk->fetch()['total'];

// Kartu 4: Produk Stok Menipis (Stok <= 10)
$stmtStokMenipis = $pdo->query("SELECT COUNT(*) AS total FROM products WHERE stok <= 10");
$totalStokKritis = $stmtStokMenipis->fetch()['total'];

// 2. Ambil Transaksi Terbaru (Limit 5)
$stmtRecentSales = $pdo->query("
    SELECT s.*, e.nama_lengkap AS nama_kasir, COALESCE(c.nama_lengkap, 'Umum / Non-Member') AS nama_pelanggan
    FROM sales s
    LEFT JOIN employees e ON s.employee_id = e.employee_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    ORDER BY s.tanggal_transaksi DESC
    LIMIT 5
");
$recentSales = $stmtRecentSales->fetchAll();

// 3. Ambil Produk Stok Kritis (Stok <= 10, Limit 5)
$stmtLowStockList = $pdo->query("
    SELECT p.*, c.nama_kategori 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.stok <= 10
    ORDER BY p.stok ASC
    LIMIT 5
");
$lowStockProducts = $stmtLowStockList->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Ringkasan Supermarket</h1>
        <p class="text-sm text-slate-500 mt-1">Pantau performa penjualan, pergerakan stok, dan aktivitas kasir secara realtime.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="sales.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-300"></i>
            <span>Transaksi Baru</span>
        </a>
        <a href="products.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200/90 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-2xl shadow-sm transition-all">
            <i data-lucide="package-plus" class="w-4 h-4 text-slate-500"></i>
            <span>Kelola Produk</span>
        </a>
    </div>
</div>

<!-- 4 KARTU STATISTIK SEJAJAR (DONEZO STYLE) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Kartu 1: Gradasi Hijau Tua (#14532D -> #166534) dengan Teks Putih -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#14532D] to-[#166534] p-6 text-white shadow-lg shadow-brand-900/15 flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-200/90">Penjualan Bulan Ini</span>
                <h3 class="text-2xl font-black mt-2 tracking-tight"><?= formatRupiah($totalOmzet) ?></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-md flex items-center justify-center text-emerald-300">
                <i data-lucide="wallet" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="mt-6 flex items-center justify-between text-xs text-emerald-100/90 pt-3 border-t border-white/10">
            <span class="flex items-center gap-1.5 font-medium">
                <i data-lucide="trending-up" class="w-3.5 h-3.5 text-emerald-300"></i>
                <?= $totalTransaksiBulanIni ?> transaksi tercatat
            </span>
            <span class="bg-emerald-800/80 text-[10px] font-bold px-2 py-0.5 rounded-full text-emerald-200">Bulan Ini</span>
        </div>
    </div>

    <!-- Kartu 2: Putih Bersih dengan Ikon Panah Melingkar di Pojok Kanan Atas -->
    <div class="relative rounded-2xl bg-white p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Transaksi</span>
                <h3 class="text-2xl font-black mt-2 text-slate-900 tracking-tight"><?= formatAngka($totalTransaksi) ?></h3>
            </div>
            <a href="sales.php" title="Buka Riwayat Penjualan" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 text-slate-400 hover:text-brand-900 hover:bg-emerald-50 hover:border-emerald-200 flex items-center justify-center transition-colors">
                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="mt-6 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
            <span class="flex items-center gap-1.5">
                <i data-lucide="receipt" class="w-3.5 h-3.5 text-brand-800"></i>
                Seluruh riwayat faktur
            </span>
            <span class="text-emerald-700 font-semibold text-[11px]">POS Aktif</span>
        </div>
    </div>

    <!-- Kartu 3: Putih Bersih dengan Ikon Panah Melingkar di Pojok Kanan Atas -->
    <div class="relative rounded-2xl bg-white p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Produk Aktif</span>
                <h3 class="text-2xl font-black mt-2 text-slate-900 tracking-tight"><?= formatAngka($totalProduk) ?></h3>
            </div>
            <a href="products.php" title="Buka Daftar Produk" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 text-slate-400 hover:text-brand-900 hover:bg-emerald-50 hover:border-emerald-200 flex items-center justify-center transition-colors">
                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="mt-6 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
            <span class="flex items-center gap-1.5">
                <i data-lucide="layers" class="w-3.5 h-3.5 text-brand-800"></i>
                Katalog supermarket
            </span>
            <span class="text-slate-600 font-semibold text-[11px]">Tersedia</span>
        </div>
    </div>

    <!-- Kartu 4: Putih Bersih dengan Ikon Panah Melingkar di Pojok Kanan Atas -->
    <div class="relative rounded-2xl bg-white p-6 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
        <div class="flex items-start justify-between">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Stok Menipis (&le; 10)</span>
                <h3 class="text-2xl font-black mt-2 <?= $totalStokKritis > 0 ? 'text-amber-600' : 'text-slate-900' ?> tracking-tight">
                    <?= formatAngka($totalStokKritis) ?>
                </h3>
            </div>
            <a href="products.php?filter=low_stock" title="Buka Produk Stok Rendah" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 text-slate-400 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 flex items-center justify-center transition-colors">
                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="mt-6 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
            <span class="flex items-center gap-1.5 <?= $totalStokKritis > 0 ? 'text-amber-600 font-semibold' : 'text-slate-500' ?>">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                <?= $totalStokKritis > 0 ? 'Perlu Restock Segera' : 'Stok Aman Terjaga' ?>
            </span>
            <span class="bg-amber-50 text-amber-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-200/60">Limit 10</span>
        </div>
    </div>
</div>

<!-- DUA TABEL RINGKASAN: TRANSAKSI TERBARU & STOK PRODUK MENIPIS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Tabel 1: Transaksi Penjualan Terbaru (Lebar 2 Kolom) -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-bold text-slate-900">Transaksi Penjualan Terbaru</h2>
                <p class="text-xs text-slate-400 mt-0.5">5 transaksi kasir terakhir yang berhasil diproses</p>
            </div>
            <a href="sales.php" class="text-xs font-semibold text-brand-800 hover:text-brand-900 flex items-center gap-1">
                <span>Lihat Semua</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                        <th class="pb-3 font-semibold">No. Faktur</th>
                        <th class="pb-3 font-semibold">Waktu</th>
                        <th class="pb-3 font-semibold">Kasir / Pelanggan</th>
                        <th class="pb-3 font-semibold text-right">Total Belanja</th>
                        <th class="pb-3 font-semibold text-center">Status</th>
                        <th class="pb-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentSales)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-xs">
                                <i data-lucide="receipt" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                Belum ada transaksi penjualan tercatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3.5 font-bold text-slate-800">
                                    <span class="font-mono text-xs text-brand-900 bg-brand-50 px-2 py-1 rounded-md border border-brand-200/60">
                                        <?= clean($sale['nomor_faktur']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-500">
                                    <?= date('d/m/Y H:i', strtotime($sale['tanggal_transaksi'])) ?>
                                </td>
                                <td class="py-3.5">
                                    <p class="text-xs font-semibold text-slate-800"><?= clean($sale['nama_pelanggan']) ?></p>
                                    <p class="text-[11px] text-slate-400">Kasir: <?= clean($sale['nama_kasir']) ?></p>
                                </td>
                                <td class="py-3.5 text-right font-bold text-slate-900 text-xs">
                                    <?= formatRupiah($sale['grand_total']) ?>
                                </td>
                                <td class="py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <?= clean($sale['status_transaksi']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 text-center">
                                    <a href="sales.php?detail=<?= $sale['sale_id'] ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-800 hover:text-brand-900 bg-slate-100 hover:bg-emerald-50 px-2.5 py-1 rounded-lg transition-colors">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>Struk</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel 2: Peringatan Stok Produk Menipis (Lebar 1 Kolom) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Peringatan Stok Menipis</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Produk dengan sisa stok &le; 10</p>
                </div>
                <a href="products.php" class="text-xs font-semibold text-brand-800 hover:text-brand-900">Semua</a>
            </div>

            <?php if (empty($lowStockProducts)): ?>
                <div class="py-12 text-center text-slate-400">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="check" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs font-semibold text-slate-700">Semua Stok Aman</p>
                    <p class="text-[11px] text-slate-400 mt-1">Tidak ada produk yang perlu di-restock saat ini.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($lowStockProducts as $low): ?>
                        <div class="p-3 rounded-xl border border-slate-100 hover:border-amber-200 bg-slate-50/50 hover:bg-amber-50/30 transition-all flex items-center justify-between">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="text-xs font-bold text-slate-800 truncate"><?= clean($low['nama_produk']) ?></p>
                                <p class="text-[11px] text-slate-400">SKU: <?= clean($low['sku']) ?> &bull; <?= clean($low['nama_kategori'] ?? 'Umum') ?></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="inline-block px-2.5 py-1 text-xs font-black rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                                    <?= formatAngka($low['stok'], 0) ?> <?= clean($low['satuan']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100">
            <a href="purchase_orders.php" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-xs font-bold text-brand-900 bg-brand-50 hover:bg-brand-100 border border-brand-200 transition-colors">
                <i data-lucide="truck" class="w-4 h-4"></i>
                <span>Buat Purchase Order Baru</span>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
