<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Kelola Produk Supermarket';

// PROSES POST (TAMBAH / UPDATE / HAPUS)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH PRODUK
    if ($action === 'create') {
        $sku          = trim($_POST['sku'] ?? '');
        $barcode      = trim($_POST['barcode'] ?? '');
        $nama_produk  = trim($_POST['nama_produk'] ?? '');
        $category_id  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $harga_beli   = (float)($_POST['harga_beli'] ?? 0);
        $harga_jual   = (float)($_POST['harga_jual'] ?? 0);
        $harga_member = !empty($_POST['harga_member']) ? (float)$_POST['harga_member'] : $harga_jual;
        $stok         = (float)($_POST['stok'] ?? 0);
        $satuan       = trim($_POST['satuan'] ?? 'Pcs');

        if (empty($nama_produk) || empty($sku) || $harga_jual <= 0) {
            setFlash('error', 'Nama produk, SKU, dan harga jual wajib diisi secara valid!');
        } else {
            // Cek keunikan SKU
            $chk = $pdo->prepare("SELECT product_id FROM products WHERE sku = ?");
            $chk->execute([$sku]);
            if ($chk->fetch()) {
                setFlash('error', "SKU '{$sku}' sudah digunakan produk lain!");
            } else {
                // Cek barcode jika diisi
                if (!empty($barcode)) {
                    $chkBc = $pdo->prepare("SELECT product_id FROM products WHERE barcode = ?");
                    $chkBc->execute([$barcode]);
                    if ($chkBc->fetch()) {
                        setFlash('error', "Barcode '{$barcode}' sudah digunakan produk lain!");
                        header("Location: products.php");
                        exit;
                    }
                }

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (sku, barcode, nama_produk, category_id, harga_beli, harga_jual, harga_member, stok, satuan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$sku, $barcode ?: null, $nama_produk, $category_id, $harga_beli, $harga_jual, $harga_member, $stok, $satuan]);
                    setFlash('success', "Produk '{$nama_produk}' berhasil ditambahkan ke inventori!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal menambah produk: ' . $e->getMessage());
                }
            }
        }
        header("Location: products.php");
        exit;
    }

    // UPDATE PRODUK
    if ($action === 'update') {
        $product_id   = (int)($_POST['product_id'] ?? 0);
        $sku          = trim($_POST['sku'] ?? '');
        $barcode      = trim($_POST['barcode'] ?? '');
        $nama_produk  = trim($_POST['nama_produk'] ?? '');
        $category_id  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $harga_beli   = (float)($_POST['harga_beli'] ?? 0);
        $harga_jual   = (float)($_POST['harga_jual'] ?? 0);
        $harga_member = !empty($_POST['harga_member']) ? (float)$_POST['harga_member'] : $harga_jual;
        $stok         = (float)($_POST['stok'] ?? 0);
        $satuan       = trim($_POST['satuan'] ?? 'Pcs');

        if (empty($nama_produk) || empty($sku) || empty($product_id) || $harga_jual <= 0) {
            setFlash('error', 'Data input produk tidak lengkap atau tidak valid!');
        } else {
            // Cek duplikasi SKU
            $chk = $pdo->prepare("SELECT product_id FROM products WHERE sku = ? AND product_id != ?");
            $chk->execute([$sku, $product_id]);
            if ($chk->fetch()) {
                setFlash('error', "SKU '{$sku}' sudah digunakan produk lain!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET sku = ?, barcode = ?, nama_produk = ?, category_id = ?, harga_beli = ?, harga_jual = ?, harga_member = ?, stok = ?, satuan = ?
                        WHERE product_id = ?
                    ");
                    $stmt->execute([$sku, $barcode ?: null, $nama_produk, $category_id, $harga_beli, $harga_jual, $harga_member, $stok, $satuan, $product_id]);
                    setFlash('success', "Produk '{$nama_produk}' berhasil diperbarui!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui produk: ' . $e->getMessage());
                }
            }
        }
        header("Location: products.php");
        exit;
    }

    // HAPUS PRODUK
    if ($action === 'delete') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                setFlash('success', 'Produk berhasil dihapus dari inventori!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menghapus produk. Mungkin produk ini sudah terkait dengan transaksi penjualan.');
            }
        }
        header("Location: products.php");
        exit;
    }
}

