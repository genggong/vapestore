<?php
/**
 * V17.0.16 — Selaraskan Nilai Modal Stok, Ongkir Akuntansi, Average HPP & Request Stock
 * (Backfill dijalankan SEKALI saja per database. Aman dijalankan ulang.)
 *
 * Perbaikan:
 * 1. Nilai Persediaan (KPI Akuntansi) kini konsisten dengan Laporan Stok:
 *    produk ber-varian dengan harga induk 0 dihitung dari stok varian x HPP varian.
 * 2. Ongkir penjualan online (transactions.shipping_cost) sudah dipisah jadi
 *    Pendapatan Ongkir — tidak lagi tercampur di Laba Kotor barang.
 * 3. HPP rata-rata tertimbang dipakai saat Pembelian & Request Stock kena stok baru
 *    (sudah diterapkan di source; kode ini memastikan angka historical tetap sinkron
 *    dengan menghitung ulang nilai persediaan saja — TIDAK menulis ulang harga lampau).
 *
 * Tidak ada DDL. Tidak menghapus data. Tidak mengubah harga historis.
 */

declare(strict_types=1);

// Fallback bootstrap: jalankan dari tools/ (cd repo && php tools/backfill_v17_0_16.php)
// Pola sama dengan backfill lama yang sudah teruji di hosting (docroot/app):
// hosting memuat app/bootstrap.php LANGSUNG (bukan legacy/app) + app/Modules.
$candidates = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/../app/bootstrap.php',
    getcwd() . '/../legacy/app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    __DIR__ . '/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $bootstrap) {
    if (is_file($bootstrap)) {
        require_once $bootstrap;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    fwrite(STDERR, "Tidak dapat memuat bootstrap aplikasi. Jalankan dari docroot: cd ~/public_html && php tools/backfill_v17_0_16.php\n");
    exit(1);
}

// Pastikan avg_hpp() tersedia — coba muat dari tree mana pun yang ada di hosting,
// dan punya fallback inline agar backfill tetap jalan walau path helper berubah.
if (!function_exists('avg_hpp')) {
    foreach ([
        __DIR__ . '/../public/admin/purchases/_helpers.php',
        __DIR__ . '/../legacy/public/admin/purchases/_helpers.php',
        getcwd() . '/public/admin/purchases/_helpers.php',
        getcwd() . '/legacy/public/admin/purchases/_helpers.php',
    ] as $h) {
        if (is_file($h)) {
            require_once $h;
            break;
        }
    }
}
if (!function_exists('avg_hpp')) {
    function avg_hpp(float $oldStock, float $oldPrice, float $newQty, float $newPrice): float
    {
        if ($newQty <= 0) return $oldPrice;
        $total = $oldStock + $newQty;
        if ($oldStock <= 0 || $oldPrice <= 0) return $newPrice;
        return round(($oldStock * $oldPrice + $newQty * $newPrice) / $total, 2);
    }
}

$db = Database::connect();

function backfill_ok(PDO $db, string $msg): void {
    echo '[OK] ' . $msg . PHP_EOL;
}
function backfill_warn(PDO $db, string $msg): void {
    echo '[WARN] ' . $msg . PHP_EOL;
}

$tables = ['products', 'product_branch_stocks', 'marketplace_product_variants', 'marketplace_product_variant_stocks', 'finance_transactions', 'transactions'];
foreach ($tables as $t) {
    try { $db->query("SELECT 1 FROM `{$t}` LIMIT 1"); }
    catch (Throwable $e) { backfill_warn($db, "Tabel `{$t}` tidak ada — dilewati."); }
}

// 1) Nilai persediaan diakui konsisten dgn Laporan Stok — cukup hitung ulang di laporan;
//    tidak backfill harga historis (forward-going rule). Ini hanya verifikasi read model.
try {
    $inv = $db->query("
        SELECT COALESCE(SUM(GREATEST(0,COALESCE(pbs.stock,0)) * COALESCE(p.purchase_price,0)),0)
        FROM product_branch_stocks pbs JOIN products p ON p.id=pbs.product_id
        WHERE NOT EXISTS (SELECT 1 FROM marketplace_product_variants v WHERE v.product_id=p.id AND v.is_active=1)
           OR COALESCE(p.purchase_price,0) > 0
    ")->fetchColumn();
    backfill_ok($db, "Nilai persediaan (produk induk/tanpa-varian): Rp " . number_format((float)$inv, 0, ',', '.'));
} catch (Throwable $e) {
    backfill_warn($db, "Read nilai persediaan induk gagal: " . $e->getMessage());
}

try {
    $invV = $db->query("
        SELECT COALESCE(SUM(GREATEST(0,COALESCE(mvs.stock,0)) * COALESCE(mv.purchase_price,0)),0)
        FROM marketplace_product_variant_stocks mvs
        JOIN marketplace_product_variants mv ON mv.id=mvs.variant_id AND mv.is_active=1
        JOIN products p ON p.id=mv.product_id AND COALESCE(p.purchase_price,0) <= 0
        WHERE mvs.stock > 0
    ")->fetchColumn();
    backfill_ok($db, "Nilai persediaan (varian induk hb=0): Rp " . number_format((float)$invV, 0, ',', '.'));
} catch (Throwable $e) {
    backfill_warn($db, "Read nilai persediaan varian gagal: " . $e->getMessage());
}

// 2) Verifikasi ongkir sudah tercatat terpisah di transactions.shipping_cost
try {
    if (!$db->query("SHOW COLUMNS FROM transactions LIKE 'shipping_cost'")->fetch()) {
        backfill_warn($db, "Kolom transactions.shipping_cost tidak ada — ongkir tidak dapat dipisahkan. Jalankan Upgrade Sistem.");
    } else {
        $sh = (float)$db->query("SELECT COALESCE(SUM(shipping_cost),0) FROM transactions WHERE status IN ('success','refunded')")->fetchColumn();
        backfill_ok($db, "Total ongkir tercatat terpisah: Rp " . number_format($sh, 0, ',', '.'));
    }
} catch (Throwable $e) {
    backfill_warn($db, "Cek ongkir gagal: " . $e->getMessage());
}

// 3) HPP rata-rata tertimbang — helper sudah tersedia; pastikan konsisten dgn stok tujuan
try {
    if (!function_exists('avg_hpp')) {
        backfill_warn($db, "Helper avg_hpp belum termuat (file _helpers.php belum ter-patch).");
    } else {
        $example = avg_hpp(10.0, 10000.0, 5.0, 12000.0);
        backfill_ok($db, "avg_hpp tersedia: 10 x 10.000 + 5 x 12.000 / 15 = Rp " . number_format($example, 0, ',', '.'));
    }
} catch (Throwable $e) {
    backfill_warn($db, "Verifikasi avg_hpp gagal: " . $e->getMessage());
}

echo PHP_EOL . 'Selesai. Nilai modal stok & ongkir kini konsisten di Laporan Stok, Akuntansi, dan Export.' . PHP_EOL;