<?php
/**
 * V17.0.11 — Set password marketplace default utk pelanggan member.
 *
 * Semua pelanggan member yang BELUM punya password marketplace
 * (marketplace_password_hash NULL/kosong) di-set password = vapekong123.
 * Login marketplace: pakai No HP + password ini.
 *
 * AMAN: hanya mengisi yang kosong, TIDAK menimpa password yang sudah ada.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=vapekong_pos;charset=utf8mb4',
    getenv('VAPEKONG_DB_USER') ?: 'vapekong_pospalasanga',
    getenv('VAPEKONG_DB_PASS') ?: 'M@ver1ck',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$defaultPassword = 'vapekong123';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Hitung dulu yang akan di-set
$count = $db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')")->fetchColumn();
echo "Pelanggan aktif tanpa password: {$count}\n";

if ($count > 0) {
    $stmt = $db->prepare("UPDATE customers SET marketplace_password_hash=?, marketplace_registered_at=COALESCE(marketplace_registered_at,NOW()), updated_at=NOW()
                          WHERE status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')");
    $stmt->execute([$hash]);
    echo "Password default '{$defaultPassword}' di-set untuk {$stmt->rowCount()} pelanggan.\n";
}

// Verifikasi
$verified = $db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND marketplace_password_hash IS NOT NULL AND marketplace_password_hash != ''")->fetchColumn();
echo "Total pelanggan aktif yg punya password sekarang: {$verified}\n";

// Tes 1 login (contoh cek pertama yg punya password)
$sample = $db->query("SELECT id, name, phone, marketplace_password_hash FROM customers WHERE marketplace_password_hash IS NOT NULL AND marketplace_password_hash != '' LIMIT 1")->fetch();
if ($sample) {
    $ok = password_verify($defaultPassword, $sample['marketplace_password_hash']);
    echo "Cek sample pelanggan #{$sample['id']} ({$sample['name']}) login password default: " . ($ok ? 'BERHASIL' : 'GAGAL (password lama berbeda)') . "\n";
}
echo "SELESAI\n";