<?php
/**
 * V17.0.19 — Set palet tema marketplace ke HITAM PUTIH (monokrom).
 *
 * Jalankan dari docroot:  php tools/set_theme_mono.php
 * Kembalikan ke graphite: php tools/set_theme_mono.php --revert
 * Preset lain:            php tools/set_theme_mono.php --preset=blue
 *
 * Aman diulang. Hanya mengubah baris setting tema di tabel `settings`
 * (tidak menyentuh produk/transaksi/pesanan).
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$args = $argv ?? [];
$preset = 'monochrome';
foreach ($args as $a) {
    if ($a === '--revert') $preset = 'graphite';
    if (str_starts_with($a, '--preset=')) $preset = strtolower(trim(substr($a, 9)));
}

$candidates = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (is_file($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded || !class_exists('Database')) {
    fwrite(STDERR, "bootstrap aplikasi tidak ditemukan — jalankan dari docroot (cd ~/public_html && php tools/set_theme_mono.php)\n");
    exit(1);
}

$db = Database::connect();
echo 'DB OK: ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . "\n";

// Palet untuk tiap preset yang didukung tool ini
$palettes = [
    'monochrome' => ['#111111', '#000000', '#333333'],
    'graphite'   => ['#334155', '#0F172A', '#475569'],
    'purple'     => ['#7C3AED', '#5B21B6', '#A855F7'],
];
if (!isset($palettes[$preset])) {
    fwrite(STDERR, "preset '$preset' tidak didukung tool ini (pilihan: " . implode(', ', array_keys($palettes)) . ")\n");
    exit(1);
}
[$primary, $secondary, $accent] = $palettes[$preset];

$before = [];
$q = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'marketplace_theme_%'");
foreach ($q->fetchAll() as $row) { $before[$row['setting_key']] = $row['setting_value']; }
echo "SEBELUM: preset=" . ($before['marketplace_theme_preset'] ?? '-') . " primary=" . ($before['marketplace_theme_primary'] ?? '-') . "\n";

$set = static function (PDO $db, string $key, string $value): void {
    $upd = $db->prepare("UPDATE settings SET setting_value=?, updated_at=NOW() WHERE setting_key=?");
    $upd->execute([$value, $key]);
    if ($upd->rowCount() === 0) {
        $chk = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key=?");
        $chk->execute([$key]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $db->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?,?,NOW(),NOW())");
            $ins->execute([$key, $value]);
        }
    }
};

$set($db, 'marketplace_theme_preset', $preset);
$set($db, 'marketplace_theme_primary', $primary);
$set($db, 'marketplace_theme_secondary', $secondary);
$set($db, 'marketplace_theme_accent', $accent);

$after = [];
$q = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'marketplace_theme_%'");
foreach ($q->fetchAll() as $row) { $after[$row['setting_key']] = $row['setting_value']; }
echo "SESUDAH: preset=" . ($after['marketplace_theme_preset'] ?? '-')
    . " primary=" . ($after['marketplace_theme_primary'] ?? '-')
    . " secondary=" . ($after['marketplace_theme_secondary'] ?? '-')
    . " accent=" . ($after['marketplace_theme_accent'] ?? '-') . "\n";
echo ($after['marketplace_theme_preset'] ?? '') === $preset ? "OK: tema marketplace = $preset\n" : "PERINGATAN: setting tidak berubah, cek permission tabel settings\n";
echo "Catatan: aset CSS di-cache browser — lakukan Purge All lalu hard refresh (Ctrl+F5).\n";
echo "SELESAI\n";