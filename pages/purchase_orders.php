<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Purchase Order (PO)';

// PROSES POST (TAMBAH / UPDATE / HAPUS)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH PURCHASE ORDER
    if ($action === 'create') {
        $nomor_po         = strtoupper(trim($_POST['nomor_po'] ?? ''));
        $supplier_id      = (int)($_POST['supplier_id'] ?? 0);
        $tanggal_pesan    = !empty($_POST['tanggal_pesan']) ? $_POST['tanggal_pesan'] : date('Y-m-d');
        $total_biaya      = (float)($_POST['total_biaya'] ?? 0);
        $status_pembelian = trim($_POST['status_pembelian'] ?? 'Draft');

        if (empty($supplier_id) || $total_biaya <= 0) {
            setFlash('error', 'Pilih supplier dan isi total biaya pesanan secara valid!');
        } else {
            if (empty($nomor_po)) {
                $nomor_po = 'PO-' . date('Ymd') . '-' . rand(100, 999);
            }

            // Cek keunikan no PO
            $chk = $pdo->prepare("SELECT po_id FROM purchase_orders WHERE nomor_po = ?");
            $chk->execute([$nomor_po]);
            if ($chk->fetch()) {
                setFlash('error', "Nomor PO '{$nomor_po}' sudah terdaftar!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO purchase_orders (nomor_po, supplier_id, tanggal_pesan, total_biaya, status_pembelian)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$nomor_po, $supplier_id, $tanggal_pesan, $total_biaya, $status_pembelian]);
                    setFlash('success', "Purchase Order '{$nomor_po}' berhasil dibuat!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal membuat PO: ' . $e->getMessage());
                }
            }
        }
        header("Location: purchase_orders.php");
        exit;
    }

    // UPDATE PURCHASE ORDER
    if ($action === 'update') {
        $po_id            = (int)($_POST['po_id'] ?? 0);
        $nomor_po         = strtoupper(trim($_POST['nomor_po'] ?? ''));
        $supplier_id      = (int)($_POST['supplier_id'] ?? 0);
        $tanggal_pesan    = !empty($_POST['tanggal_pesan']) ? $_POST['tanggal_pesan'] : date('Y-m-d');
        $total_biaya      = (float)($_POST['total_biaya'] ?? 0);
        $status_pembelian = trim($_POST['status_pembelian'] ?? 'Draft');

        if (empty($supplier_id) || empty($po_id) || $total_biaya <= 0) {
            setFlash('error', 'Data input purchase order tidak lengkap!');
        } else {
            // Cek duplikasi nomor PO
            $chk = $pdo->prepare("SELECT po_id FROM purchase_orders WHERE nomor_po = ? AND po_id != ?");
            $chk->execute([$nomor_po, $po_id]);
            if ($chk->fetch()) {
                setFlash('error', "Nomor PO '{$nomor_po}' sudah digunakan PO lain!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE purchase_orders 
                        SET nomor_po = ?, supplier_id = ?, tanggal_pesan = ?, total_biaya = ?, status_pembelian = ?
                        WHERE po_id = ?
                    ");
                    $stmt->execute([$nomor_po, $supplier_id, $tanggal_pesan, $total_biaya, $status_pembelian, $po_id]);
                    setFlash('success', "Purchase Order '{$nomor_po}' berhasil diperbarui!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui PO: ' . $e->getMessage());
                }
            }
        }
        header("Location: purchase_orders.php");
        exit;
    }

    // HAPUS PURCHASE ORDER
    if ($action === 'delete') {
        $po_id = (int)($_POST['po_id'] ?? 0);
        if ($po_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE po_id = ?");
                $stmt->execute([$po_id]);
                setFlash('success', 'Purchase Order berhasil dihapus!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menghapus PO: ' . $e->getMessage());
            }
        }
        header("Location: purchase_orders.php");
        exit;
    }
}

