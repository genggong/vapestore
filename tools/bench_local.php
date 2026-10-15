<?php
/**
 * Benchmark lokal query Akuntansi + Dashboard (DB vapekong_pos_test).
 * Memuat modul asli dari tree legacy & menjalankan accounting_build_report + dashboard_stock_value.
 * Jalankan: php tools/bench_local.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = new PDO(
    'mysql:host=localhost;port=3306;dbname=vapekong_pos_test;charset=utf8mb4',
    'vk_test2', 'VkTest2!Pass2026',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$db->exec('SET SESSION sql_mode = ""');

require_once __DIR__ . '/../legacy/app/Modules/Accounting/Accounting.php';

if (!function_exists('setting')) {
    function setting(string $k, $d = null) { return $d; }
}

// Salinan persis query dashboard_stock_value v2 (tanpa memuat file halaman yg auto-connect DB)
function dashboard_stock_value_local(PDO $db): float {
    $v = 0.0;
    try {
        $v += (float)$db->query(
            "SELECT COALESCE(SUM(GREATEST(0,COALESCE(p.stock,0))*COALESCE(p.purchase_price,0)),0)
             FROM products p
             WHERE p.purchase_price > 0"
        )->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $v += (float)$db->query(
            "SELECT COALESCE(SUM(GREATEST(0,COALESCE(mvs.stock,0))*COALESCE(mv.purchase_price,0)),0)
             FROM marketplace_product_variant_stocks mvs
             INNER JOIN marketplace_product_variants mv ON mv.id=mvs.variant_id AND mv.is_active=1
             INNER JOIN products p ON p.id=mv.product_id AND p.purchase_price <= 0
             WHERE mvs.stock > 0"
        )->fetchColumn();
    } catch (Throwable $e) {}
    return $v;
}

function bench_local(string $label, callable $fn) {
    $t = microtime(true);
    $r = $fn();
    printf("%-46s %8.3fs\n", $label, microtime(true) - $t);
    return $r;
}

echo "== angka DB test ==\n";
echo "products: " . $db->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
echo "variants: " . $db->query("SELECT COUNT(*) FROM marketplace_product_variants")->fetchColumn() . "\n";
echo "variant_stocks: " . $db->query("SELECT COUNT(*) FROM marketplace_product_variant_stocks")->fetchColumn() . "\n";
echo "pbs: " . $db->query("SELECT COUNT(*) FROM product_branch_stocks")->fetchColumn() . "\n";
echo "transactions: " . $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn() . "\n";
echo "transaction_items: " . $db->query("SELECT COUNT(*) FROM transaction_items")->fetchColumn() . "\n\n";

echo "== query dashboard_stock_value (v2 ringkas) ==\n";
$v = bench_local('dashboard_stock_value() v2', fn() => dashboard_stock_value_local($db));
echo "Nilai: " . number_format($v, 0, ',', '.') . "\n\n";

echo "== accounting_build_report (1 bulan, semua cabang) ==\n";
$rep = bench_local('build_report', fn() => accounting_build_report($db, '2026-10-01', '2026-10-15', 0));
echo "inventory_value: " . number_format((float)$rep['summary']['inventory_value'], 0, ',', '.') . "\n";
echo "shipping_income: " . number_format((float)$rep['summary']['shipping_income'], 0, ',', '.') . "\n";
echo "gross_sales: " . number_format((float)$rep['summary']['gross_sales'], 0, ',', '.') . "\n\n";

echo "== EXPLAIN query inventory v2 (pastikan pakai index) ==\n";
foreach (["SELECT pbs.branch_id, SUM(pbs.stock*p.purchase_price) FROM product_branch_stocks pbs INNER JOIN products p ON p.id=pbs.product_id WHERE p.purchase_price > 0 GROUP BY pbs.branch_id",
          "SELECT mvs.branch_id, SUM(mvs.stock*mv.purchase_price) FROM marketplace_product_variant_stocks mvs INNER JOIN marketplace_product_variants mv ON mv.id=mvs.variant_id AND mv.is_active=1 INNER JOIN products p ON p.id=mv.product_id AND p.purchase_price <= 0 WHERE mvs.stock > 0 GROUP BY mvs.branch_id"] as $i => $sql) {
    echo "--- query " . ($i + 1) . " ---\n";
    foreach ($db->query("EXPLAIN " . $sql)->fetchAll() as $row) {
        printf("  type=%-8s table=%-12s key=%-25s rows=%s extra=%s\n", $row['type'], $row['table'], (string)($row['key'] ?? '-'), $row['rows'], (string)($row['Extra'] ?? ''));
    }
}
echo "\n== EXPLAIN query transaksi (status_date index) ==\n";
$sql = "SELECT COUNT(*) FROM transactions t WHERE t.status IN ('success','refunded') AND t.transaction_date >= '2026-10-01' AND t.transaction_date < DATE_ADD('2026-10-15', INTERVAL 1 DAY)";
foreach ($db->query("EXPLAIN " . $sql)->fetchAll() as $row) {
    printf("  type=%-8s table=%-12s key=%-25s rows=%s extra=%s\n", $row['type'], $row['table'], (string)($row['key'] ?? '-'), $row['rows'], (string)($row['Extra'] ?? ''));
}
echo "DONE\n";