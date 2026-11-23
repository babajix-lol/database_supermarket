<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Penjualan & Kasir POS';

// PROSES POST: SIMPAN TRANSAKSI PENJUALAN BARU
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // SIMPAN TRANSAKSI KASIR
    if ($action === 'create_sale') {
        $nomor_faktur   = strtoupper(trim($_POST['nomor_faktur'] ?? ''));
        $employee_id    = (int)($_POST['employee_id'] ?? 0);
        $customer_id    = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $subtotal       = (float)($_POST['subtotal'] ?? 0);
        $total_diskon   = (float)($_POST['total_diskon'] ?? 0);
        $total_pajak    = (float)($_POST['total_pajak'] ?? 0);
        $grand_total    = (float)($_POST['grand_total'] ?? 0);
        $total_bayar    = (float)($_POST['total_bayar'] ?? 0);
        $kembalian      = (float)($_POST['kembalian'] ?? 0);
        $product_ids    = $_POST['product_id'] ?? [];
        $quantities     = $_POST['jumlah_beli'] ?? [];
        $prices         = $_POST['harga_satuan'] ?? [];
        $item_discounts = $_POST['diskon_item'] ?? [];

        if (empty($nomor_faktur)) {
            $nomor_faktur = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        if (empty($employee_id)) {
            setFlash('error', 'Silakan pilih kasir yang bertugas!');
            header("Location: sales.php?view=pos");
            exit;
        }

        if (empty($product_ids) || count($product_ids) === 0) {
            setFlash('error', 'Keranjang belanja masih kosong! Pilih minimal 1 produk.');
            header("Location: sales.php?view=pos");
            exit;
        }

        if ($total_bayar < $grand_total) {
            setFlash('error', 'Uang pembayaran kurang dari Grand Total belanja!');
            header("Location: sales.php?view=pos");
            exit;
        }

        // DATABASE TRANSACTION
        try {
            $pdo->beginTransaction();

            // 1. Simpan Header Sales
            $stmtSale = $pdo->prepare("
                INSERT INTO sales (nomor_faktur, employee_id, customer_id, tanggal_transaksi, subtotal, total_diskon, total_pajak, grand_total, total_bayar, kembalian, status_transaksi)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, 'Selesai')
            ");
            $stmtSale->execute([
                $nomor_faktur, $employee_id, $customer_id, 
                $subtotal, $total_diskon, $total_pajak, $grand_total, $total_bayar, $kembalian
            ]);
            $sale_id = $pdo->lastInsertId();

            // 2. Simpan Item & Kurangi Stok
            $stmtItem = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, jumlah_beli, harga_satuan, diskon_item, subtotal_item)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtCheckStock = $pdo->prepare("SELECT product_id, nama_produk, stok FROM products WHERE product_id = ? FOR UPDATE");
            $stmtReduceStock = $pdo->prepare("UPDATE products SET stok = stok - ? WHERE product_id = ?");

            for ($i = 0; $i < count($product_ids); $i++) {
                $p_id  = (int)$product_ids[$i];
                $qty   = (float)$quantities[$i];
                $price = (float)$prices[$i];
                $disc  = (float)($item_discounts[$i] ?? 0);
                $sub   = ($qty * $price) - $disc;

                if ($p_id <= 0 || $qty <= 0) continue;

                // Cek ketersediaan stok
                $stmtCheckStock->execute([$p_id]);
                $prod = $stmtCheckStock->fetch();
                if (!$prod) {
                    throw new Exception("Produk ID {$p_id} tidak ditemukan di database!");
                }
                if ($prod['stok'] < $qty) {
                    throw new Exception("Stok untuk produk '{$prod['nama_produk']}' tidak mencukupi! (Sisa: {$prod['stok']}, diminta: {$qty})");
                }

                // Insert ke sale_items
                $stmtItem->execute([$sale_id, $p_id, $qty, $price, $disc, $sub]);

                // Kurangi stok produk secara otomatis
                $stmtReduceStock->execute([$qty, $p_id]);
            }

            // 3. Tambah poin member jika ada customer
            if ($customer_id) {
                $poinDidapat = (int)floor($grand_total / 10000); // 1 poin tiap Rp 10.000
                if ($poinDidapat > 0) {
                    $stmtPoin = $pdo->prepare("UPDATE customers SET poin_member = poin_member + ? WHERE customer_id = ?");
                    $stmtPoin->execute([$poinDidapat, $customer_id]);
                }
            }

            $pdo->commit();
            setFlash('success', "Transaksi '{$nomor_faktur}' berhasil disimpan! Stok produk otomatis terpotong.");
            header("Location: sales.php?detail={$sale_id}");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Transaksi gagal diproses: ' . $e->getMessage());
            header("Location: sales.php?view=pos");
            exit;
        }
    }

    // BATALKAN / HAPUS TRANSAKSI (MENGEMBALIKAN STOK)
    if ($action === 'delete_sale') {
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        if ($sale_id > 0) {
            try {
                $pdo->beginTransaction();

                // Ambil semua item penjualan untuk mengembalikan stok
                $stmtGetItems = $pdo->prepare("SELECT product_id, jumlah_beli FROM sale_items WHERE sale_id = ?");
                $stmtGetItems->execute([$sale_id]);
                $items = $stmtGetItems->fetchAll();

                $stmtRestoreStock = $pdo->prepare("UPDATE products SET stok = stok + ? WHERE product_id = ?");
                foreach ($items as $item) {
                    $stmtRestoreStock->execute([$item['jumlah_beli'], $item['product_id']]);
                }

                // Hapus penjualan (cascade menghapus sale_items)
                $stmtDel = $pdo->prepare("DELETE FROM sales WHERE sale_id = ?");
                $stmtDel->execute([$sale_id]);

                $pdo->commit();
                setFlash('success', 'Transaksi berhasil dibatalkan dan seluruh stok produk telah dikembalikan!');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
            }
        }
        header("Location: sales.php");
        exit;
    }
}