// PENCARIAN & DATA
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "
    SELECT po.*, s.nama_perusahaan, s.kontak_person, s.nomor_telepon
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (po.nomor_po LIKE ? OR s.nama_perusahaan LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($filterStatus)) {
    $sql .= " AND po.status_pembelian = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY po.po_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Opsi Supplier untuk Form
$suppliers = $pdo->query("SELECT supplier_id, nama_perusahaan FROM suppliers ORDER BY nama_perusahaan ASC")->fetchAll();

// Helper Status Badge
function renderPOStatusBadge($status) {
    switch ($status) {
        case 'Selesai':
            return '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Selesai (Diterima)</span>';
        case 'Diproses':
            return '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-800 border border-sky-200"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Sedang Diproses</span>';
        case 'Dibatalkan':
            return '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-800 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Dibatalkan</span>';
        default:
            return '<span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Draft Pesanan</span>';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Purchase Order (Pengadaan Barang)</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola pesanan restock barang dagangan supermarket ke distributor dan supplier.</p>
    </div>
    <div>
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Buat PO Baru</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar -->
    <form method="GET" action="" class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto flex-1">
            <div class="relative flex-1 min-w-[240px]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari No. PO atau nama supplier..." 
                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
            </div>

            <select name="status" onchange="this.form.submit()" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-800">
                <option value="">-- Semua Status --</option>
                <option value="Draft" <?= ($filterStatus === 'Draft') ? 'selected' : '' ?>>Draft</option>
                <option value="Diproses" <?= ($filterStatus === 'Diproses') ? 'selected' : '' ?>>Diproses</option>
                <option value="Selesai" <?= ($filterStatus === 'Selesai') ? 'selected' : '' ?>>Selesai</option>
                <option value="Dibatalkan" <?= ($filterStatus === 'Dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
            </select>

            <?php if (!empty($search) || !empty($filterStatus)): ?>
                <a href="purchase_orders.php" class="text-xs text-rose-600 hover:underline font-semibold">Reset</a>
            <?php endif; ?>
        </div>

        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Dokumen: <strong class="text-slate-700"><?= count($orders) ?></strong> PO
        </span>
    </form>

    <!-- Tabel Data PO -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Nomor PO</th>
                    <th class="pb-3 font-semibold">Nama Supplier</th>
                    <th class="pb-3 font-semibold">Tanggal Pesan</th>
                    <th class="pb-3 font-semibold text-right">Total Biaya PO</th>
                    <th class="pb-3 font-semibold text-center">Status Pembelian</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <i data-lucide="file-x" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada purchase order ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Klik "+ Buat PO Baru" untuk membuat pesanan ke supplier.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $po): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <span class="font-mono text-xs font-bold text-brand-900 bg-brand-50 px-2.5 py-1 rounded-md border border-brand-200/60">
                                    <?= clean($po['nomor_po']) ?>
                                </span>
                            </td>
                            <td class="py-3.5">
                                <span class="font-bold text-slate-900 text-sm"><?= clean($po['nama_perusahaan']) ?></span>
                                <p class="text-[11px] text-slate-400">PIC: <?= clean($po['kontak_person'] ?: '-') ?> &bull; <?= clean($po['nomor_telepon'] ?: '-') ?></p>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600 font-medium">
                                <?= formatTanggal($po['tanggal_pesan']) ?>
                            </td>
                            <td class="py-3.5 text-right font-black text-slate-900 text-xs">
                                <?= formatRupiah($po['total_biaya']) ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <?= renderPOStatusBadge($po['status_pembelian']) ?>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($po) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah PO">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus PO ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="po_id" value="<?= $po['po_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus PO">
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

<!-- MODAL FORM TAMBAH / EDIT PURCHASE ORDER -->
<div id="modalPO" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Buat Purchase Order Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Form pengadaan stok supermarket dari mitra vendor</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="po_id" id="poId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nomor PO</label>
                    <input type="text" name="nomor_po" id="nomorPo" placeholder="Contoh: PO-20241120-001"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white uppercase text-slate-800">
                    <p class="text-[11px] text-slate-400 mt-1">Kosongkan untuk otomatis generate.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tanggal Pemesanan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_pesan" id="tanggalPesan" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Pilih Supplier Pemasok <span class="text-rose-500">*</span></label>
                <select name="supplier_id" id="supplierId" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                    <option value="">-- Pilih Mitra Supplier --</option>
                    <?php foreach ($suppliers as $sp): ?>
                        <option value="<?= $sp['supplier_id'] ?>"><?= clean($sp['nama_perusahaan']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Total Estimasi Biaya (Rp) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" name="total_biaya" id="totalBiaya" required placeholder="0"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Status Pembelian</label>
                    <select name="status_pembelian" id="statusPembelian" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                        <option value="Draft">Draft</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Selesai">Selesai (Barang Diterima)</option>
                        <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Buat Purchase Order Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('poId').value = '';
        document.getElementById('nomorPo').value = '';
        document.getElementById('supplierId').value = '';
        document.getElementById('tanggalPesan').value = '<?= date('Y-m-d') ?>';
        document.getElementById('totalBiaya').value = '';
        document.getElementById('statusPembelian').value = 'Draft';
        document.getElementById('modalPO').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Purchase Order';
        document.getElementById('formAction').value = 'update';
        document.getElementById('poId').value = data.po_id;
        document.getElementById('nomorPo').value = data.nomor_po || '';
        document.getElementById('supplierId').value = data.supplier_id || '';
        document.getElementById('tanggalPesan').value = data.tanggal_pesan || '<?= date('Y-m-d') ?>';
        document.getElementById('totalBiaya').value = data.total_biaya || 0;
        document.getElementById('statusPembelian').value = data.status_pembelian || 'Draft';
        document.getElementById('modalPO').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalPO').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
