<?php
/**
 * V17.0.38 — Batalkan otomatis pesanan "Ambil di Toko" yang lewat batas pengambilan.
 *
 * Default DRY-RUN (tidak mengubah apa pun).
 *   php tools/expire_pickup_orders.php                 → lihat daftar saja
 *   php tools/expire_pickup_orders.php --apply         → batalkan + kembalikan stok
 *   php tools/expire_pickup_orders.php --apply --limit=50
 *
 * Hanya menyentuh pesanan BELUM DIBAYAR (pending/unpaid/error) yang masih aktif.
 * Pesanan yang sudah dibayar / sudah diambil TIDAK disentuh.
 *
 * Cron harian (5 menit sekali) di cPanel:
 *   * /5 * * * * /usr/local/bin/php /home/USER/public_html/tools/expire_pickup_orders.php --apply >> /home/USER/pickup_expire.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$args = array_slice($argv ?? [], 1);
$apply = in_array('--apply', $args, true);
$limit = 200;
foreach ($args as $a) {
    if (strpos($a, '--limit=') === 0) {
        $limit = max(1, (int) substr($a, 8));
    }
}

$candidates = [
    __DIR__ . '/../legacy/public/store/_data.php',
    __DIR__ . '/../public/store/_data.php',
    __DIR__ . '/../store/_data.php',
];
$booted = false;
foreach ($candidates as $file) {
    if (is_file($file)) {
        require_once $file;
        $booted = true;
        break;
    }
}
if (!$booted) {
    fwrite(STDERR, "Tidak menemukan store/_data.php di docroot ini.\n");
    exit(1);
}

$db = storefront_db();
if (!$db) {
    fwrite(STDERR, "Koneksi database gagal.\n");
    exit(1);
}
if (function_exists('marketplace_install')) {
    marketplace_install($db);
}
if (function_exists('marketplace_store_pickup_install')) {
    marketplace_store_pickup_install($db);
}

$sql = "SELECT * FROM online_orders
        WHERE courier_code='pickup'
          AND pickup_deadline_at IS NOT NULL
          AND pickup_deadline_at < NOW()
          AND stock_released_at IS NULL
          AND payment_status IN ('pending','unpaid','error')
          AND order_status IN ('pending_payment','pending','confirmed','processing')
        ORDER BY pickup_deadline_at ASC
        LIMIT " . (int) $limit;
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

printf("Mode: %s | kandidat: %d\n", $apply ? 'APPLY' : 'DRY-RUN', count($rows));
printf("%-26s %-20s %-12s %s\n", 'No Pesanan', 'Batas Ambil', 'Total', 'Status');

$done = 0;
$failed = 0;
foreach ($rows as $o) {
    printf("%-26s %-20s %-12s %s\n", (string) $o['order_number'], (string) $o['pickup_deadline_at'], number_format((float) $o['grand_total'], 0, ',', '.'), (string) $o['order_status']);
    if (!$apply) {
        continue;
    }
    try {
        $before = (string) $o['order_status'];
        $up = $db->prepare("UPDATE online_orders SET order_status='expired', payment_status=CASE WHEN payment_status IN ('pending','unpaid','error') THEN 'expired' ELSE payment_status END, cancelled_at=COALESCE(cancelled_at,NOW()), updated_at=NOW() WHERE id=? AND order_status=?");
        $up->execute([(int) $o['id'], $before]);
        if ($up->rowCount() > 0) {
            marketplace_release_stock($db, (int) $o['id'], 'Batas pengambilan di toko terlewat (cron).');
            marketplace_add_event($db, (int) $o['id'], 'expired', 'Batas ambil terlewat', 'Dibatalkan otomatis oleh cron; stok dikembalikan.');
            $done++;
        }
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, 'GAGAL ' . $o['order_number'] . ': ' . $e->getMessage() . "\n");
    }
}

if ($apply) {
    printf("\nDibatalkan: %d | gagal: %d\n", $done, $failed);
} else {
    echo "\nIni DRY-RUN. Jalankan ulang dengan --apply untuk membatalkan & mengembalikan stok.\n";
}
