<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Kelola Pelanggan / Member';

// PROSES POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH PELANGGAN
    if ($action === 'create') {
        $membership_no     = strtoupper(trim($_POST['membership_no'] ?? ''));
        $tier_id           = (int)($_POST['tier_id'] ?? 3);
        $nama_lengkap      = trim($_POST['nama_lengkap'] ?? '');
        $nomor_telepon     = trim($_POST['nomor_telepon'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $poin_member       = (int)($_POST['poin_member'] ?? 0);
        $tanggal_bergabung = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : date('Y-m-d');

        if (empty($nama_lengkap)) {
            setFlash('error', 'Nama lengkap pelanggan wajib diisi!');
        } else {
            if (empty($membership_no)) {
                $membership_no = 'MBR-' . date('Y') . '-' . rand(100, 999);
            }

            // Cek keunikan no member
            $chk = $pdo->prepare("SELECT customer_id FROM customers WHERE membership_no = ?");
            $chk->execute([$membership_no]);
            if ($chk->fetch()) {
                setFlash('error', "Nomor Member '{$membership_no}' sudah terdaftar!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (membership_no, tier_id, nama_lengkap, nomor_telepon, email, poin_member, tanggal_bergabung)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$membership_no, $tier_id, $nama_lengkap, $nomor_telepon, $email ?: null, $poin_member, $tanggal_bergabung]);
                    setFlash('success', "Pelanggan '{$nama_lengkap}' berhasil didaftarkan sebagai member!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal mendaftarkan pelanggan: ' . $e->getMessage());
                }
            }
        }
        header("Location: customers.php");
        exit;
    }

    // UPDATE PELANGGAN
    if ($action === 'update') {
        $customer_id       = (int)($_POST['customer_id'] ?? 0);
        $membership_no     = strtoupper(trim($_POST['membership_no'] ?? ''));
        $tier_id           = (int)($_POST['tier_id'] ?? 3);
        $nama_lengkap      = trim($_POST['nama_lengkap'] ?? '');
        $nomor_telepon     = trim($_POST['nomor_telepon'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $poin_member       = (int)($_POST['poin_member'] ?? 0);
        $tanggal_bergabung = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : date('Y-m-d');

        if (empty($nama_lengkap) || empty($customer_id)) {
            setFlash('error', 'Data input pelanggan tidak valid!');
        } else {
            // Cek duplikasi no member
            $chk = $pdo->prepare("SELECT customer_id FROM customers WHERE membership_no = ? AND customer_id != ?");
            $chk->execute([$membership_no, $customer_id]);
            if ($chk->fetch()) {
                setFlash('error', "Nomor Member '{$membership_no}' sudah digunakan pelanggan lain!");
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE customers 
                        SET membership_no = ?, tier_id = ?, nama_lengkap = ?, nomor_telepon = ?, email = ?, poin_member = ?, tanggal_bergabung = ?
                        WHERE customer_id = ?
                    ");
                    $stmt->execute([$membership_no, $tier_id, $nama_lengkap, $nomor_telepon, $email ?: null, $poin_member, $tanggal_bergabung, $customer_id]);
                    setFlash('success', "Data pelanggan '{$nama_lengkap}' berhasil diperbarui!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui pelanggan: ' . $e->getMessage());
                }
            }
        }
        header("Location: customers.php");
        exit;
    }

    // HAPUS PELANGGAN
    if ($action === 'delete') {
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        if ($customer_id > 0) {
            try {
                // Relasi di database ON DELETE SET NULL untuk riwayat transaksi sales
                $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
                $stmt->execute([$customer_id]);
                setFlash('success', 'Data member pelanggan berhasil dihapus!');
            } catch (PDOException $e) {
                setFlash('error', 'Gagal menghapus pelanggan: ' . $e->getMessage());
            }
        }
        header("Location: customers.php");
        exit;
    }
}

// PENCARIAN & DATA
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.customer_id) AS total_transaksi,
               (SELECT COALESCE(SUM(s.grand_total), 0) FROM sales s WHERE s.customer_id = c.customer_id) AS total_belanja
        FROM customers c
        WHERE c.nama_lengkap LIKE ? OR c.membership_no LIKE ? OR c.nomor_telepon LIKE ? OR c.email LIKE ?
        ORDER BY c.customer_id DESC
    ");
    $stmt->execute(["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
} else {
    $stmt = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.customer_id) AS total_transaksi,
               (SELECT COALESCE(SUM(s.grand_total), 0) FROM sales s WHERE s.customer_id = c.customer_id) AS total_belanja
        FROM customers c
        ORDER BY c.customer_id DESC
    ");
}
$customers = $stmt->fetchAll();

// Helper Tier
function renderTierBadge($tier_id) {
    if ($tier_id == 1) {
        return '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200">Tier 1 &bull; Platinum / VIP</span>';
    } elseif ($tier_id == 2) {
        return '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200">Tier 2 &bull; Gold</span>';
    } else {
        return '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">Tier 3 &bull; Silver</span>';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Data Pelanggan & Member</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola program loyalitas pelanggan, kartu membership, tier level, dan perolehan poin belanja.</p>
    </div>
    <div>
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="user-plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Tambah Pelanggan</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <form method="GET" action="" class="relative w-full sm:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama, no member, no telp..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
        </form>
        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Member: <strong class="text-slate-700"><?= count($customers) ?></strong> orang
        </span>
    </div>

    <!-- Tabel Data Pelanggan -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">No. Membership</th>
                    <th class="pb-3 font-semibold">Nama Lengkap</th>
                    <th class="pb-3 font-semibold">Tier Level</th>
                    <th class="pb-3 font-semibold">Kontak (HP & Email)</th>
                    <th class="pb-3 font-semibold text-center">Poin Member</th>
                    <th class="pb-3 font-semibold text-right">Total Belanja</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada data pelanggan ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Klik "+ Tambah Pelanggan" untuk mendaftarkan member baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $cust): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <span class="font-mono text-xs font-bold text-brand-900 bg-brand-50 px-2.5 py-1 rounded-md border border-brand-200/60">
                                    <?= clean($cust['membership_no']) ?>
                                </span>
                            </td>
                            <td class="py-3.5">
                                <span class="font-bold text-slate-900 text-sm"><?= clean($cust['nama_lengkap']) ?></span>
                                <p class="text-[11px] text-slate-400">Bergabung: <?= formatTanggal($cust['tanggal_bergabung']) ?></p>
                            </td>
                            <td class="py-3.5">
                                <?= renderTierBadge($cust['tier_id']) ?>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600">
                                <p class="font-semibold text-slate-800"><?= clean($cust['nomor_telepon'] ?: '-') ?></p>
                                <p class="text-slate-400 text-[11px]"><?= clean($cust['email'] ?: '-') ?></p>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 text-xs font-black px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <i data-lucide="award" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    <?= formatAngka($cust['poin_member']) ?> Pts
                                </span>
                            </td>
                            <td class="py-3.5 text-right font-bold text-slate-900 text-xs">
                                <div><?= formatRupiah($cust['total_belanja']) ?></div>
                                <span class="text-[11px] text-slate-400 font-normal"><?= $cust['total_transaksi'] ?> Transaksi</span>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($cust) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah Pelanggan">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data member ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="customer_id" value="<?= $cust['customer_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus Pelanggan">
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

<!-- MODAL FORM TAMBAH / EDIT PELANGGAN -->
<div id="modalCustomer" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Tambah Member Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Lengkapi profil pelanggan dan nomor keanggotaan</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="customer_id" id="customerId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">No. Membership</label>
                    <input type="text" name="membership_no" id="membershipNo" placeholder="Contoh: MBR-2024-001"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white uppercase text-slate-800">
                    <p class="text-[11px] text-slate-400 mt-1">Kosongkan untuk otomatis generate.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tier Membership</label>
                    <select name="tier_id" id="tierId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                        <option value="1">Tier 1 - Platinum / VIP</option>
                        <option value="2">Tier 2 - Gold</option>
                        <option value="3" selected>Tier 3 - Silver (Standar)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_lengkap" id="namaLengkap" required placeholder="Nama lengkap pelanggan"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nomor Telepon / WhatsApp</label>
                    <input type="text" name="nomor_telepon" id="nomorTelepon" placeholder="08xxxxxxxx"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Alamat Email</label>
                    <input type="email" name="email" id="email" placeholder="email@contoh.com"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Poin Loyalitas Awal</label>
                    <input type="number" name="poin_member" id="poinMember" value="0" min="0"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tanggal Bergabung</label>
                    <input type="date" name="tanggal_bergabung" id="tanggalBergabung" value="<?= date('Y-m-d') ?>"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Member
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Member Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('customerId').value = '';
        document.getElementById('membershipNo').value = '';
        document.getElementById('tierId').value = '3';
        document.getElementById('namaLengkap').value = '';
        document.getElementById('nomorTelepon').value = '';
        document.getElementById('email').value = '';
        document.getElementById('poinMember').value = '0';
        document.getElementById('tanggalBergabung').value = '<?= date('Y-m-d') ?>';
        document.getElementById('modalCustomer').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data Pelanggan';
        document.getElementById('formAction').value = 'update';
        document.getElementById('customerId').value = data.customer_id;
        document.getElementById('membershipNo').value = data.membership_no || '';
        document.getElementById('tierId').value = data.tier_id || '3';
        document.getElementById('namaLengkap').value = data.nama_lengkap || '';
        document.getElementById('nomorTelepon').value = data.nomor_telepon || '';
        document.getElementById('email').value = data.email || '';
        document.getElementById('poinMember').value = data.poin_member || 0;
        document.getElementById('tanggalBergabung').value = data.tanggal_bergabung || '<?= date('Y-m-d') ?>';
        document.getElementById('modalCustomer').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalCustomer').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
