<?php
/**
 * V17.0.12 — Set password marketplace default utk pelanggan member.
 *
 * Semua pelanggan member yg BELUM punya password marketplace
 * (marketplace_password_hash NULL/kosong) di-set password = vapekong123.
 * Login marketplace: pakai No HP + password ini.
 *
 * AMAN: hanya mengisi yg kosong, TIDAK menimpa password yg sudah ada.
 *
 * MEMAKAI koneksi DB dari konfigurasi aplikasi (Database::connect) —
 * tidak ada kredensial hardcoded, jadi jalan otomatis di hosting.
 * Sekaligus auto-buat kolom password jika DB belum pernah di-upgrade.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Temukan bootstrap aplikasi (jalan dari folder mana pun) ---
$candidates = [
    __DIR__ . '/../legacy/app/bootstrap.php',
    __DIR__ . '/legacy/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
    getcwd() . '/../legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (file_exists($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded) {
    fwrite(STDERR, "bootstrap.php aplikasi tidak ditemukan. Jalankan skrip dari folder aplikasi (contoh: cd ~/public_html && php tools/set_default_password.php)\n");
    exit(1);
}
if (!class_exists('Database')) {
    fwrite(STDERR, "Kelas Database tidak ada — bootstrap.php salah path.\n");
    exit(1);
}

$db = Database::connect();
$defaultPassword = 'vapekong123';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// --- Auto-upgrade: pastikan kolom password ada (idempotent, aman diulang) ---
try {
    $col = $db->query("SHOW COLUMNS FROM customers LIKE 'marketplace_password_hash'")->fetch();
    if (!$col) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_password_hash VARCHAR(255) NULL");
    $col2 = $db->query("SHOW COLUMNS FROM customers LIKE 'marketplace_registered_at'")->fetch();
    if (!$col2) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_registered_at DATETIME NULL");
    $col3 = $db->query("SHOW COLUMNS FROM customers LIKE 'marketplace_last_login_at'")->fetch();
    if (!$col3) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_last_login_at DATETIME NULL");
    echo "Kolom password marketplace: OK (sudah ada / baru dibuat).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal cek/buat kolom: " . $e->getMessage() . "\n");
    exit(1);
}

// --- Hitung yg akan di-set ---
$count = $db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')")->fetchColumn();
echo "Pelanggan aktif tanpa password: {$count}\n";

if ($count > 0) {
    $stmt = $db->prepare("UPDATE customers SET marketplace_password_hash=?, marketplace_registered_at=COALESCE(marketplace_registered_at,NOW()), updated_at=NOW()
                          WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')");
    $stmt->execute([$hash]);
    echo "Password default '" . $defaultPassword . "' di-set untuk {$stmt->rowCount()} pelanggan.\n";
}

// --- Verifikasi ---
$verified = $db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND marketplace_password_hash IS NOT NULL AND marketplace_password_hash != ''")->fetchColumn();
echo "Total pelanggan aktif yg punya password sekarang: {$verified}\n";

$sample = $db->query("SELECT id, name, phone, marketplace_password_hash FROM customers WHERE marketplace_password_hash IS NOT NULL AND marketplace_password_hash != '' LIMIT 1")->fetch();
if ($sample) {
    $ok = password_verify($defaultPassword, $sample['marketplace_password_hash']);
    echo "Cek sample pelanggan #{$sample['id']} ({$sample['name']}) login password default: " . ($ok ? 'BERHASIL' : 'GAGAL (password lama berbeda)') . "\n";
}
echo "SELESAI\n";