<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Kelola Supplier';

// PROSES POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH SUPPLIER
    if ($action === 'create') {
        $kode_supplier   = strtoupper(trim($_POST['kode_supplier'] ?? ''));
        $nama_perusahaan = trim($_POST['nama_perusahaan'] ?? '');
        $kontak_person   = trim($_POST['kontak_person'] ?? '');
        $nomor_telepon   = trim($_POST['nomor_telepon'] ?? '');
        $alamat          = trim($_POST['alamat'] ?? '');

        if (empty($nama_perusahaan)) {
            setFlash('error', 'Nama perusahaan supplier wajib diisi!');
        } else {
            if (empty($kode_supplier)) {
                $kode_supplier = 'SUP-' . rand(100, 999);
            }

            // Cek keunikan kode
            $chk = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE kode_supplier = ?");
            $chk->execute([$kode_supplier]);
            if ($chk->fetch()) {
                setFlash('error', "Kode supplier '{$kode_supplier}' sudah digunakan!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO suppliers (kode_supplier, nama_perusahaan, kontak_person, nomor_telepon, alamat)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$kode_supplier, $nama_perusahaan, $kontak_person, $nomor_telepon, $alamat]);
                    setFlash('success', "Supplier '{$nama_perusahaan}' berhasil ditambahkan!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal menambah supplier: ' . $e->getMessage());
                }
            }
        }
        header("Location: suppliers.php");
        exit;
    }

    // UPDATE SUPPLIER
    if ($action === 'update') {
        $supplier_id     = (int)($_POST['supplier_id'] ?? 0);
        $kode_supplier   = strtoupper(trim($_POST['kode_supplier'] ?? ''));
        $nama_perusahaan = trim($_POST['nama_perusahaan'] ?? '');
        $kontak_person   = trim($_POST['kontak_person'] ?? '');
        $nomor_telepon   = trim($_POST['nomor_telepon'] ?? '');
        $alamat          = trim($_POST['alamat'] ?? '');

        if (empty($nama_perusahaan) || empty($supplier_id)) {
            setFlash('error', 'Data input supplier tidak valid!');
        } else {
            // Cek duplikasi kode
            $chk = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE kode_supplier = ? AND supplier_id != ?");
            $chk->execute([$kode_supplier, $supplier_id]);
            if ($chk->fetch()) {
                setFlash('error', "Kode supplier '{$kode_supplier}' sudah digunakan supplier lain!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE suppliers 
                        SET kode_supplier = ?, nama_perusahaan = ?, kontak_person = ?, nomor_telepon = ?, alamat = ?
                        WHERE supplier_id = ?
                    ");
                    $stmt->execute([$kode_supplier, $nama_perusahaan, $kontak_person, $nomor_telepon, $alamat, $supplier_id]);
                    setFlash('success', "Data supplier '{$nama_perusahaan}' berhasil diperbarui!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui supplier: ' . $e->getMessage());
                }
            }
        }
        header("Location: suppliers.php");
        exit;
    }

    // HAPUS SUPPLIER
    if ($action === 'delete') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        if ($supplier_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$supplier_id]);
                setFlash('success', 'Supplier berhasil dihapus!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menghapus supplier. Supplier ini mungkin memiliki riwayat Purchase Order.');
            }
        }
        header("Location: suppliers.php");
        exit;
    }
}

// PENCARIAN & DATA
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.supplier_id) AS total_po
        FROM suppliers s
        WHERE s.nama_perusahaan LIKE ? OR s.kode_supplier LIKE ? OR s.kontak_person LIKE ?
        ORDER BY s.supplier_id DESC
    ");
    $stmt->execute(["%{$search}%", "%{$search}%", "%{$search}%"]);
} else {
    $stmt = $pdo->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.supplier_id) AS total_po
        FROM suppliers s
        ORDER BY s.supplier_id DESC
    ");
}
$suppliers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Mitra Supplier & Pemasok</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola data vendor pemasok produk dan bahan pokok supermarket.</p>
    </div>
    <div>
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Tambah Supplier</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <form method="GET" action="" class="relative w-full sm:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama vendor, kode, kontak..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
        </form>
        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Supplier: <strong class="text-slate-700"><?= count($suppliers) ?></strong> vendor
        </span>
    </div>

    <!-- Tabel Data Supplier -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Kode Supplier</th>
                    <th class="pb-3 font-semibold">Nama Perusahaan / Vendor</th>
                    <th class="pb-3 font-semibold">Kontak Person</th>
                    <th class="pb-3 font-semibold">Nomor Telepon</th>
                    <th class="pb-3 font-semibold">Alamat Kantor / Gudang</th>
                    <th class="pb-3 font-semibold text-center">Riwayat PO</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i data-lucide="truck" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada supplier ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Klik "+ Tambah Supplier" untuk menambahkan mitra baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <span class="font-mono text-xs font-bold text-brand-900 bg-brand-50 px-2.5 py-1 rounded-md border border-brand-200/60">
                                    <?= clean($sup['kode_supplier']) ?>
                                </span>
                            </td>
                            <td class="py-3.5">
                                <span class="font-bold text-slate-800 text-sm"><?= clean($sup['nama_perusahaan']) ?></span>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600 font-medium">
                                <?= clean($sup['kontak_person'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 text-xs font-semibold text-slate-700">
                                <?= clean($sup['nomor_telepon'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 text-xs text-slate-500 max-w-xs truncate" title="<?= clean($sup['alamat']) ?>">
                                <?= clean($sup['alamat'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    <?= $sup['total_po'] ?> PO
                                </span>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($sup) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah Supplier">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data supplier ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="supplier_id" value="<?= $sup['supplier_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus Supplier">
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

<!-- MODAL FORM TAMBAH / EDIT SUPPLIER -->
<div id="modalSupplier" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Tambah Supplier Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Lengkapi profil perusahaan dan kontak pemasok</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="supplier_id" id="supplierId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kode Supplier</label>
                    <input type="text" name="kode_supplier" id="kodeSupplier" placeholder="Contoh: SUP-001"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white uppercase text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nama Perusahaan <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_perusahaan" id="namaPerusahaan" required placeholder="PT / CV Nama Vendor"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kontak Person (PIC)</label>
                    <input type="text" name="kontak_person" id="kontakPerson" placeholder="Nama sales / manajer"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nomor Telepon / WA</label>
                    <input type="text" name="nomor_telepon" id="nomorTelepon" placeholder="08xxxxxxxx"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Alamat Kantor / Gudang</label>
                <textarea name="alamat" id="alamat" rows="3" placeholder="Alamat lengkap supplier..."
                          class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Supplier Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('supplierId').value = '';
        document.getElementById('kodeSupplier').value = '';
        document.getElementById('namaPerusahaan').value = '';
        document.getElementById('kontakPerson').value = '';
        document.getElementById('nomorTelepon').value = '';
        document.getElementById('alamat').value = '';
        document.getElementById('modalSupplier').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Supplier';
        document.getElementById('formAction').value = 'update';
        document.getElementById('supplierId').value = data.supplier_id;
        document.getElementById('kodeSupplier').value = data.kode_supplier || '';
        document.getElementById('namaPerusahaan').value = data.nama_perusahaan || '';
        document.getElementById('kontakPerson').value = data.kontak_person || '';
        document.getElementById('nomorTelepon').value = data.nomor_telepon || '';
        document.getElementById('alamat').value = data.alamat || '';
        document.getElementById('modalSupplier').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalSupplier').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
