<?php
/**
 * VAPEKONG FIX SATU-KALI (v17.0.15)
 * - Backup & hapus folder nyasar root: store/, admin/, assets/js
 * - Timpa index.php ROOT dengan versi CI4 (legacy bridge) jika belum CI4
 * - Verifikasi file penting
 * Jalankan:  php apply_fix_store.php   (dari ~/public_html)
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

function out(string $s): void { echo $s . "\n"; }
function rm_backup(string $dir, string $stamp): void {
    if (!is_dir($dir)) return;
    $dst = $dir . '_backup_' . $stamp;
    if (rename($dir, $dst)) out("BACKUP+HAPUS  $dir -> " . basename($dst));
    else out("GAGAL backup  $dir — cek izin/permission");
}
function rm_tree(string $dir): void {
    if (!is_dir($dir)) { @unlink($dir); return; }
    foreach (scandir($dir) as $i) {
        if ($i === '.' || $i === '..') continue;
        $p = $dir . '/' . $i;
        is_dir($p) ? rm_tree($p) : @unlink($p);
    }
    @rmdir($dir);
}

$root = dirname(__DIR__);

// ---- isi index.php ROOT yang benar (CI4 legacy bridge) ----
$ci4Index = <<<'PHP'
<?php

use CodeIgniter\Boot;
use Config\Paths;

$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;
    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

require FCPATH . 'app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
PHP;

out('=== VAPEKONG FIX SATU-KALI v17.0.15 ===');
out('Root: ' . $root);
out('');

// 1) index.php ROOT: pastikan versi CI4
$rootIndex = $root . '/index.php';
$isCI4 = (is_file($rootIndex) && strpos((string)file_get_contents($rootIndex), 'CodeIgniter\\Boot') !== false);
if ($isCI4) {
    out('index.php ROOT: sudah CI4 (baik, tidak diubah)');
} else {
    $backup = $rootIndex . '.bak_' . date('Ymd_His');
    if (file_exists($rootIndex)) { rename($rootIndex, $backup); out('index.php ROOT lama  -> ' . basename($backup)); }
    file_put_contents($rootIndex, $ci4Index);
    out('index.php ROOT DITIMPA versi CI4. Cek hasil: ' . (strpos((string)file_get_contents($rootIndex), 'CodeIgniter\\Boot') !== false ? 'OK' : 'GAGAL'));
}
out('');

// 2) Hapus folder nyasar root (semua di-backup dulu)
$stamp = date('Ymd_His');
foreach (['store', 'admin', 'assets'] as $d) {
    $p = $root . '/' . $d;
    if (!is_dir($p)) { out('tidak ada folder: ' . $d); continue; }
    // assets: hanya hapus jika berisi js/stock-request.js saja (nyasar), selainnya jangan sentuh
    if ($d === 'assets' && is_dir($p . '/js')) {
        $js = array_values(array_diff(scandir($p . '/js'), ['.', '..']));
        if ($js === ['stock-request.js']) {
            rm_backup($p . '/js', $stamp);
            @rmdir($p);
            out('assets/js (isinya cuma stock-request.js) DIHAPUS');
        } else {
            out('assets/ berisi ' . count($js) . ' file js lain — TIDAK disentuh');
        }
    } elseif ($d === 'assets') {
        out('assets/ tanpa folder js — TIDAK disentuh');
    } else {
        rm_backup($p, $stamp);
    }
}
out('');

// 3) Verifikasi
out('=== VERIFIKASI ===');
$checks = [
    'index.php (CI4?)' => $root . '/index.php',
    'app/bootstrap.php' => $root . '/app/bootstrap.php',
    'legacy/public/index.php' => $root . '/legacy/public/index.php',
    'legacy/public/store/index.php' => $root . '/legacy/public/store/index.php',
    'legacy/public/store/_data.php' => $root . '/legacy/public/store/_data.php',
    'public/store/index.php' => $root . '/public/store/index.php',
];
foreach ($checks as $label => $p) {
    $extra = '';
    if (str_contains($label, 'CI4')) $extra = is_file($p) && strpos((string)file_get_contents($p), 'CodeIgniter\\Boot') !== false ? ' [CI4 OK]' : ' [BUKAN CI4]';
    out(str_pad($label, 32) . (file_exists($p) ? 'ADA ' . filesize($p) . 'B' . $extra : '*** HILANG ***'));
}
out('');
out('=== SELESAI ===');
out('Langkah terakhir: cPanel -> LiteSpeed Cache Manager -> Purge All, lalu tes:');
out('  https://vapekongstore.com/  (harusnya home storefront, bukan FAQ)');
out('  https://vapekongstore.com/store  (harusnya 200, bukan 500)');