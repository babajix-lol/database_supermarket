<?php
/**
 * Sidebar Navigation Component
 * Sistem Manajemen Supermarket
 * Style: "Donezo" Modern Emerald (#14532D)
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!-- Sidebar Kiri Fixed -->
<aside class="no-print w-64 fixed inset-y-0 left-0 bg-white border-r border-slate-200/80 flex flex-col z-30 select-none shadow-[2px_0_12px_rgba(0,0,0,0.02)]">
    <!-- Brand / Logo Supermarket -->
    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
        <a href="dashboard.php" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-brand-900 flex items-center justify-center text-white shadow-md shadow-brand-900/20 group-hover:scale-105 transition-transform">
                <i data-lucide="store" class="w-5 h-5 text-emerald-300"></i>
            </div>
            <div>
                <span class="text-base font-extrabold text-slate-900 tracking-tight block">SUPERMARKET</span>
                <span class="text-[10px] font-semibold tracking-wider uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Management Pro</span>
            </div>
        </a>
    </div>

    <!-- Navigasi Menu -->
    <div class="flex-1 overflow-y-auto px-4 py-5 space-y-6">
        <!-- Kelompok MENU -->
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">MENU</p>
            <nav class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'dashboard.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="layout-grid" class="w-4 h-4 <?= ($current_page === 'dashboard.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Produk -->
                <a href="products.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'products.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="package" class="w-4 h-4 <?= ($current_page === 'products.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Produk</span>
                </a>

                <!-- Kategori -->
                <a href="categories.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'categories.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="tags" class="w-4 h-4 <?= ($current_page === 'categories.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Kategori</span>
                </a>

                <!-- Supplier -->
                <a href="suppliers.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'suppliers.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="truck" class="w-4 h-4 <?= ($current_page === 'suppliers.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Supplier</span>
                </a>

                <!-- Pelanggan -->
                <a href="customers.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'customers.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="users" class="w-4 h-4 <?= ($current_page === 'customers.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Pelanggan</span>
                </a>

                <!-- Karyawan -->
                <a href="employees.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'employees.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="badge-check" class="w-4 h-4 <?= ($current_page === 'employees.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Karyawan</span>
                </a>
            </nav>
        </div>

        <!-- Kelompok TRANSAKSI -->
        <div>
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">TRANSAKSI</p>
            <nav class="space-y-1">
                <!-- Penjualan / Kasir -->
                <a href="sales.php" 
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'sales.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="receipt" class="w-4 h-4 <?= ($current_page === 'sales.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                        <span>Penjualan / Kasir</span>
                    </div>
                    <span class="text-[10px] bg-brand-800 text-white font-bold px-1.5 py-0.5 rounded-md">POS</span>
                </a>

                <!-- Purchase Order -->
                <a href="purchase_orders.php" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= ($current_page === 'purchase_orders.php') 
                       ? 'bg-emerald-50 text-brand-900 border-l-4 border-brand-900 pl-2.5 font-bold shadow-sm' 
                       : 'text-slate-600 hover:text-brand-900 hover:bg-slate-50' ?>">
                    <i data-lucide="file-text" class="w-4 h-4 <?= ($current_page === 'purchase_orders.php') ? 'text-brand-900' : 'text-slate-400' ?>"></i>
                    <span>Purchase Order</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Profil Footer Mini di Sidebar -->
    <div class="p-4 border-t border-slate-100">
        <div class="bg-slate-50 p-3 rounded-2xl flex items-center justify-between border border-slate-200/60">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-brand-900 flex items-center justify-center font-bold text-xs">
                    SM
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800">Supermarket DB</p>
                    <p class="text-[10px] text-slate-400">MySQL Localhost</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 text-[10px] text-emerald-600 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Ready
            </span>
        </div>
    </div>
</aside>
