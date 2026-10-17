<?php
/**
 * V17.0.22 — Selaraskan ONGKIR & penjualan online ke laporan akuntansi.
 *
 * Menjalankan 2 langkah yang aman diulang:
 *   1) Catat order online yang SUDAH LUNAS & SELESAI tapi belum masuk tabel `transactions`
 *      (memakai fungsi resmi aplikasi: marketplace_record_pos_transaction()).
 *   2) Isi `transactions.shipping_cost` untuk transaksi online yang tercatat dengan ongkir 0
 *      padahal ordernya punya ongkir (hanya menambah, tidak pernah mengurangi).
 *
 * Jalankan dari docroot:  php tools/backfill_online_ongkir.php
 * Hapus setelah selesai.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$bootstrap = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($bootstrap as $b) { if (is_file($b)) { require_once $b; $loaded = true; break; } }
if (!$loaded || !class_exists('Database')) { exit("bootstrap aplikasi tidak ditemukan\n"); }
ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__);
foreach ([
    $root . '/legacy/app/Modules/Marketplace/Marketplace.php',
    $root . '/app/Modules/Marketplace/Marketplace.php',
] as $m) { if (is_file($m)) { require_once $m; echo 'require: ' . str_replace($root . '/', '', $m) . "\n"; break; } }

$db = Database::connect();
echo 'DB OK: ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

$agg = static function (PDO $db): array {
    return [
        'row'  => (int)$db->query("SELECT COUNT(*) FROM transactions WHERE reference_type='online_order'")->fetchColumn(),
        'ongkir' => (float)$db->query("SELECT COALESCE(SUM(shipping_cost),0) FROM transactions WHERE reference_type='online_order'")->fetchColumn(),
        'all_ongkir' => (float)$db->query("SELECT COALESCE(SUM(shipping_cost),0) FROM transactions WHERE status IN ('success','refunded')")->fetchColumn(),
    ];
};

$before = $agg($db);
echo "--- SEBELUM ---\n";
echo 'transaksi online di tabel transactions : ' . $before['row'] . "\n";
echo 'ongkir tercatat (transaksi online)     : Rp ' . number_format($before['ongkir'], 0, ',', '.') . "\n";
echo 'ongkir di laporan akuntansi            : Rp ' . number_format($before['all_ongkir'], 0, ',', '.') . "\n\n";

// ---------- 1. catat order online lunas & selesai yang belum masuk ----------
echo "--- 1) CATAT ORDER ONLINE (lunas + selesai, belum tercatat) ---\n";
if (!function_exists('marketplace_record_pos_transaction')) {
    echo "PERINGATAN: fungsi marketplace_record_pos_transaction() tidak tersedia — langkah 1 dilewati.\n";
} else {
    $cand = $db->query("SELECT id, order_number, shipping_cost, grand_total FROM online_orders
                        WHERE payment_status='paid' AND order_status='completed' AND pos_recorded_at IS NULL
                        ORDER BY id ASC LIMIT 200")->fetchAll() ?: [];
    echo 'kandidat: ' . count($cand) . " order\n";
    $ok = 0; $gagal = 0;
    foreach ($cand as $c) {
        try {
            $res = marketplace_record_pos_transaction($db, (int)$c['id']);
            if ($res !== null) {
                $ok++;
                echo '  OK  order #' . $c['id'] . ' ' . $c['order_number'] . ' -> transaksi (total Rp '
                    . number_format((float)$c['grand_total'], 0, ',', '.') . ', ongkir Rp '
                    . number_format((float)$c['shipping_cost'], 0, ',', '.') . ")\n";
            } else {
                echo '  SKIP order #' . $c['id'] . ' (sudah tercatat / ditolak fungsi)' . "\n";
            }
        } catch (Throwable $e) {
            $gagal++;
            echo '  GAGAL order #' . $c['id'] . ': ' . $e->getMessage() . "\n";
        }
    }
    echo "hasil: {$ok} dicatat, {$gagal} gagal\n";
}

// ---------- 2. isi ongkir pada transaksi online yang masih 0 ----------
echo "\n--- 2) ISI ONGKIR PADA TRANSAKSI ONLINE YANG MASIH 0 ---\n";
$rows = $db->query("SELECT t.id, t.invoice_number, t.shipping_cost, o.id AS order_id, o.order_number, o.shipping_cost AS ongkir_order
                    FROM transactions t JOIN online_orders o ON o.id = t.reference_id
                    WHERE t.reference_type='online_order' AND COALESCE(t.shipping_cost,0) = 0 AND COALESCE(o.shipping_cost,0) > 0
                    LIMIT 500")->fetchAll() ?: [];
echo 'kandidat: ' . count($rows) . " transaksi\n";
$fixed = 0;
foreach ($rows as $r) {
    $upd = $db->prepare('UPDATE transactions SET shipping_cost=? WHERE id=? AND COALESCE(shipping_cost,0)=0');
    $upd->execute([(float)$r['ongkir_order'], (int)$r['id']]);
    if ($upd->rowCount() > 0) {
        $fixed++;
        echo '  OK  ' . $r['invoice_number'] . ' (order ' . $r['order_number'] . ') ongkir 0 -> Rp '
            . number_format((float)$r['ongkir_order'], 0, ',', '.') . "\n";
    }
}
echo "hasil: {$fixed} transaksi diperbarui\n";

$after = $agg($db);
echo "\n--- SESUDAH ---\n";
echo 'transaksi online di tabel transactions : ' . $after['row'] . "\n";
echo 'ongkir tercatat (transaksi online)     : Rp ' . number_format($after['ongkir'], 0, ',', '.') . "\n";
echo 'ongkir di laporan akuntansi            : Rp ' . number_format($after['all_ongkir'], 0, ',', '.') . "\n";
echo "\nBuka /admin/accounting/index.php -> kartu 'Pendapatan Ongkir' harus menampilkan angka di atas.\n";
echo "Cek juga halaman penjualan online: /admin/marketplace-orders/index.php (order lunas+selesai otomatis dicatat saat halaman dibuka).\n";
echo "SELESAI — hapus file ini: rm -f tools/backfill_online_ongkir.php\n";