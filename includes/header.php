<?php
/**
 * Header Layout Component
 * Sistem Manajemen Supermarket
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle) ?> - Supermarket Management</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532D', // Emerald Tua Utama ("Donezo")
                            950: '#052e16',
                        },
                        surface: '#F5F7F6',
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F5F7F6;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Print media styles */
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white !important;
            }
            main {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-emerald-100 selection:text-emerald-900">
    <div class="min-h-screen flex">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Area Konten Utama -->
        <div class="flex-1 ml-64 flex flex-col min-h-screen">
            <!-- Header Atas (Fixed / Sticky) -->
            <header class="no-print sticky top-0 z-20 bg-white/90 backdrop-blur-md border-b border-slate-200/80 px-8 py-3.5 flex items-center justify-between transition-all">
                <!-- Sisi Kiri Header: Pencarian Cepat -->
                <div class="flex items-center gap-4 flex-1 max-w-md">
                    <form action="products.php" method="GET" class="relative w-full">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="search" placeholder="Cari nama produk, SKU, barcode..." 
                               class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200/90 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white transition-all text-slate-700 placeholder:text-slate-400">
                    </form>
                </div>

                <!-- Sisi Kanan Header: Tanggal & Info Profil -->
                <div class="flex items-center gap-6">
                    <!-- Tanggal Hari Ini -->
                    <div class="hidden md:flex items-center gap-2 text-xs font-medium text-slate-500 bg-slate-100/80 px-3 py-1.5 rounded-lg border border-slate-200/60">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-brand-800"></i>
                        <span><?= formatTanggal(date('Y-m-d')) ?></span>
                    </div>

                    <!-- Shortcut Transaksi Cepat -->
                    <a href="sales.php?action=new" class="hidden sm:inline-flex items-center gap-2 px-3.5 py-1.5 bg-brand-50 text-brand-900 border border-brand-200 text-xs font-semibold rounded-xl hover:bg-brand-100 transition-colors">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-brand-800"></i>
                        <span>Kasir POS</span>
                    </a>

                    <!-- Profil User -->
                    <div class="flex items-center gap-3 pl-2 border-l border-slate-200">
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&h=120&q=80" 
                                 alt="Avatar Admin" 
                                 class="w-9 h-9 rounded-full object-cover ring-2 ring-brand-800 shadow-sm">
                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                        </div>
                        <div class="text-left hidden lg:block">
                            <p class="text-xs font-bold text-slate-800 leading-tight">Admin Supermarket</p>
                            <p class="text-[11px] font-medium text-brand-800">Administrator Utama</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 px-8 py-8">
                <!-- Flash Notification Container -->
                <?php renderFlash(); ?>
