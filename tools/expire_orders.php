<?php
/**
 * V17.0.40 — Batalkan otomatis pesanan kedaluwarsa:
 *   1) QRIS / Virtual Account yang belum dibayar melewati batas (default 30 menit)
 *   2) Ambil di Toko yang melewati batas pengambilan
 *
 * Keduanya mengembalikan stok (cabang/varian + master), voucher, dan poin.
 *
 *   php tools/expire_orders.php                 → dry-run (lihat saja)
 *   php tools/expire_orders.php --apply         → batalkan + kembalikan stok
 *   php tools/expire_orders.php --apply --limit=100
 *
 * Cron tiap 5 menit:
 *   *\/5 * * * * /usr/local/bin/php /home/USER/public_html/tools/expire_orders.php --apply >> /home/USER/expire_orders.log 2>&1
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
$timeout = function_exists('marketplace_payment_timeout_minutes') ? marketplace_payment_timeout_minutes() : 30;

$sql = "SELECT id, order_number, payment_method, grand_total, created_at, payment_expired_at, courier_code, pickup_deadline_at, order_status, payment_status
        FROM online_orders
        WHERE payment_status='pending'
          AND (
                (courier_code <> 'pickup' OR courier_code IS NULL)
                AND COALESCE(payment_expired_at, created_at + INTERVAL " . (int) $timeout . " MINUTE) <= NOW()
              )
        ORDER BY created_at ASC
        LIMIT " . (int) $limit;
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

printf("Mode: %s | batas bayar: %d menit | kandidat: %d\n", $apply ? 'APPLY' : 'DRY-RUN', $timeout, count($rows));
printf("%-26s %-10s %-14s %-20s %s\n", 'No Pesanan', 'Metode', 'Total', 'Batas Bayar', 'Status');

$done = 0;
$failed = 0;
foreach ($rows as $o) {
    $batas = $o['payment_expired_at'] ?: '(kosong → created_at + ' . $timeout . 'm)';
    printf("%-26s %-10s %-14s %-20s %s\n", (string) $o['order_number'], (string) $o['payment_method'], number_format((float) $o['grand_total'], 0, ',', '.'), (string) $batas, (string) $o['order_status']);
    if (!$apply) {
        continue;
    }
    try {
        if (function_exists('marketplace_expire_if_needed')) {
            $after = marketplace_expire_if_needed($db, $o);
            if ((string) $after['payment_status'] === 'expired') {
                $done++;
            }
        }
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, 'GAGAL ' . $o['order_number'] . ': ' . $e->getMessage() . "\n");
    }
}

// Bagian 2: batas ambil di toko
if (function_exists('marketplace_pickup_expire_if_needed')) {
    $pick = $db->query("SELECT * FROM online_orders
        WHERE courier_code='pickup' AND pickup_deadline_at IS NOT NULL AND pickup_deadline_at < NOW()
          AND stock_released_at IS NULL AND payment_status IN ('pending','unpaid','error')
          AND order_status IN ('pending_payment','pending','confirmed','processing')
        LIMIT " . (int) $limit)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    printf("\nBatas ambil lewat: %d pesanan\n", count($pick));
    foreach ($pick as $o) {
        printf("%-26s batas ambil %s\n", (string) $o['order_number'], (string) $o['pickup_deadline_at']);
        if (!$apply) {
            continue;
        }
        try {
            $after = marketplace_pickup_expire_if_needed($db, $o);
            if ((string) $after['order_status'] === 'expired') {
                $done++;
            }
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, 'GAGAL ' . $o['order_number'] . ': ' . $e->getMessage() . "\n");
        }
    }
}

if ($apply) {
    printf("\nDibatalkan: %d | gagal: %d\n", $done, $failed);
} else {
    echo "\nIni DRY-RUN. Jalankan ulang dengan --apply untuk membatalkan & mengembalikan stok.\n";
}