// AMBIL MASTER DATA UNTUK FORM KASIR
$employees = $pdo->query("SELECT employee_id, nama_lengkap FROM employees WHERE status_aktif = 1 ORDER BY nama_lengkap ASC")->fetchAll();
$customers = $pdo->query("SELECT customer_id, nama_lengkap, membership_no, tier_id FROM customers ORDER BY nama_lengkap ASC")->fetchAll();
$products  = $pdo->query("SELECT product_id, sku, barcode, nama_produk, harga_jual, harga_member, stok, satuan FROM products WHERE stok > 0 ORDER BY nama_produk ASC")->fetchAll();

// MODE TAMPILAN: 'pos' (Kasir) ATAU 'history' (Riwayat)
$view = $_GET['view'] ?? 'history';
if (isset($_GET['action']) && $_GET['action'] === 'new') {
    $view = 'pos';
}

// RIWAYAT TRANSAKSI PENJUALAN
$search = trim($_GET['search'] ?? '');
$sqlSales = "
    SELECT s.*, e.nama_lengkap AS nama_kasir, COALESCE(c.nama_lengkap, 'Umum / Non-Member') AS nama_pelanggan,
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) AS total_item
    FROM sales s
    LEFT JOIN employees e ON s.employee_id = e.employee_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    WHERE 1=1
";
$paramsSales = [];
if (!empty($search)) {
    $sqlSales .= " AND (s.nomor_faktur LIKE ? OR e.nama_lengkap LIKE ? OR c.nama_lengkap LIKE ?)";
    $paramsSales[] = "%{$search}%";
    $paramsSales[] = "%{$search}%";
    $paramsSales[] = "%{$search}%";
}
$sqlSales .= " ORDER BY s.tanggal_transaksi DESC";
$stmtSales = $pdo->prepare($sqlSales);
$stmtSales->execute($paramsSales);
$salesHistory = $stmtSales->fetchAll();

