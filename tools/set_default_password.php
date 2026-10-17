<?php
/**
 * V17.0.17 — Set password marketplace default utk pelanggan member (vapekong123).
 *
 * Semua pelanggan aktif yg BELUM punya password marketplace (hash NULL/kosong) di-set
 * password = vapekong123. Login marketplace: No HP + password ini.
 *
 * AMAN: hanya mengisi yg kosong, TIDAK menimpa password yg sudah ada. Aman diulang.
 * Pakai koneksi DB dari konfigurasi aplikasi (Database::connect) — tanpa kredensial hardcoded.
 * Sekaligus memastikan kolom password marketplace ada (idempotent, tanpa DROP).
 *
 * Jalankan dari docroot: php tools/set_default_password.php
 * Setelah selesai, HAPUS file ini dari hosting (berisi akses DB).
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

// --- Bootstrap: docroot memuat app/bootstrap.php LANGSUNG (bukan legacy/app) ---
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
if (!$loaded) {
    fwrite(STDERR, "bootstrap.php aplikasi tidak ditemukan. Jalankan dari docroot (contoh: cd ~/public_html && php tools/set_default_password.php)\n");
    exit(1);
}
if (!class_exists('Database')) {
    fwrite(STDERR, "Kelas Database tidak ada — bootstrap.php salah path.\n");
    exit(1);
}

$db = Database::connect();
echo 'DB OK: ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . "\n";

$defaultPassword = 'vapekong123';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// --- Auto-upgrade kolom (idempotent) ---
try {
    foreach ([
        'marketplace_password_hash' => 'VARCHAR(255) NULL',
        'marketplace_registered_at' => 'DATETIME NULL',
        'marketplace_last_login_at' => 'DATETIME NULL',
    ] as $col => $def) {
        $chk = $db->query("SHOW COLUMNS FROM customers LIKE " . $db->quote($col));
        if (!$chk->fetch()) {
            $db->exec("ALTER TABLE customers ADD COLUMN {$col} {$def}");
            echo "Kolom baru dibuat: customers.{$col}\n";
        }
    }
    echo "Kolom password marketplace: OK (sudah ada / baru dibuat).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal cek/buat kolom: ' . $e->getMessage() . "\n");
    exit(1);
}

$count = (int)$db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')")->fetchColumn();
echo "Pelanggan aktif tanpa password: {$count}\n";

if ($count > 0) {
    $stmt = $db->prepare("UPDATE customers SET marketplace_password_hash=?, marketplace_registered_at=COALESCE(marketplace_registered_at,NOW()), updated_at=NOW()
                          WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')");
    $stmt->execute([$hash]);
    echo "Password default '" . $defaultPassword . "' di-set untuk {$stmt->rowCount()} pelanggan.\n";
}

$verified = (int)$db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND marketplace_password_hash IS NOT NULL AND marketplace_password_hash != ''")->fetchColumn();
echo "Total pelanggan aktif yg punya password sekarang: {$verified}\n";

$sample = $db->query("SELECT id, name, phone, marketplace_password_hash FROM customers WHERE marketplace_password_hash IS NOT NULL AND marketplace_password_hash != '' LIMIT 1")->fetch();
if ($sample) {
    $ok = password_verify($defaultPassword, (string)$sample['marketplace_password_hash']);
    echo "Cek sample pelanggan #{$sample['id']} ({$sample['name']}) login password default: " . ($ok ? 'BERHASIL' : 'GAGAL (password lama berbeda)') . "\n";
}
echo "SELESAI\n";