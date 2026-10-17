<?php
/**
 * V17.0.13 — Backfill: catat semua order marketplace paid+completed
 * yang BELUM masuk transaksi POS (pos_recorded_at NULL) ke transactions.
 *
 * Idempotent & aman diulang: order yang sudah tercatat otomatis dilewati.
 * Jalankan SEKALI di hosting setelah timpa file fix (cPanel Terminal):
 *   cd ~/public_html && php tools/backfill_pos_transactions.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$candidates = [
    // Hosting vapekongstore memuat app/bootstrap.php LANGSUNG (root tree) — kandidat ini harus paling awal.
    __DIR__ . '/../app/bootstrap.php',
    getcwd() . '/../app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    __DIR__ . '/legacy/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
    getcwd() . '/../legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (file_exists($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded) { fwrite(STDERR, "bootstrap.php tidak ditemukan. Jalankan dari folder aplikasi.\n"); exit(1); }
// Modul marketplace: root tree dulu (hosting memuat app/), lalu legacy.
foreach ([
    __DIR__ . '/../app/Modules/Marketplace/Marketplace.php',
    __DIR__ . '/../legacy/app/Modules/Marketplace/Marketplace.php',
    getcwd() . '/app/Modules/Marketplace/Marketplace.php',
    getcwd() . '/legacy/app/Modules/Marketplace/Marketplace.php',
] as $mod) {
    if (is_file($mod)) { require_once $mod; break; }
}
if (!class_exists('Database')) { fwrite(STDERR, "Database class tidak ada.\n"); exit(1); }

$db = Database::connect();

// Bootstrap mematikan display_errors saat debug=false -> kegagalan fatal terlihat seperti "sukses
// tanpa output". Nyalakan ulang SETELAH bootstrap + laporkan fatal terakhir lewat shutdown handler.
ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        fwrite(STDERR, 'FATAL: ' . $e['message'] . ' (' . $e['file'] . ':' . $e['line'] . ')' . PHP_EOL);
    }
});
echo 'DB OK: ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;

// Prasyarat kolom v17.0.11/v17.0.13: dibuat oleh installer marketplace (idempotent).
try {
    if (function_exists('marketplace_column_exists') && function_exists('marketplace_install')) {
        if (!marketplace_column_exists($db, 'online_orders', 'pos_recorded_at')
            || !marketplace_column_exists($db, 'transactions', 'shipping_cost')) {
            marketplace_install($db);
        }
    }
} catch (Throwable $e) { /* dicek tegas di bawah */ }

if (function_exists('marketplace_column_exists') && !marketplace_column_exists($db, 'online_orders', 'pos_recorded_at')) {
    fwrite(STDERR, 'Kolom online_orders.pos_recorded_at belum ada di database ini.' . PHP_EOL);
    fwrite(STDERR, 'Jalankan dulu: buka /admin/system-upgrade/index.php lalu klik "Jalankan Upgrade Otomatis",' . PHP_EOL);
    fwrite(STDERR, 'lalu ulangi perintah backfill ini.' . PHP_EOL);
    exit(1);
}
$cand = $db->query(
    "SELECT id, order_number FROM online_orders
     WHERE payment_status='paid' AND order_status='completed' AND pos_recorded_at IS NULL
     ORDER BY id ASC"
)->fetchAll() ?: [];

echo "Kandidat belum tercatat POS: " . count($cand) . "\n";
$ok = 0; $fail = 0;
foreach ($cand as $c) {
    try {
        $res = marketplace_record_pos_transaction($db, (int)$c['id']);
        if ($res !== null) { echo "  OK  order #{$c['id']} ({$c['order_number']}) -> transaksi #{$res}\n"; $ok++; }
    } catch (Throwable $e) {
        echo "  GAGAL order #{$c['id']}: " . $e->getMessage() . "\n";
        $fail++;
    }
}
echo "Selesai: {$ok} tercatat, {$fail} gagal.\n";