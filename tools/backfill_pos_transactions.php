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
require_once __DIR__ . '/../legacy/app/Modules/Marketplace/Marketplace.php';
if (!class_exists('Database')) { fwrite(STDERR, "Database class tidak ada.\n"); exit(1); }

$db = Database::connect();
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