<?php
require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Kelola Karyawan & Kasir';

// PROSES POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH KARYAWAN
    if ($action === 'create') {
        $role_id       = (int)($_POST['role_id'] ?? 2);
        $nama_lengkap  = trim($_POST['nama_lengkap'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $password      = trim($_POST['password'] ?? '');
        $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
        $status_aktif  = isset($_POST['status_aktif']) ? 1 : 0;

        if (empty($nama_lengkap) || empty($email) || empty($password)) {
            setFlash('error', 'Nama lengkap, email, dan password wajib diisi!');
        } else {
            // Cek keunikan email
            $chk = $pdo->prepare("SELECT employee_id FROM employees WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                setFlash('error', "Email '{$email}' sudah terdaftar pada sistem!");
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (role_id, nama_lengkap, email, password, nomor_telepon, status_aktif)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$role_id, $nama_lengkap, $email, $hash, $nomor_telepon, $status_aktif]);
                    setFlash('success', "Akun karyawan '{$nama_lengkap}' berhasil dibuat!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal membuat karyawan: ' . $e->getMessage());
                }
            }
        }
        header("Location: employees.php");
        exit;
    }

    // UPDATE KARYAWAN
    if ($action === 'update') {
        $employee_id   = (int)($_POST['employee_id'] ?? 0);
        $role_id       = (int)($_POST['role_id'] ?? 2);
        $nama_lengkap  = trim($_POST['nama_lengkap'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $password      = trim($_POST['password'] ?? '');
        $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
        $status_aktif  = isset($_POST['status_aktif']) ? 1 : 0;

        if (empty($nama_lengkap) || empty($email) || empty($employee_id)) {
            setFlash('error', 'Data input karyawan tidak valid!');
        } else {
            // Cek duplikasi email
            $chk = $pdo->prepare("SELECT employee_id FROM employees WHERE email = ? AND employee_id != ?");
            $chk->execute([$email, $employee_id]);
            if ($chk->fetch()) {
                setFlash('error', "Email '{$email}' sudah digunakan karyawan lain!");
            } else {
                try {
                    if (!empty($password)) {
                        // Update beserta password baru
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE employees 
                            SET role_id = ?, nama_lengkap = ?, email = ?, password = ?, nomor_telepon = ?, status_aktif = ?
                            WHERE employee_id = ?
                        ");
                        $stmt->execute([$role_id, $nama_lengkap, $email, $hash, $nomor_telepon, $status_aktif, $employee_id]);
                    } else {
                        // Update tanpa ubah password
                        $stmt = $pdo->prepare("
                            UPDATE employees 
                            SET role_id = ?, nama_lengkap = ?, email = ?, nomor_telepon = ?, status_aktif = ?
                            WHERE employee_id = ?
                        ");
                        $stmt->execute([$role_id, $nama_lengkap, $email, $nomor_telepon, $status_aktif, $employee_id]);
                    }
                    setFlash('success', "Data karyawan '{$nama_lengkap}' berhasil diperbarui!");
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal memperbarui karyawan: ' . $e->getMessage());
                }
            }
        }
        header("Location: employees.php");
        exit;
    }

    // HAPUS KARYAWAN
    if ($action === 'delete') {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        if ($employee_id > 0) {
            // Cek jika akun memiliki transaksi penjualan
            $chkSales = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE employee_id = ?");
            $chkSales->execute([$employee_id]);
            if ($chkSales->fetchColumn() > 0) {
                setFlash('error', 'Karyawan ini tidak dapat dihapus karena sudah memiliki riwayat transaksi penjualan. Anda dapat menonaktifkan akunnya.');
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
                    $stmt->execute([$employee_id]);
                    setFlash('success', 'Akun karyawan berhasil dihapus!');
                } catch (PDOException $e) {
                    setFlash('error', 'Gagal menghapus karyawan: ' . $e->getMessage());
                }
            }
        }
        header("Location: employees.php");
        exit;
    }
}

// PENCARIAN & DATA
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM sales s WHERE s.employee_id = e.employee_id) AS total_transaksi
        FROM employees e
        WHERE e.nama_lengkap LIKE ? OR e.email LIKE ? OR e.nomor_telepon LIKE ?
        ORDER BY e.employee_id ASC
    ");
    $stmt->execute(["%{$search}%", "%{$search}%", "%{$search}%"]);
} else {
    $stmt = $pdo->query("
        SELECT e.*,
               (SELECT COUNT(*) FROM sales s WHERE s.employee_id = e.employee_id) AS total_transaksi
        FROM employees e
        ORDER BY e.employee_id ASC
    ");
}
$employees = $stmt->fetchAll();

// Helper Role Name
function getRoleLabel($role_id) {
    switch ($role_id) {
        case 1:
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-brand-900 border border-emerald-200"><i data-lucide="shield" class="w-3 h-3 text-emerald-600"></i> Administrator</span>';
        case 2:
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200"><i data-lucide="shopping-bag" class="w-3 h-3 text-sky-600"></i> Kasir POS</span>';
        case 3:
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-50 text-purple-800 border border-purple-200"><i data-lucide="package" class="w-3 h-3 text-purple-600"></i> Staff Gudang</span>';
        default:
            return '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Staff Lainnya</span>';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Karyawan & Kasir</h1>
        <p class="text-sm text-slate-500 mt-1">Kelola data petugas kasir, admin supermarket, hak akses, dan status operasional.</p>
    </div>
    <div>
        <button onclick="openModalTambah()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold rounded-2xl shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="user-plus" class="w-4 h-4 text-emerald-300"></i>
            <span>+ Tambah Karyawan</span>
        </button>
    </div>
</div>

<!-- Kartu Konten Tabel Utama (Donezo Style) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <form method="GET" action="" class="relative w-full sm:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="search" value="<?= clean($search) ?>" placeholder="Cari nama, email, nomor HP..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800 placeholder:text-slate-400 transition-all">
        </form>
        <span class="text-xs text-slate-400 self-end sm:self-center font-medium">
            Total Karyawan: <strong class="text-slate-700"><?= count($employees) ?></strong> orang
        </span>
    </div>

    <!-- Tabel Data Karyawan -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-3">
                    <th class="pb-3 font-semibold">Nama Karyawan</th>
                    <th class="pb-3 font-semibold">Hak Akses (Role)</th>
                    <th class="pb-3 font-semibold">Kontak (HP & Email)</th>
                    <th class="pb-3 font-semibold text-center">Status</th>
                    <th class="pb-3 font-semibold text-center">Total Transaksi Kasir</th>
                    <th class="pb-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">
                            <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                            <p class="font-medium text-sm text-slate-600">Tidak ada data karyawan ditemukan.</p>
                            <p class="text-xs text-slate-400 mt-1">Klik "+ Tambah Karyawan" untuk menambahkan akun baru.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 text-brand-900 font-extrabold flex items-center justify-center text-xs border border-slate-200">
                                        <?= strtoupper(substr($emp['nama_lengkap'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 text-sm"><?= clean($emp['nama_lengkap']) ?></p>
                                        <p class="text-[11px] text-slate-400">ID: EMP-<?= str_pad($emp['employee_id'], 3, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5">
                                <?= getRoleLabel($emp['role_id']) ?>
                            </td>
                            <td class="py-3.5 text-xs text-slate-600">
                                <p class="font-semibold text-slate-800"><?= clean($emp['email']) ?></p>
                                <p class="text-slate-400 text-[11px]"><?= clean($emp['nomor_telepon'] ?: '-') ?></p>
                            </td>
                            <td class="py-3.5 text-center">
                                <?php if ($emp['status_aktif']): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 text-center">
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                    <?= $emp['total_transaksi'] ?> Transaksi
                                </span>
                            </td>
                            <td class="py-3.5 text-right space-x-1">
                                <!-- Tombol Edit -->
                                <button onclick='openModalEdit(<?= json_encode($emp) ?>)' 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-emerald-50 hover:text-brand-900 rounded-lg transition-colors"
                                        title="Ubah Karyawan">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    <span>Edit</span>
                                </button>
                                <!-- Form Hapus -->
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun karyawan ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="employee_id" value="<?= $emp['employee_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors" title="Hapus Karyawan">
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

<!-- MODAL FORM TAMBAH / EDIT KARYAWAN -->
<div id="modalKaryawan" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Tambah Karyawan Baru</h3>
                <p class="text-xs text-slate-400 mt-0.5">Kelola akun dan otorisasi sistem kasir</p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="" class="mt-4 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="employee_id" id="employeeId" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_lengkap" id="namaLengkap" required placeholder="Nama lengkap petugas"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Hak Akses (Role) <span class="text-rose-500">*</span></label>
                    <select name="role_id" id="roleId" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                        <option value="1">1 - Administrator</option>
                        <option value="2" selected>2 - Kasir POS</option>
                        <option value="3">3 - Staff Gudang</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nomor HP / WhatsApp</label>
                    <input type="text" name="nomor_telepon" id="nomorTelepon" placeholder="08xxxxxxxx"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Alamat Email <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" id="email" required placeholder="email@supermarket.com"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        <span id="labelPassword">Password Akun</span> <span id="passRequired" class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="password" id="password" placeholder="Minimal 6 karakter"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-800 focus:bg-white text-slate-800">
                    <p id="passHelp" class="text-[11px] text-slate-400 mt-1 hidden">Kosongkan jika tidak ingin merubah password.</p>
                </div>
            </div>

            <div class="pt-2">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status_aktif" id="statusAktif" value="1" checked class="w-4 h-4 text-brand-900 border-slate-300 rounded focus:ring-brand-800">
                    <span class="text-xs font-bold text-slate-700">Akun Aktif (Bisa login & melakukan transaksi)</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                    Simpan Karyawan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalTambah() {
        document.getElementById('modalTitle').innerText = 'Tambah Karyawan Baru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('employeeId').value = '';
        document.getElementById('namaLengkap').value = '';
        document.getElementById('roleId').value = '2';
        document.getElementById('email').value = '';
        document.getElementById('nomorTelepon').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passRequired').style.display = 'inline';
        document.getElementById('passHelp').classList.add('hidden');
        document.getElementById('statusAktif').checked = true;
        document.getElementById('modalKaryawan').classList.remove('hidden');
    }

    function openModalEdit(data) {
        document.getElementById('modalTitle').innerText = 'Edit Karyawan';
        document.getElementById('formAction').value = 'update';
        document.getElementById('employeeId').value = data.employee_id;
        document.getElementById('namaLengkap').value = data.nama_lengkap || '';
        document.getElementById('roleId').value = data.role_id || '2';
        document.getElementById('email').value = data.email || '';
        document.getElementById('nomorTelepon').value = data.nomor_telepon || '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('passRequired').style.display = 'none';
        document.getElementById('passHelp').classList.remove('hidden');
        document.getElementById('statusAktif').checked = data.status_aktif == 1;
        document.getElementById('modalKaryawan').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalKaryawan').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
