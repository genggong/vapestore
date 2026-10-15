<?php
/**
 * V17.0.16 — Debug kecepatan laporan Akuntansi (CLI only).
 * Jalankan di hosting:
 *   cd ~/public_html && php tools/dbg_accounting_speed.php
 * Output: waktu tiap query + total accounting_build_report.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$candidates = [
    __DIR__ . '/../app/bootstrap.php',
    getcwd() . '/../app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    __DIR__ . '/app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/../legacy/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (file_exists($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded) { fwrite(STDERR, "bootstrap.php tidak ditemukan. Jalankan dari docroot: cd ~/public_html && php tools/dbg_accounting_speed.php\n"); exit(1); }
// Bootstrap menonaktifkan display_errors jika debug=false — aktifkan lagi utk tool ini.
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Muat module dari tree yang live dipakai halaman admin (root app/ di hosting).
$accModule = __DIR__ . '/../app/Modules/Accounting/Accounting.php';
if (!is_file($accModule)) $accModule = __DIR__ . '/../legacy/app/Modules/Accounting/Accounting.php';
require_once $accModule;
$branchHelpers = __DIR__ . '/../public/admin/branches/_helpers.php';
if (!is_file($branchHelpers)) $branchHelpers = __DIR__ . '/../legacy/public/admin/branches/_helpers.php';
require_once $branchHelpers;

$db = Database::connect();
echo "DB OK: " . $db->query("SELECT DATABASE()")->fetchColumn() . "\n";

function bench(PDO $db, string $label, string $sql): void
{
    $t = microtime(true);
    try {
        $n = $db->query($sql)->fetchColumn();
        printf("%-52s %.3fs  -> %s\n", $label, microtime(true) - $t, var_export($n, true));
    } catch (Throwable $e) {
        printf("%-52s ERROR: %s\n", $label, $e->getMessage());
    }
}

echo "== Volume data ==\n";
bench($db, 'COUNT transactions', 'SELECT COUNT(*) FROM transactions');
bench($db, 'COUNT transaction_items', 'SELECT COUNT(*) FROM transaction_items');
bench($db, 'COUNT products', 'SELECT COUNT(*) FROM products');
bench($db, 'COUNT product_branch_stocks', 'SELECT COUNT(*) FROM product_branch_stocks');
bench($db, 'COUNT marketplace_product_variants', 'SELECT COUNT(*) FROM marketplace_product_variants');
bench($db, 'COUNT marketplace_product_variant_stocks', 'SELECT COUNT(*) FROM marketplace_product_variant_stocks');
bench($db, 'COUNT finance_transactions', 'SELECT COUNT(*) FROM finance_transactions');

echo "\n== Query baru v17.0.16 (nilai persediaan) ==\n";
bench($db, 'inv_query_1 (induk, hb=0 OR non-varian)',
    "SELECT COALESCE(SUM(GREATEST(0,COALESCE(pbs.stock,0))*COALESCE(p.purchase_price,0)),0)
     FROM product_branch_stocks pbs
     JOIN products p ON p.id=pbs.product_id
     LEFT JOIN (SELECT product_id, COUNT(*) c FROM marketplace_product_variants WHERE is_active=1 GROUP BY product_id) vx ON vx.product_id=p.id
     WHERE COALESCE(vx.c,0)=0 OR COALESCE(p.purchase_price,0)>0");
bench($db, 'inv_query_2 (varian induk hb=0)',
    "SELECT COALESCE(SUM(GREATEST(0,COALESCE(mvs.stock,0))*COALESCE(mv.purchase_price,0)),0)
     FROM marketplace_product_variant_stocks mvs
     JOIN marketplace_product_variants mv ON mv.id=mvs.variant_id AND mv.is_active=1
     JOIN products p ON p.id=mv.product_id AND COALESCE(p.purchase_price,0)<=0
     WHERE mvs.stock>0");

echo "\n== accounting_build_report (bulan ini, semua cabang) ==\n";
$t = microtime(true);
$report = accounting_build_report($db, date('Y-m-01'), date('Y-m-d'), 0);
printf("BUILD_REPORT total: %.3fs (branches=%d daily=%d)\n", microtime(true) - $t, count($report['branches']), count($report['daily']));
echo "Nilai persediaan (summary): Rp " . number_format((float)$report['summary']['inventory_value'], 0, ',', '.') . "\n";
echo "Pendapatan ongkir (summary): Rp " . number_format((float)$report['summary']['shipping_income'], 0, ',', '.') . "\n";
echo "DONE\n";