// DETAIL STRUK JIKA PARAMETER ?detail=ID DIBERIKAN
$detailSale = null;
$detailItems = [];
if (!empty($_GET['detail'])) {
    $detail_id = (int)$_GET['detail'];
    $stmtD = $pdo->prepare("
        SELECT s.*, e.nama_lengkap AS nama_kasir, c.nama_lengkap AS nama_pelanggan, c.membership_no
        FROM sales s
        LEFT JOIN employees e ON s.employee_id = e.employee_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        WHERE s.sale_id = ?
    ");
    $stmtD->execute([$detail_id]);
    $detailSale = $stmtD->fetch();

    if ($detailSale) {
        $stmtI = $pdo->prepare("
            SELECT si.*, p.nama_produk, p.satuan, p.sku
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            WHERE si.sale_id = ?
        ");
        $stmtI->execute([$detail_id]);
        $detailItems = $stmtI->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman & Toggle Tab View -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Kasir POS & Transaksi Penjualan</h1>
        <p class="text-sm text-slate-500 mt-1">Lakukan transaksi belanja pelanggan, cetak faktur/struk, dan pantau riwayat penjualan.</p>
    </div>
    <div class="flex items-center gap-2 bg-slate-200/60 p-1.5 rounded-2xl">
        <a href="sales.php?view=pos" 
           class="px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 transition-all <?= ($view === 'pos') ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i>
            <span>Kasir Baru (POS)</span>
        </a>
        <a href="sales.php?view=history" 
           class="px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 transition-all <?= ($view === 'history') ? 'bg-brand-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i data-lucide="history" class="w-4 h-4"></i>
            <span>Riwayat Penjualan</span>
        </a>
    </div>
</div>

<?php if ($view === 'pos'): ?>
<!-- ======================================================== -->
<!-- VIEW 1: FORM KASIR TRANSAKSI BARU (POS)                  -->
<!-- ======================================================== -->
<form method="POST" action="" id="posForm" class="space-y-6">
    <input type="hidden" name="action" value="create_sale">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Header Kasir & Baris Item Produk (Lebar 2 Kolom) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Informasi Faktur & Kasir -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-4 h-4 text-brand-900"></i>
                    <span>Informasi Faktur & Kasir</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">No. Faktur</label>
                        <input type="text" name="nomor_faktur" value="INV-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?>" readonly
                               class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono font-bold text-brand-900 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kasir Bertugas <span class="text-rose-500">*</span></label>
                        <select name="employee_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 text-slate-800">
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['employee_id'] ?>"><?= clean($emp['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Pelanggan (Opsional)</label>
                        <select name="customer_id" id="customerSelect" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 text-slate-800">
                            <option value="">-- Umum / Non-Member --</option>
                            <?php foreach ($customers as $cst): ?>
                                <option value="<?= $cst['customer_id'] ?>">
                                    <?= clean($cst['nama_lengkap']) ?> (<?= clean($cst['membership_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Keranjang Produk Yang Dibeli -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Daftar Belanjaan Pelanggan</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Pilih produk, tentukan jumlah beli, subtotal terhitung otomatis</p>
                    </div>
                    <button type="button" onclick="tambahBarisProduk()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand-50 text-brand-900 hover:bg-brand-100 border border-brand-200 text-xs font-bold rounded-xl transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Tambah Item</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="cartTable">
                        <thead>
                            <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                                <th class="pb-2 font-semibold min-w-[200px]">Produk</th>
                                <th class="pb-2 font-semibold text-right w-28">Harga (Rp)</th>
                                <th class="pb-2 font-semibold text-center w-24">Qty</th>
                                <th class="pb-2 font-semibold text-right w-24">Diskon (Rp)</th>
                                <th class="pb-2 font-semibold text-right w-32">Subtotal (Rp)</th>
                                <th class="pb-2 font-semibold text-center w-12"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody" class="divide-y divide-slate-100">
                            <!-- Baris pertama otomatis di-render oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Rangkuman Pembayaran & Kasir Selesai (Lebar 1 Kolom) -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sticky top-24">
                <h3 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100 mb-4 flex items-center justify-between">
                    <span>Ringkasan Bayar</span>
                    <span class="text-xs font-mono text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md font-bold">POS Ready</span>
                </h3>

                <!-- Rincian Biaya -->
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Subtotal Belanja</span>
                        <span id="labelSubtotal" class="font-bold text-slate-800">Rp 0</span>
                        <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
                    </div>

                    <div class="flex items-center justify-between text-slate-600">
                        <span>Total Diskon</span>
                        <div class="w-32">
                            <input type="number" step="100" name="total_diskon" id="inputTotalDiskon" value="0" min="0" oninput="hitungTotalKeseluruhan()"
                                   class="w-full text-right px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-800">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-slate-600">
                        <span>Pajak PPN (Rp)</span>
                        <div class="w-32">
                            <input type="number" step="100" name="total_pajak" id="inputTotalPajak" value="0" min="0" oninput="hitungTotalKeseluruhan()"
                                   class="w-full text-right px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-800">
                        </div>
                    </div>

                    <!-- Grand Total Big Display -->
                    <div class="pt-3 border-t border-slate-100">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-1">Grand Total</span>
                        <div id="labelGrandTotal" class="text-3xl font-black text-brand-900 tracking-tight">Rp 0</div>
                        <input type="hidden" name="grand_total" id="inputGrandTotal" value="0">
                    </div>

                    <!-- Input Uang Bayar -->
                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Jumlah Uang Diterima (Rp) <span class="text-rose-500">*</span></label>
                        <input type="number" step="500" name="total_bayar" id="inputTotalBayar" required min="0" placeholder="0" oninput="hitungKembalian()"
                               class="w-full px-3.5 py-3 text-lg font-black bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-brand-900 focus:bg-white transition-all">

                        <!-- Tombol Cepat Nominal Uang (Quick Cash) -->
                        <div class="grid grid-cols-3 gap-1.5 mt-2.5">
                            <button type="button" onclick="setNominalPas()" class="py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 transition-colors">Uang Pas</button>
                            <button type="button" onclick="tambahNominal(10000)" class="py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 transition-colors">+10.000</button>
                            <button type="button" onclick="tambahNominal(20000)" class="py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 transition-colors">+20.000</button>
                            <button type="button" onclick="tambahNominal(50000)" class="py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 transition-colors">+50.000</button>
                            <button type="button" onclick="tambahNominal(100000)" class="py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 transition-colors">+100.000</button>
                            <button type="button" onclick="clearNominal()" class="py-1 px-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-[11px] font-bold text-rose-700 transition-colors">Reset</button>
                        </div>
                    </div>

                    <!-- Kembalian Display -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Kembalian</span>
                            <div id="labelKembalian" class="text-xl font-bold text-slate-800">Rp 0</div>
                            <input type="hidden" name="kembalian" id="inputKembalian" value="0">
                        </div>
                        <span id="badgeLunas" class="text-xs font-extrabold px-2.5 py-1 rounded-full bg-slate-100 text-slate-400">Belum Cukup</span>
                    </div>

                    <!-- Tombol Submit Simpan Transaksi -->
                    <div class="pt-4">
                        <button type="submit" id="btnProses" class="w-full py-3.5 px-4 bg-brand-900 hover:bg-brand-800 text-white font-extrabold rounded-xl shadow-lg shadow-brand-900/20 flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-300"></i>
                            <span>Selesaikan & Simpan Transaksi</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Data Produk JSON untuk Interaktivitas Kasir -->
<script>
    const masterProduk = <?= json_encode($products) ?>;

    function formatRupiahJs(angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    }

    function tambahBarisProduk() {
        const tbody = document.getElementById('cartBody');
        const rowIndex = tbody.children.length;

        let optionsHtml = '<option value="">-- Pilih Produk --</option>';
        masterProduk.forEach(p => {
            optionsHtml += `<option value="${p.product_id}" data-harga="${p.harga_jual}" data-stok="${p.stok}" data-satuan="${p.satuan}">
                ${p.nama_produk} (Stok: ${Number(p.stok).toFixed(0)} ${p.satuan}) - ${formatRupiahJs(p.harga_jual)}
            </option>`;
        });

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/70 transition-colors py-2';
        tr.innerHTML = `
            <td class="py-2.5 pr-2">
                <select name="product_id[]" required onchange="onProdukSelected(this)" 
                        class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-800 text-slate-800">
                    ${optionsHtml}
                </select>
                <div class="stok-info text-[11px] text-slate-400 mt-0.5"></div>
            </td>
            <td class="py-2.5 px-2 text-right">
                <input type="number" step="0.01" name="harga_satuan[]" value="0" readonly 
                       class="harga-input w-full text-right px-2 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700">
            </td>
            <td class="py-2.5 px-2 text-center">
                <input type="number" step="1" min="1" name="jumlah_beli[]" value="1" required oninput="onQtyChanged(this)"
                       class="qty-input w-full text-center px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-800">
            </td>
            <td class="py-2.5 px-2 text-right">
                <input type="number" step="100" min="0" name="diskon_item[]" value="0" oninput="onQtyChanged(this)"
                       class="disc-input w-full text-right px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-800">
            </td>
            <td class="py-2.5 px-2 text-right font-bold text-slate-900 text-xs">
                <span class="subtotal-item-label">Rp 0</span>
            </td>
            <td class="py-2.5 pl-2 text-center">
                <button type="button" onclick="hapusBaris(this)" class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors">
                    <i data-lucide="trash" class="w-3.5 h-3.5"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        lucide.createIcons();
    }

    function onProdukSelected(selectEl) {
        const tr = selectEl.closest('tr');
        const selectedOpt = selectEl.selectedOptions[0];
        const harga = parseFloat(selectedOpt.getAttribute('data-harga')) || 0;
        const stok = parseFloat(selectedOpt.getAttribute('data-stok')) || 0;
        const satuan = selectedOpt.getAttribute('data-satuan') || '';

        tr.querySelector('.harga-input').value = harga;
        const qtyInput = tr.querySelector('.qty-input');
        qtyInput.max = stok;
        if (parseFloat(qtyInput.value) > stok) {
            qtyInput.value = stok > 0 ? 1 : 0;
        }

        const stokInfo = tr.querySelector('.stok-info');
        if (stok > 0) {
            stokInfo.innerHTML = `<span class="text-emerald-700 font-medium">Sisa stok: ${stok} ${satuan}</span>`;
        } else if (selectEl.value !== '') {
            stokInfo.innerHTML = `<span class="text-rose-600 font-bold">Stok Habis!</span>`;
        } else {
            stokInfo.innerHTML = '';
        }

        hitungBaris(tr);
    }

    function onQtyChanged(inputEl) {
        const tr = inputEl.closest('tr');
        const qtyInput = tr.querySelector('.qty-input');
        const maxStok = parseFloat(qtyInput.max);
        if (maxStok && parseFloat(qtyInput.value) > maxStok) {
            alert('Jumlah beli melebihi sisa stok produk (' + maxStok + ')');
            qtyInput.value = maxStok;
        }
        hitungBaris(tr);
    }

    function hitungBaris(tr) {
        const harga = parseFloat(tr.querySelector('.harga-input').value) || 0;
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const disc = parseFloat(tr.querySelector('.disc-input').value) || 0;

        let subtotal = (harga * qty) - disc;
        if (subtotal < 0) subtotal = 0;

        tr.querySelector('.subtotal-item-label').innerText = formatRupiahJs(subtotal);
        tr.dataset.subtotal = subtotal;

        hitungTotalKeseluruhan();
    }

    function hapusBaris(btn) {
        const tbody = document.getElementById('cartBody');
        if (tbody.children.length > 1) {
            btn.closest('tr').remove();
            hitungTotalKeseluruhan();
        } else {
            alert('Minimal harus ada satu item dalam keranjang belanja!');
        }
    }

    function hitungTotalKeseluruhan() {
        let totalSub = 0;
        document.querySelectorAll('#cartBody tr').forEach(tr => {
            const sub = parseFloat(tr.dataset.subtotal) || 0;
            totalSub += sub;
        });

        document.getElementById('inputSubtotal').value = totalSub;
        document.getElementById('labelSubtotal').innerText = formatRupiahJs(totalSub);

        const totalDiskon = parseFloat(document.getElementById('inputTotalDiskon').value) || 0;
        const totalPajak = parseFloat(document.getElementById('inputTotalPajak').value) || 0;

        let grand = totalSub - totalDiskon + totalPajak;
        if (grand < 0) grand = 0;

        document.getElementById('inputGrandTotal').value = grand;
        document.getElementById('labelGrandTotal').innerText = formatRupiahJs(grand);

        hitungKembalian();
    }

    function hitungKembalian() {
        const grand = parseFloat(document.getElementById('inputGrandTotal').value) || 0;
        const bayar = parseFloat(document.getElementById('inputTotalBayar').value) || 0;
        const kembali = bayar - grand;

        const labelKembali = document.getElementById('labelKembalian');
        const badgeLunas = document.getElementById('badgeLunas');

        document.getElementById('inputKembalian').value = kembali > 0 ? kembali : 0;

        if (bayar >= grand && grand > 0) {
            labelKembali.innerText = formatRupiahJs(kembali);
            labelKembali.className = 'text-xl font-black text-emerald-700';
            badgeLunas.innerText = 'LUNAS';
            badgeLunas.className = 'text-xs font-extrabold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200';
        } else {
            labelKembali.innerText = kembali < 0 ? '-' + formatRupiahJs(Math.abs(kembali)) : 'Rp 0';
            labelKembali.className = 'text-xl font-bold text-rose-600';
            badgeLunas.innerText = 'Belum Cukup';
            badgeLunas.className = 'text-xs font-extrabold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200';
        }
    }

    function setNominalPas() {
        const grand = parseFloat(document.getElementById('inputGrandTotal').value) || 0;
        document.getElementById('inputTotalBayar').value = grand;
        hitungKembalian();
    }

    function tambahNominal(nominal) {
        const curr = parseFloat(document.getElementById('inputTotalBayar').value) || 0;
        document.getElementById('inputTotalBayar').value = curr + nominal;
        hitungKembalian();
    }

    function clearNominal() {
        document.getElementById('inputTotalBayar').value = '';
        hitungKembalian();
    }

    // Inisialisasi baris pertama saat form dimuat
    window.addEventListener('DOMContentLoaded', () => {
        tambahBarisProduk();
    });
</script>

<?php else: ?>
<!-- ======================================================== -->
<!-- VIEW 2: RIWAYAT TRANSAKSI PENJUALAN                      -->
<!-- ======================================================== -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <form method="GET" action="" class="relative w-full sm:w-80">
            <input type="hidden" name="view" value="history">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari no. faktur, kasir, member..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
        </form>
        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Transaksi: <strong class="text-slate-700"><?= count($salesHistory) ?></strong> faktur
        </span>
    </div>

    <!-- Tabel Riwayat Penjualan -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Nomor Faktur</th>
                    <th class="pb-3 font-semibold">Waktu Transaksi</th>
                    <th class="pb-3 font-semibold">Kasir</th>
                    <th class="pb-3 font-semibold">Pelanggan</th>
                    <th class="pb-3 font-semibold text-center">Jumlah Item</th>
                    <th class="pb-3 font-semibold text-right">Grand Total</th>
                    <th class="pb-3 font-semibold text-center">Status</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($salesHistory)): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i data-lucide="receipt" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada riwayat transaksi penjualan.</p>
                            <p class="text-xs text-slate-400 mt-1">Gunakan tab Kasir Baru (POS) untuk memproses belanja.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salesHistory as $sh): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <span class="font-mono text-xs font-bold text-brand-900 bg-brand-50 px-2.5 py-1 rounded-md border border-brand-200/60">
                                    <?= clean($sh['nomor_faktur']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600">
                                <?= date('d/m/Y H:i', strtotime($sh['tanggal_transaksi'])) ?>
                            </td>
                            <td class="py-3.5 text-xs font-semibold text-slate-800">
                                <?= clean($sh['nama_kasir']) ?>
                            </td>
                            <td class="py-3.5 text-xs text-slate-700">
                                <?= clean($sh['nama_pelanggan']) ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    <?= $sh['total_item'] ?> Item
                                </span>
                            </td>
                            <td class="py-3.5 text-right font-black text-slate-900 text-xs">
                                <?= formatRupiah($sh['grand_total']) ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <?= clean($sh['status_transaksi']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Lihat Struk -->
                                <a href="sales.php?detail=<?= $sh['sale_id'] ?>" 
                                   class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                   title="Lihat & Cetak Struk">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>Struk</span>
                                </a>

                                <!-- Batal / Hapus Transaksi -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan transaksi ini? Stok produk akan dikembalikan otomatis!');">
                                    <input type="hidden" name="action" value="delete_sale">
                                    <input type="hidden" name="sale_id" value="<?= $sh['sale_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Batalkan Transaksi">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        <span>Batal</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL STRUK BELANJA SUPERMARKET (RECEIPT PREVIEW & PRINT) -->
<!-- ======================================================== -->
<?php if ($detailSale): ?>
<div id="modalStruk" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 my-8">
        <!-- Header Modal Preview (No Print) -->
        <div class="no-print flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-sm font-bold text-slate-800">Pratinjau Struk Kasir</h3>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition-all">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    <span>Cetak Struk</span>
                </button>
                <a href="sales.php" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- AREA KERTAS STRUK BELANJA THERMAL -->
        <div id="printableReceipt" class="p-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-300 font-mono text-xs text-slate-800">
            <!-- Kop Toko -->
            <div class="text-center pb-3 border-b border-dashed border-slate-300">
                <h2 class="text-base font-extrabold tracking-wider">SUPERMARKET INDONESIA</h2>
                <p class="text-[11px] text-slate-500">Jl. Jend. Sudirman Kav. 45 - Jakarta</p>
                <p class="text-[11px] text-slate-500">Telp: (021) 555-8901 &bull; NPWP: 01.234.567.8-012.000</p>
            </div>

            <!-- Meta Transaksi -->
            <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>Faktur : <?= clean($detailSale['nomor_faktur']) ?></span>
                    <span><?= date('d/m/y H:i', strtotime($detailSale['tanggal_transaksi'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Kasir  : <?= clean($detailSale['nama_kasir']) ?></span>
                    <span>Status : <?= clean($detailSale['status_transaksi']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Member : <?= clean($detailSale['nama_pelanggan'] ?: 'Umum / Non-Member') ?></span>
                    <span><?= clean($detailSale['membership_no'] ?: '') ?></span>
                </div>
            </div>

            <!-- Daftar Item Belanja -->
            <div class="py-3 border-b border-dashed border-slate-300 space-y-2">
                <?php foreach ($detailItems as $it): ?>
                    <div>
                        <div class="font-bold"><?= clean($it['nama_produk']) ?></div>
                        <div class="flex justify-between text-[11px] text-slate-600">
                            <span><?= formatAngka($it['jumlah_beli'], 0) ?> <?= clean($it['satuan']) ?> x <?= formatRupiah($it['harga_satuan']) ?></span>
                            <?php if ($it['diskon_item'] > 0): ?>
                                <span class="text-rose-600">Disc: -<?= formatRupiah($it['diskon_item']) ?></span>
                            <?php endif; ?>
                            <span class="font-bold text-slate-900"><?= formatRupiah($it['subtotal_item']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Rincian Total -->
            <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span><?= formatRupiah($detailSale['subtotal']) ?></span>
                </div>
                <?php if ($detailSale['total_diskon'] > 0): ?>
                    <div class="flex justify-between text-rose-600">
                        <span>Diskon Belanja</span>
                        <span>-<?= formatRupiah($detailSale['total_diskon']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($detailSale['total_pajak'] > 0): ?>
                    <div class="flex justify-between">
                        <span>Pajak (PPN)</span>
                        <span><?= formatRupiah($detailSale['total_pajak']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between font-black text-sm pt-1 border-t border-slate-200">
                    <span>GRAND TOTAL</span>
                    <span><?= formatRupiah($detailSale['grand_total']) ?></span>
                </div>
                <div class="flex justify-between pt-1">
                    <span>TUNAI / BAYAR</span>
                    <span><?= formatRupiah($detailSale['total_bayar']) ?></span>
                </div>
                <div class="flex justify-between font-bold">
                    <span>KEMBALIAN</span>
                    <span><?= formatRupiah($detailSale['kembalian']) ?></span>
                </div>
            </div>

            <!-- Footer Struk -->
            <div class="text-center pt-3 text-[10px] text-slate-500 space-y-1">
                <p>=== TERIMA KASIH ATAS KUNJUNGAN ANDA ===</p>
                <p>Barang yang sudah dibeli dapat ditukar 1x24 jam dengan membawa struk asli.</p>
                <p class="font-sans font-bold text-slate-400">--- www.supermarket.com ---</p>
            </div>
        </div>

        <!-- Tombol Aksi Bawah (No Print) -->
        <div class="no-print mt-4 flex items-center justify-end gap-2">
            <a href="sales.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-colors">
                Tutup
            </a>
            <button onclick="window.print()" class="px-5 py-2 bg-brand-900 hover:bg-brand-800 text-white rounded-xl text-xs font-bold shadow transition-all flex items-center gap-1.5">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Cetak Struk Sekarang</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
