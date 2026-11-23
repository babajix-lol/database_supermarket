<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Kelola Kategori';

// PROSES POST FORM (TAMBAH / EDIT / HAPUS)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH KATEGORI
    if ($action === 'create') {
        $kode_kategori = strtoupper(trim($_POST['kode_kategori'] ?? ''));
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $parent_id     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (empty($nama_kategori)) {
            setFlash('error', 'Nama kategori wajib diisi!');
        } else {
            // Cek kode unik jika diisi
            if (!empty($kode_kategori)) {
                $chk = $pdo->prepare("SELECT category_id FROM categories WHERE kode_kategori = ?");
                $chk->execute([$kode_kategori]);
                if ($chk->fetch()) {
                    setFlash('error', "Kode kategori '{$kode_kategori}' sudah digunakan!");
                    header("Location: categories.php");
                    exit;
                }
            } else {
                $kode_kategori = 'CAT-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nama_kategori), 0, 3)) . rand(10, 99);
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO categories (parent_id, nama_kategori, kode_kategori) VALUES (?, ?, ?)");
                $stmt->execute([$parent_id, $nama_kategori, $kode_kategori]);
                setFlash('success', 'Kategori baru berhasil ditambahkan!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menambah kategori: ' . $e->getMessage());
            }
        }
        header("Location: categories.php");
        exit;
    }

    // UPDATE KATEGORI
    if ($action === 'update') {
        $category_id   = (int)($_POST['category_id'] ?? 0);
        $kode_kategori = strtoupper(trim($_POST['kode_kategori'] ?? ''));
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $parent_id     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($parent_id === $category_id) {
            $parent_id = null; // Tidak boleh jadi parent diri sendiri
        }

        if (empty($nama_kategori) || empty($category_id)) {
            setFlash('error', 'Data tidak lengkap untuk pembaruan!');
        } else {
            // Cek duplikasi kode selain diri sendiri
            $chk = $pdo->prepare("SELECT category_id FROM categories WHERE kode_kategori = ? AND category_id != ?");
            $chk->execute([$kode_kategori, $category_id]);
            if ($chk->fetch()) {
                setFlash('error', "Kode kategori '{$kode_kategori}' sudah digunakan kategori lain!");
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET parent_id = ?, nama_kategori = ?, kode_kategori = ? WHERE category_id = ?");
                    $stmt->execute([$parent_id, $nama_kategori, $kode_kategori, $category_id]);
                    setFlash('success', 'Kategori berhasil diperbarui!');
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui kategori: ' . $e->getMessage());
                }
            }
        }
        header("Location: categories.php");
        exit;
    }

    // HAPUS KATEGORI
    if ($action === 'delete') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            try {
                // Relasi di database akan ON DELETE SET NULL untuk produk dan subkategori
                $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                setFlash('success', 'Kategori berhasil dihapus!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menghapus kategori: ' . $e->getMessage());
            }
        }
        header("Location: categories.php");
        exit;
    }
}

// PENCARIAN & DATA LIST
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nama_kategori AS parent_name,
               (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.category_id) AS total_produk
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.category_id
        WHERE c.nama_kategori LIKE ? OR c.kode_kategori LIKE ?
        ORDER BY c.category_id DESC
    ");
    $stmt->execute(["%{$search}%", "%{$search}%"]);
} else {
    $stmt = $pdo->query("
        SELECT c.*, p.nama_kategori AS parent_name,
               (SELECT COUNT(*) FROM products pr WHERE pr.category_id = c.category_id) AS total_produk
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.category_id
        ORDER BY c.category_id DESC
    ");
}
$categories = $stmt->fetchAll();

// Ambil semua parent kategori untuk opsi dropdown
$stmtParents = $pdo->query("SELECT category_id, nama_kategori FROM categories ORDER BY nama_kategori ASC");
$allCategories = $stmtParents->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Kategori Produk</h1>
        <p class="text-sm text-slate-500 mt-1">Klasifikasikan produk supermarket ke dalam kategori dan subkategori yang rapi.</p>
    </div>
    <div>
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Tambah Kategori</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter & Pencarian -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <form method="GET" action="" class="relative w-full sm:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari kategori atau kode..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
        </form>
        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Kategori: <strong class="text-slate-700"><?= count($categories) ?></strong>
        </span>
    </div>

    <!-- Tabel Data Kategori -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Kode Kategori</th>
                    <th class="pb-3 font-semibold">Nama Kategori</th>
                    <th class="pb-3 font-semibold">Induk (Parent)</th>
                    <th class="pb-3 font-semibold text-center">Jumlah Produk</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400">
                            <i data-lucide="folder-x" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada kategori ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Klik "+ Tambah Kategori" untuk menambahkan kategori baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <span class="font-mono text-xs font-bold text-brand-900 bg-brand-50 px-2.5 py-1 rounded-md border border-brand-200/60">
                                    <?= clean($cat['kode_kategori'] ?: '-') ?>
                                </span>
                            </td>
                            <td class="py-3.5">
                                <span class="font-bold text-slate-800 text-sm"><?= clean($cat['nama_kategori']) ?></span>
                            </td>
                            <td class="py-3.5 text-xs text-slate-500">
                                <?php if (!empty($cat['parent_name'])): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-medium">
                                        <i data-lucide="corner-down-right" class="w-3 h-3 text-slate-400"></i>
                                        <?= clean($cat['parent_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic font-normal">Kategori Utama</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    <?= $cat['total_produk'] ?> Produk
                                </span>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($cat) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah Kategori">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini? Produk yang terkait akan dialihkan ke tanpa kategori.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus Kategori">
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

<!-- MODAL FORM TAMBAH / EDIT KATEGORI -->
<div id="modalKategori" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Tambah Kategori Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Lengkapi data kategori produk supermarket</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="category_id" id="categoryId" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kode Kategori</label>
                <input type="text" name="kode_kategori" id="kodeKategori" placeholder="Contoh: CAT-FNB"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white uppercase text-slate-800">
                <p class="text-[11px] text-slate-400 mt-1">Kosongkan untuk otomatis generate kode kategori unik.</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nama Kategori <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_kategori" id="namaKategori" required placeholder="Contoh: Sayuran & Buah Segar"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Kategori Induk (Parent - Opsional)</label>
                <select name="parent_id" id="parentId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                    <option value="">-- Tanpa Induk (Kategori Utama) --</option>
                    <?php foreach ($allCategories as $pc): ?>
                        <option value="<?= $pc['category_id'] ?>"><?= clean($pc['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Pilih induk jika kategori ini merupakan subkategori.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Kategori Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('categoryId').value = '';
        document.getElementById('kodeKategori').value = '';
        document.getElementById('namaKategori').value = '';
        document.getElementById('parentId').value = '';
        document.getElementById('modalKategori').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Kategori';
        document.getElementById('formAction').value = 'update';
        document.getElementById('categoryId').value = data.category_id;
        document.getElementById('kodeKategori').value = data.kode_kategori || '';
        document.getElementById('namaKategori').value = data.nama_kategori || '';
        document.getElementById('parentId').value = data.parent_id || '';
        document.getElementById('modalKategori').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalKategori').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
