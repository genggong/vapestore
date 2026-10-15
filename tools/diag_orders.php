<?php
/**
 * DIAGNOSA LAPORAN TRANSAKSI — cek kenapa order online tidak tampil di laporan POS.
 * Jalankan: php tools/diag_orders.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
if (!$loaded) { fwrite(STDERR, "bootstrap.php tidak ditemukan.\n"); exit(1); }
require_once __DIR__ . '/../legacy/app/Modules/Marketplace/Marketplace.php';
if (!class_exists('Database')) { fwrite(STDERR, "Database class tidak ada.\n"); exit(1); }

$db = Database::connect();

function q1(PDO $db, string $sql, array $p = []): int { return (int)($db->prepare($sql)->execute($p) ? $db->query('SELECT ROW_COUNT()')->fetchColumn() : 0); }
function cnt(PDO $db, string $sql): int { $s = $db->query($sql); return (int)($s ? $s->fetchColumn() : -1); }

echo "=== DIAGNOSA LAPORAN TRANSAKSI ===\n";
echo "1. Online orders TOTAL            : " . cnt($db, "SELECT COUNT(*) FROM online_orders") . "\n";
echo "2. paid+completed                 : " . cnt($db, "SELECT COUNT(*) FROM online_orders WHERE payment_status='paid' AND order_status='completed'") . "\n";
echo "3. paid+completed blm tercatat    : " . cnt($db, "SELECT COUNT(*) FROM online_orders WHERE payment_status='paid' AND order_status='completed' AND pos_recorded_at IS NULL") . "\n";
echo "4. Transaksi online_order di POS  : " . cnt($db, "SELECT COUNT(*) FROM transactions WHERE reference_type='online_order'") . "\n";
echo "5. Total transactions di POS      : " . cnt($db, "SELECT COUNT(*) FROM transactions") . "\n";
echo "6. Kolom pos_recorded_at ada?     : " . (cnt($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_orders' AND COLUMN_NAME='pos_recorded_at'") > 0 ? 'YA' : 'TIDAK') . "\n";
echo "7. Fungsi record_pos_transaction  : " . (function_exists('marketplace_record_pos_transaction') ? 'ADA' : 'TIDAK ADA') . "\n";
echo "\n=== 5 ORDER TERAKHIR ===\n";
$rows = $db->query("SELECT id, order_number, payment_status, order_status, pos_recorded_at, grand_total, created_at FROM online_orders ORDER BY id DESC LIMIT 5")->fetchAll() ?: [];
foreach ($rows as $r) {
    printf("  #%-4s %-18s pay=%-9s st=%-10s rec=%s Rp%s (%s)\n",
        $r['id'], $r['order_number'], $r['payment_status'], $r['order_status'],
        $r['pos_recorded_at'] ?? '-', number_format((float)$r['grand_total']), $r['created_at']);
}
echo "\n=== 5 TRANSAKSI ONLINE TERAKHIR DI POS ===\n";
$rows = $db->query("SELECT id, invoice_number, grand_total, transaction_date, note FROM transactions WHERE reference_type='online_order' ORDER BY id DESC LIMIT 5")->fetchAll() ?: [];
foreach ($rows as $r) {
    printf("  #%-6s %-18s Rp%-10s (%s) %s\n", $r['id'], $r['invoice_number'], number_format((float)$r['grand_total']), $r['transaction_date'], $r['note']);
}
echo "\n=== STATUS ORDER (semua) ===\n";
$rows = $db->query("SELECT payment_status, order_status, COUNT(*) c FROM online_orders GROUP BY payment_status, order_status ORDER BY c DESC")->fetchAll() ?: [];
foreach ($rows as $r) echo "  pay={$r['payment_status']} st={$r['order_status']}: {$r['c']}\n";
echo "\n=== SELESAI ===\n";