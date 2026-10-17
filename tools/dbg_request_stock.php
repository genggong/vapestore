<?php
/**
 * V17.0.20 — Diagnostik "Buat Request Stock": kenapa daftar produk kosong.
 * Jalankan dari docroot:  php tools/dbg_request_stock.php
 * Aman: hanya SELECT + COUNT (tanpa INSERT/UPDATE/ALTER). Hapus setelah selesai.
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
ini_set('display_errors', '1');   // bootstrap mematikan display_errors saat debug off
error_reporting(E_ALL);

$root = dirname(__DIR__);
$pick = static function (array $candidates): ?string {
    foreach ($candidates as $c) { if (is_file($c)) return $c; }
    return null;
};
$helpers = array_filter([
    $pick([$root . '/legacy/public/admin/warehouse/_helpers.php', $root . '/public/admin/warehouse/_helpers.php']),
    $pick([$root . '/legacy/public/admin/branches/_helpers.php', $root . '/public/admin/branches/_helpers.php']),
    $pick([$root . '/legacy/app/Modules/Marketplace/Marketplace.php', $root . '/app/Modules/Marketplace/Marketplace.php']),
    $pick([$root . '/legacy/app/Modules/CatalogFast.php', $root . '/app/Modules/CatalogFast.php']),
]);
foreach ($helpers as $f) {
    require_once $f;
    echo 'require: ' . str_replace($root . '/', '', $f) . "\n";
}

$db = Database::connect();
echo 'DB        : ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo 'PHP       : ' . PHP_VERSION . "\n";
echo 'DOCROOT   : ' . $root . "\n\n";

$count = static function (PDO $db, string $sql) {
    try { return (int)$db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 'ERR: ' . $e->getMessage(); }
};

echo "--- TABEL SUMBER ---\n";
echo 'products (semua)                 : ' . $count($db, 'SELECT COUNT(*) FROM products') . "\n";
echo 'products is_active=1             : ' . $count($db, 'SELECT COUNT(*) FROM products WHERE is_active=1') . "\n";
echo 'products is_active=0 / NULL      : ' . $count($db, 'SELECT COUNT(*) FROM products WHERE is_active<>1 OR is_active IS NULL') . "\n";
echo 'warehouses                       : ' . $count($db, 'SELECT COUNT(*) FROM warehouses') . "\n";
echo 'warehouse_stocks (semua baris)   : ' . $count($db, 'SELECT COUNT(*) FROM warehouse_stocks') . "\n";
echo 'marketplace_product_variants aktif: ' . $count($db, "SELECT COUNT(*) FROM marketplace_product_variants WHERE is_active=1") . "\n";
echo 'branches                         : ' . $count($db, 'SELECT COUNT(*) FROM branches') . "\n\n";

echo "--- HELPER HALAMAN ---\n";
$warehouseId = function_exists('warehouse_main_id') ? (int)warehouse_main_id($db) : 0;
$branchId = function_exists('branch_current_id') ? (int)branch_current_id($db) : 0;
echo 'warehouse_main_id()              : ' . $warehouseId . "\n";
echo 'branch_current_id()              : ' . $branchId . "\n";
echo 'warehouse_stocks utk gudang itu  : ' . ($warehouseId ? $count($db, 'SELECT COUNT(*) FROM warehouse_stocks WHERE warehouse_id=' . $warehouseId) : 'n/a') . "\n";
try {
    $r = $db->query('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("warehouse_main_id","default_warehouse_id","current_branch_id")')->fetchAll();
    foreach ($r as $row) { echo 'setting ' . $row['setting_key'] . ' : ' . $row['setting_value'] . "\n"; }
} catch (Throwable $e) { echo "setting warehouse: (tabel settings tidak terbaca)\n"; }

echo "\n--- QUERY PERSIS SEPERTI DI HALAMAN (baris 86) ---\n";
$sql = "SELECT p.id,p.name,p.product_code,p.barcode,p.unit,c.name category_name,COALESCE(ws.stock,0) warehouse_stock,
               COALESCE(NULLIF(ws.purchase_price,0),p.purchase_price,0) warehouse_cost,p.purchase_price
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN warehouse_stocks ws ON ws.product_id=p.id AND ws.warehouse_id=?
        WHERE p.is_active=1 ORDER BY p.name ASC";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$warehouseId]);
    $rows = $stmt->fetchAll();
    echo 'baris hasil query               : ' . count($rows) . "\n";
    if ($rows) {
        echo "5 produk pertama:\n";
        foreach (array_slice($rows, 0, 5) as $row) {
            echo '   - ' . $row['name'] . ' | stok gudang ' . $row['warehouse_stock'] . ' | Rp ' . $row['warehouse_cost'] . "\n";
        }
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        if (function_exists('catalog_fast_variants_by_product')) {
            $vars = catalog_fast_variants_by_product($db, $ids, $branchId, true);
            $withVar = 0; $totalVar = 0;
            foreach ($vars as $v) { if ($v) { $withVar++; $totalVar += count($v); } }
            echo 'produk yg punya varian aktif    : ' . $withVar . ' (total ' . $totalVar . " varian)\n";
            echo '=> jumlah grup & baris yg HARUSNYA tampil: ' . (count($rows)) . ' grup / ' . (count($rows) - $withVar + $totalVar) . " baris\n";
        } else {
            echo "catalog_fast_variants_by_product TIDAK ADA — CatalogFast.php belum ter-load!\n";
        }
    }
} catch (Throwable $e) {
    echo 'GAGAL: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

echo "\n--- FILE TERPASANG ---\n";
foreach ([
    'legacy/public/admin/stock-requests/create.php',
    'legacy/app/Modules/CatalogFast.php',
    'assets/js/stock-request.js',
    'assets/css/warehouse.css',
    'VERSION',
] as $rel) {
    $p = $root . '/' . $rel;
    printf("  %-48s %s\n", $rel, is_file($p) ? filesize($p) . ' byte, md5 ' . substr(md5_file($p), 0, 8) : 'TIDAK ADA');
}
$create = $root . '/legacy/public/admin/stock-requests/create.php';
if (is_file($create)) {
    $src = (string)file_get_contents($create);
    echo '  create.php punya marker data-product-group: ' . (str_contains($src, 'data-product-group') ? 'YA' : 'TIDAK') . "\n";
    echo '  create.php punya CatalogFast require      : ' . (str_contains($src, 'CatalogFast.php') ? 'YA' : 'TIDAK') . "\n";
}
echo "\nSELESAI — hapus file ini: rm -f tools/dbg_request_stock.php\n";