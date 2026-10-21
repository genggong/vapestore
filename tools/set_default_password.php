<?php
/**
 * V17.0.32 — Isi password marketplace default (vapekong123) untuk pelanggan yang belum punya.
 *
 * Default = DRY-RUN (hanya menghitung). Untuk benar-benar menulis pakai: --apply
 * Hanya mengisi yang kosong, TIDAK menimpa password yang sudah ada. Aman diulang.
 *
 *   php tools/set_default_password.php              # lihat berapa yang akan diisi
 *   php tools/set_default_password.php --apply      # tulis password default
 *   php tools/set_default_password.php --apply --password=Rahasia123
 *
 * Setelah dipakai, HAPUS file ini dari hosting.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$defaultPassword = 'vapekong123';
foreach ($args as $a) {
    if (stripos($a, '--password=') === 0) {
        $v = trim(substr($a, strlen('--password=')));
        if ($v !== '') $defaultPassword = $v;
    }
}
if (strlen($defaultPassword) < 6) {
    fwrite(STDERR, "Password minimal 6 karakter.\n");
    exit(1);
}

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
    fwrite(STDERR, "bootstrap.php aplikasi tidak ditemukan. Jalankan dari docroot (cd ~/public_html && php tools/set_default_password.php)\n");
    exit(1);
}
if (!class_exists('Database')) {
    fwrite(STDERR, "Kelas Database tidak ada — bootstrap.php salah path.\n");
    exit(1);
}

$db = Database::connect();
echo 'DB OK: ' . (string) $db->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo 'Mode: ' . ($apply ? "APPLY (menulis perubahan)" : "DRY-RUN (tidak menulis apa pun)") . "\n";

// --- Auto-upgrade kolom (idempotent, tanpa DROP) ---
try {
    foreach ([
        'marketplace_password_hash' => 'VARCHAR(255) NULL',
        'marketplace_registered_at' => 'DATETIME NULL',
        'marketplace_last_login_at' => 'DATETIME NULL',
    ] as $col => $def) {
        $chk = $db->query("SHOW COLUMNS FROM customers LIKE " . $db->quote($col));
        if (!$chk->fetch()) {
            if ($apply) {
                $db->exec("ALTER TABLE customers ADD COLUMN {$col} {$def}");
                echo "Kolom baru dibuat: customers.{$col}\n";
            } else {
                echo "Kolom belum ada: customers.{$col} (akan dibuat saat --apply)\n";
            }
        }
    }
    echo "Kolom password marketplace: siap.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal cek/buat kolom: ' . $e->getMessage() . "\n");
    exit(1);
}

$emptyWhere = "status='active' AND (marketplace_password_hash IS NULL OR marketplace_password_hash='')";
$count = (int) $db->query("SELECT COUNT(*) FROM customers WHERE {$emptyWhere}")->fetchColumn();
echo "Pelanggan aktif tanpa password: {$count}\n";

if ($count === 0) {
    echo "Tidak ada yang perlu diisi.\n";
} elseif (!$apply) {
    echo "Akan di-set password default '{$defaultPassword}' untuk {$count} pelanggan.\n";
    echo "Jalankan ulang dengan --apply untuk menyimpan.\n";
    $sample = $db->query("SELECT id, name, phone FROM customers WHERE {$emptyWhere} ORDER BY id DESC LIMIT 5")->fetchAll();
    foreach (($sample ?: []) as $s) {
        echo "  - #{$s['id']} {$s['name']} ({$s['phone']})\n";
    }
} else {
    $stmt = $db->prepare("UPDATE customers SET marketplace_password_hash=?, marketplace_registered_at=COALESCE(marketplace_registered_at,NOW())
                          WHERE {$emptyWhere}");
    $stmt->execute([password_hash($defaultPassword, PASSWORD_DEFAULT)]);
    echo "Password default '{$defaultPassword}' di-set untuk {$stmt->rowCount()} pelanggan.\n";
}

$verified = (int) $db->query("SELECT COUNT(*) FROM customers WHERE status='active' AND marketplace_password_hash IS NOT NULL AND marketplace_password_hash != ''")->fetchColumn();
echo "Total pelanggan aktif yang punya password: {$verified}\n";

$sample = $db->query("SELECT id, name, marketplace_password_hash FROM customers WHERE status='active' AND marketplace_password_hash IS NOT NULL AND marketplace_password_hash != '' ORDER BY id DESC LIMIT 1")->fetch();
if ($sample) {
    $cocok = password_verify($defaultPassword, (string) $sample['marketplace_password_hash']);
    echo "Cek sample #{$sample['id']} ({$sample['name']}): " . ($cocok ? "bisa login dengan '{$defaultPassword}'" : 'memakai password lain (tidak ditimpa)') . "\n";
}
echo "SELESAI\n";