// PENCARIAN & FILTER
$search      = trim($_GET['search'] ?? '');
$filterCat   = !empty($_GET['category']) ? (int)$_GET['category'] : '';
$filterStock = trim($_GET['filter'] ?? '');

$sql = "
    SELECT p.*, c.nama_kategori 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nama_produk LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($filterCat)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $filterCat;
}

if ($filterStock === 'low_stock') {
    $sql .= " AND p.stok <= 10";
}

$sql .= " ORDER BY p.product_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Opsi Kategori untuk Filter & Modal
$categories = $pdo->query("SELECT category_id, nama_kategori FROM categories ORDER BY nama_kategori ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Katalog Produk Supermarket</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola data barang dagangan, SKU, barcode scanner, margin harga, dan sisa stok.</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Tambah Produk</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar & Pencarian -->
    <form method="GET" action="" class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6">
        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto flex-1">
            <!-- Search Box -->
            <div class="relative flex-1 min-w-[240px]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama, SKU, barcode..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
            </div>

            <!-- Filter Kategori -->
            <select name="category" onchange="this.form.submit()" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white">
                <option value="">-- Semua Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= ($filterCat == $cat['category_id']) ? 'selected' : '' ?>>
                        <?= clean($cat['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Quick Filter: Low Stock -->
            <a href="products.php<?= ($filterStock === 'low_stock') ? '' : '?filter=low_stock' ?>" 
               class="px-3 py-2 rounded-xl text-xs font-bold border transition-colors flex items-center gap-1.5 <?= ($filterStock === 'low_stock') ? 'bg-amber-500 text-white border-amber-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-amber-50 hover:text-amber-700' ?>">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                <span>Stok Rendah (&le;10)</span>
            </a>

            <?php if (!empty($search) || !empty($filterCat) || !empty($filterStock)): ?>
                <a href="products.php" class="text-xs text-rose-600 hover:underline font-semibold">Reset Filter</a>
            <?php endif; ?>
        </div>

        <span class="text-xs text-slate-400 self-end md:self-center font-medium">
            Total Produk: <strong class="text-slate-800"><?= count($products) ?></strong> item
        </span>
    </form>

    <!-- Tabel Data Produk -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Informasi Produk</th>
                    <th class="pb-3 font-semibold">Kategori</th>
                    <th class="pb-3 font-semibold text-right">Harga Beli</th>
                    <th class="pb-3 font-semibold text-right">Harga Jual</th>
                    <th class="pb-3 font-semibold text-right">Harga Member</th>
                    <th class="pb-3 font-semibold text-center">Stok & Satuan</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i data-lucide="package-x" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada produk ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau tambahkan produk baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $prd): ?>
                        <?php 
                            $isLow = $prd['stok'] <= 10;
                            $isCritical = $prd['stok'] <= 5;
                            $margin = $prd['harga_jual'] - $prd['harga_beli'];
                        ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <p class="font-bold text-slate-900 text-sm leading-snug"><?= clean($prd['nama_produk']) ?></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="font-mono text-[11px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium">SKU: <?= clean($prd['sku']) ?></span>
                                    <?php if (!empty($prd['barcode'])): ?>
                                        <span class="font-mono text-[11px] bg-emerald-50 text-emerald-800 px-1.5 py-0.5 rounded font-medium flex items-center gap-1">
                                            <i data-lucide="scan-barcode" class="w-3 h-3 text-emerald-700"></i>
                                            <?= clean($prd['barcode']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600 font-medium">
                                <?= clean($prd['nama_kategori'] ?? 'Tanpa Kategori') ?>
                            </td>
                            <td class="py-3.5 text-right text-xs text-slate-500">
                                <?= formatRupiah($prd['harga_beli']) ?>
                            </td>
                            <td class="py-3.5 text-right font-bold text-slate-900 text-xs">
                                <div><?= formatRupiah($prd['harga_jual']) ?></div>
                                <span class="text-[10px] text-emerald-700 font-semibold">+<?= formatRupiah($margin) ?></span>
                            </td>
                            <td class="py-3.5 text-right text-xs font-semibold text-brand-900">
                                <?= formatRupiah($prd['harga_member'] ?? $prd['harga_jual']) ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <?php if ($isCritical): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                                        <i data-lucide="alert-octagon" class="w-3 h-3"></i>
                                        <?= formatAngka($prd['stok'], 0) ?> <?= clean($prd['satuan']) ?>
                                    </span>
                                <?php elseif ($isLow): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                                        <?= formatAngka($prd['stok'], 0) ?> <?= clean($prd['satuan']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                                        <?= formatAngka($prd['stok'], 0) ?> <?= clean($prd['satuan']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($prd) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah Produk">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= $prd['product_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus Produk">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        <span>Hapus</span>
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

<!-- MODAL FORM TAMBAH / EDIT PRODUK -->
<div id="modalProduk" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Tambah Produk Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Lengkapi spesifikasi produk, barcode, harga, dan stok</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="product_id" id="productId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SKU <span class="text-rose-500">*</span></label>
                    <input type="text" name="sku" id="sku" required placeholder="Contoh: SKU-MYS-01"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white uppercase text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Barcode Scanner</label>
                    <input type="text" name="barcode" id="barcode" placeholder="Contoh: 899999900001"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nama Produk <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_produk" id="namaProduk" required placeholder="Contoh: Minyak Goreng Bimoli 2L"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kategori</label>
                    <select name="category_id" id="categoryId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= clean($cat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Satuan Kemasan <span class="text-rose-500">*</span></label>
                    <input type="text" name="satuan" id="satuan" required placeholder="Pcs, Kg, Sak, Kotak, Btl..."
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Harga Beli (Modal)</label>
                    <input type="number" step="0.01" name="harga_beli" id="hargaBeli" required placeholder="0"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Harga Jual Normal <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" name="harga_jual" id="hargaJual" required placeholder="0"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Harga Khusus Member</label>
                    <input type="number" step="0.01" name="harga_member" id="hargaMember" placeholder="Sama dg jual"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Jumlah Stok Awal / Saat Ini</label>
                <input type="number" step="0.001" name="stok" id="stok" required placeholder="0"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Produk Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('productId').value = '';
        document.getElementById('sku').value = '';
        document.getElementById('barcode').value = '';
        document.getElementById('namaProduk').value = '';
        document.getElementById('categoryId').value = '';
        document.getElementById('hargaBeli').value = '';
        document.getElementById('hargaJual').value = '';
        document.getElementById('hargaMember').value = '';
        document.getElementById('stok').value = '0';
        document.getElementById('satuan').value = 'Pcs';
        document.getElementById('modalProduk').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Produk Supermarket';
        document.getElementById('formAction').value = 'update';
        document.getElementById('productId').value = data.product_id;
        document.getElementById('sku').value = data.sku || '';
        document.getElementById('barcode').value = data.barcode || '';
        document.getElementById('namaProduk').value = data.nama_produk || '';
        document.getElementById('categoryId').value = data.category_id || '';
        document.getElementById('hargaBeli').value = data.harga_beli || 0;
        document.getElementById('hargaJual').value = data.harga_jual || 0;
        document.getElementById('hargaMember').value = data.harga_member || '';
        document.getElementById('stok').value = data.stok || 0;
        document.getElementById('satuan').value = data.satuan || 'Pcs';
        document.getElementById('modalProduk').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalProduk').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
