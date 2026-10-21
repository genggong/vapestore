<?php
/**
 * Kirim ulang notifikasi WhatsApp pesanan marketplace yang gagal/pending.
 *
 * Pemakaian:
 *   php tools/send_pending_order_notifications.php            # lihat antrean (dry-run)
 *   php tools/send_pending_order_notifications.php --send     # kirim sekarang
 *   php tools/send_pending_order_notifications.php --send --retry --limit=20
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/legacy/app/bootstrap.php';
require_once $root . '/legacy/app/Modules/Marketplace/Marketplace.php';

$args = $argv ?? [];
$send = in_array('--send', $args, true);
$retry = in_array('--retry', $args, true);
$limit = 10;
foreach ($args as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) $limit = max(1, min(50, (int) $m[1]));
}

$db = Database::connect();
marketplace_order_notify_install($db);

$statuses = $retry ? "'pending','failed'" : "'pending'";
$rows = $db->query("SELECT id,order_id,target,status,attempts,LEFT(COALESCE(last_error,''),90) AS err,created_at
                      FROM marketplace_order_notifications
                     WHERE status IN ($statuses) AND attempts < 5
                     ORDER BY id ASC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "Antrean notifikasi pesanan marketplace (" . ($retry ? 'pending+failed' : 'pending') . "): " . count($rows) . " baris\n";
foreach ($rows as $r) {
    printf("  #%d order=%s ke=%s status=%s attempt=%d %s\n", $r['id'], $r['order_id'], $r['target'], $r['status'], $r['attempts'], $r['err'] ? '| ' . $r['err'] : '');
}

if (!$rows) exit(0);
if (!$send) {
    echo "\nDry-run. Tambahkan --send untuk mengirim (opsional --retry untuk ikut mengulang yang gagal).\n";
    exit(0);
}

$stats = marketplace_order_notify_dispatch($db, $limit, $retry);
echo "\nHasil: " . json_encode($stats) . "\n";
