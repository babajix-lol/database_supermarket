<?php
/**
 * Konfigurasi Database & Helper Global
 * Sistem Manajemen Supermarket
 */

// Mulai session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set zona waktu ke Waktu Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'supermarket_db';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("<div style='padding:20px; font-family:sans-serif; background:#fee2e2; color:#991b1b; border:1px solid #f87171; border-radius:8px; margin:20px;'>
        <h3 style='margin-top:0;'>Gagal Terhubung ke Database MySQL!</h3>
        <p>Pastikan MySQL di XAMPP sudah aktif dan database <strong>{$db}</strong> sudah diimpor.</p>
        <p><small>Error detail: " . htmlspecialchars($e->getMessage()) . "</small></p>
    </div>");
}

/**
 * Format mata uang Rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format angka umum (misal untuk stok)
 */
function formatAngka($angka, $desimal = 0) {
    return number_format((float)$angka, $desimal, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal($tanggal, $dengan_jam = false) {
    if (!$tanggal) return '-';
    $timestamp = strtotime($tanggal);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $timestamp) . ' ' . $bulan[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    if ($dengan_jam) {
        $tgl .= ' ' . date('H:i', $timestamp) . ' WIB';
    }
    return $tgl;
}

/**
 * Sanitasi string input
 */
function clean($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Tampilkan flash message jika ada
 */
function renderFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $bg = 'bg-emerald-50 text-emerald-800 border-emerald-200';
        $icon = 'check-circle';
        if ($flash['type'] === 'error') {
            $bg = 'bg-rose-50 text-rose-800 border-rose-200';
            $icon = 'alert-triangle';
        } elseif ($flash['type'] === 'warning') {
            $bg = 'bg-amber-50 text-amber-800 border-amber-200';
            $icon = 'alert-circle';
        }

        echo "
        <div id='flash-alert' class='flex items-center gap-3 p-4 mb-6 rounded-2xl border {$bg} shadow-sm transition-all duration-300'>
            <i data-lucide='{$icon}' class='w-5 h-5 flex-shrink-0'></i>
            <span class='text-sm font-medium'>{$flash['message']}</span>
            <button onclick=\"document.getElementById('flash-alert').style.display='none'\" class='ml-auto text-gray-400 hover:text-gray-600'>
                <i data-lucide='x' class='w-4 h-4'></i>
            </button>
        </div>";
    }
}
