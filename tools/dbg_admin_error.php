<?php
/**
 * DIAGNOSTIK SEMENTARA — cari penyebab "Terjadi kesalahan sistem. Periksa writable/logs."
 * di halaman admin yang diakses setelah login.
 *
 * Cara pakai (dari docroot hosting):
 *   wget -qO tools/dbg_admin_error.php "https://raw.githubusercontent.com/genggong/vapestore/main/tools/dbg_admin_error.php"
 *   lalu buka:  https://<domain>/tools/dbg_admin_error.php?k=dbg17&p=admin/pos/index.php
 *               https://<domain>/tools/dbg_admin_error.php?k=dbg17&p=admin/customers/edit.php&id=293
 *   (tanpa ?p= akan dicoba halaman POS dulu)
 *
 * WAJIB DIHAPUS SETELAH SELESAI:  rm -f tools/dbg_admin_error.php
 *
 * Keamanan: hanya jalan kalau token cocok, hanya menampilkan pesan error (bukan data bisnis),
 * dan tidak menulis apa pun selain log milik aplikasi.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

const DBG_TOKEN = 'dbg17';
if ((string)($_GET['k'] ?? '') !== DBG_TOKEN) {
    http_response_code(403);
    exit('Token salah.');
}

$pages = [
    'admin/pos/index.php' => ['id' => null],
    'admin/customers/edit.php' => ['id' => (int)($_GET['id'] ?? 0)],
];
$p = (string)($_GET['p'] ?? 'admin/pos/index.php');
$p = str_replace(['..', "\0", '\\'], '', $p);
$ROOT = dirname(__DIR__);
$target = $ROOT . '/legacy/public/' . $p;

header('Content-Type: text/plain; charset=utf-8');
echo "DBG v17.0.17 — docroot: $ROOT\n";
echo "PHP      : " . PHP_VERSION . "\n";
echo "target   : $target\n";
echo "VERSION  : " . trim((string)@file_get_contents($ROOT . '/VERSION')) . "\n";

if (!is_file($target)) { exit("target tidak ada\n"); }

// --- isi sesi login supaya guard Auth::requireLogin() lolos (mirror dari Auth::login) ---
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION['auth_user'] = [
    'id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'role_slug' => 'super_admin',
    'name' => 'Diagnostik', 'username' => 'admin', 'email' => 'debug@local', 'avatar' => null,
];
unset($_SESSION['auth_permissions']);

$_SERVER['SCRIPT_NAME'] = '/' . $p;
$_SERVER['PHP_SELF'] = '/' . $p;
$_SERVER['REQUEST_URI'] = '/' . $p . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : '');
if (isset($_GET['id'])) { $_GET['id'] = (int)$_GET['id']; }

// --- jalankan halaman aslinya, tampung output, cetak error kalau ada ---
ob_start();
try {
    require $target;
    $out = ob_get_clean();
    echo "STATUS: halaman selesai TANPA error (HTML " . strlen($out) . " byte)\n";
    // V17.0.19: hitung penanda penting supaya bisa dibedakan "halaman kosong" vs "file ketinggalan versi"
    $markers = ['data-product-group','request-product-row','stock-pill','request-product-list','request-cart-items','member-result-btn'];
    $found = [];
    foreach ($markers as $mk) {
        $n = substr_count($out, $mk);
        if ($n > 0) $found[] = $mk . '=' . $n;
    }
    echo 'PENANDA   : ' . ($found ? implode(' | ', $found) : '(tidak ada penanda dikenali)') . "\n";
    // V17.0.20b: tunjukkan potongan HTML asli area daftar produk supaya bisa dibedakan
    // "HTML kosong" vs "HTML ada tapi tidak terlihat (CSS)".
    if (isset($_GET['snippet'])) {
        foreach (['requestProductList', 'request-product-row', 'request-parent-heading'] as $needle) {
            $pos = strpos($out, $needle);
            echo "\n--- SNIPPET '$needle' (offset " . ($pos === false ? 'TIDAK DITEMUKAN' : $pos) . ") ---\n";
            if ($pos !== false) {
                echo preg_replace('/\s+/', ' ', substr($out, max(0, $pos - 120), 700)) . "\n";
            }
        }
    }
} catch (Throwable $e) {
    $out = ob_get_clean();
    echo "STATUS: GAGAL setelah render " . strlen($out) . " byte\n\n";
    echo "=== ERROR ===\n";
    echo 'jenis : ' . get_class($e) . "\n";
    echo 'pesan : ' . $e->getMessage() . "\n";
    echo 'file  : ' . $e->getFile() . ':' . $e->getLine() . "\n\n";
    echo "=== TRACE ===\n" . $e->getTraceAsString() . "\n";
}

// --- ringkas log terakhir aplikasi (kalau ada) ---
echo "\n=== 10 baris log terakhir (writable/logs) ===\n";
$logs = glob($ROOT . '/writable/logs/log-*.php') ?: [];
usort($logs, fn($a, $b) => filemtime($b) <=> filemtime($a));
$n = 0;
foreach ($logs as $f) {
    $lines = @file($f, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lines, -6) as $l) { echo rtrim($l) . "\n"; $n++; if ($n >= 10) break 2; }
}
if ($n === 0) { echo "(tidak ada log terbaca)\n"; }
echo "\n=== selesai — HAPUS file ini: rm -f tools/dbg_admin_error.php ===\n";