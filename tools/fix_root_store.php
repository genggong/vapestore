<?php
/**
 * Vapekong POS — fix folder nyasar di root public_html (v17.0.13)
 * Bersihkan folder store/, admin/, assets/ yang terbentuk dari ekstrak
 * zip fix lama (path root, bukan legacy/public). Semua di-backup dulu.
 *
 * Cara pakai:  php tools/fix_root_store.php
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

function out(string $s): void { echo $s . "\n"; }
function backup(string $dir, string $stamp): ?string {
    $target = $dir . '_backup_' . $stamp;
    if (!is_dir($dir)) return null;
    if (rename($dir, $target)) return $target;
    // coba copy recursive kalau rename lintas partisi gagal
    if (copy_dir($dir, $target)) return $target;
    return null;
}
function copy_dir(string $src, string $dst): bool {
    if (!mkdir($dst, 0755, true) && !is_dir($dst)) return false;
    $ok = true;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = substr($item->getPathname(), strlen($src));
        $dest = $dst . '/' . ltrim($rel, '/\\');
        if ($item->isDir()) { if (!is_dir($dest)) @mkdir($dest, 0755, true); }
        elseif ($item->isFile() && !copy($item->getPathname(), $dest)) { $ok = false; }
    }
    return $ok;
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

$root = dirname(__DIR__);            // public_html
$stamp = date('Ymd_His');
out('=== FIX ROOT STORE v17.0.13 ===');
out('Root: ' . $root);

// 1) store/ di root (nyasar dari zip fix — cuma index.php, tanpa _data.php)
$store = $root . '/store';
if (is_dir($store)) {
    $files = array_values(array_diff(scandir($store), ['.', '..']));
    $hasData = file_exists($store . '/_data.php');
    out('store/ root ketemu: ' . count($files) . ' file, _data.php=' . ($hasData ? 'ADA' : 'TIDAK'));
    if (!$hasData && count($files) <= 10) {
        $b = backup($store, $stamp);
        if ($b) { out('  -> BACKUP ke ' . basename($b)); rm_tree($store); out('  -> store/ root DIHAPUS (aman, yang asli ada di public/store & legacy/public/store)'); }
        else out('  -> GAGAL backup store/ — SKIP, cek manual');
    } else {
        out('  -> isi mencurigakan (punya _data.php atau >10 file) — TIDAK disentuh');
    }
} else out('store/ root: tidak ada (bagus)');

// 2) admin/ di root (nyasar dari zip fix — admin asli di legacy/public/admin)
$admin = $root . '/admin';
if (is_dir($admin)) {
    $legacyAdmin = $root . '/legacy/public/admin';
    out('admin/ root ketemu (asli: ' . (is_dir($legacyAdmin) ? 'legacy/public/admin ADA' : 'legacy/public/admin TIDAK ADA') . ')');
    $b = backup($admin, $stamp);
    if ($b) { out('  -> BACKUP ke ' . basename($b)); rm_tree($admin); out('  -> admin/ root DIHAPUS (aman, admin asli di legacy/public/admin)'); }
    else out('  -> GAGAL backup admin/ — SKIP');
} else out('admin/ root: tidak ada (bagus)');

// 3) assets/js di root (zip fix cuma punya assets/js/stock-request.js; sisakan kalau berisi uploads)
$assetsJs = $root . '/assets/js';
if (is_dir($assetsJs)) {
    $jsFiles = array_values(array_diff(scandir($assetsJs), ['.', '..']));
    $onlyStockReq = $jsFiles === ['stock-request.js'];
    if ($onlyStockReq) {
        $b = backup($assetsJs, $stamp);
        if ($b) { out('assets/js root (hanya stock-request.js) -> BACKUP ' . basename($b)); rm_tree($assetsJs); @rmdir($root . '/assets'); out('  -> assets/js root DIHAPUS'); }
        else out('  -> GAGAL backup assets/js — SKIP');
    } else out('assets/js root berisi ' . count($jsFiles) . ' file lain — TIDAK disentuh');
} else out('assets/js root: tidak ada (bagus)');

// 4) Cek file penting masih utuh
$checks = [
    'index.php' => $root . '/index.php',
    'app/bootstrap.php' => $root . '/app/bootstrap.php',
    'legacy/public/store/index.php' => $root . '/legacy/public/store/index.php',
    'legacy/public/store/_data.php' => $root . '/legacy/public/store/_data.php',
    'public/store/index.php' => $root . '/public/store/index.php',
    'public/store/_data.php' => $root . '/public/store/_data.php',
    'legacy/public/index.php' => $root . '/legacy/public/index.php',
];
out('');
out('=== CEK FILE PENTING ===');
foreach ($checks as $label => $p) {
    out(str_pad($label, 38) . (file_exists($p) ? 'ADA ' . filesize($p) . 'B' : '*** HILANG ***'));
}

out('');
out('=== SELESAI ===');
out('Next: purge LiteSpeed Cache (cPanel -> LiteSpeed Cache Manager -> Purge All), lalu tes / , /store , /produk.');
