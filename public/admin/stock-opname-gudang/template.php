<?php
/**
 * V17.0.43 — Template Excel Stok Opname GUDANG.
 * Sumber: warehouse_stocks (gudang utama). Produk stok 0 TIDAK dimasukkan.
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/DataExchange/Xlsx.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);

$db = Database::connect();
wopname_install($db);
$wh = wopname_warehouse($db);
$warehouseId = (int) $wh['id'];
$includeZero = !empty($_GET['include_zero']);

$st = $db->prepare("SELECT p.id,p.name,p.product_code,p.unit,p.purchase_price,c.name category_name,
        COALESCE(ws.stock,0) warehouse_stock
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    LEFT JOIN warehouse_stocks ws ON ws.product_id=p.id AND ws.warehouse_id=?
    WHERE p.is_active=1
    ORDER BY p.name ASC, p.id ASC");
$st->execute([$warehouseId]);
$products = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$data = [];
$skipped = 0;
foreach ($products as $p) {
    $stock = (int) $p['warehouse_stock'];
    if (!$includeZero && $stock <= 0) { $skipped++; continue; }
    $price = (float) $p['purchase_price'];
    $data[] = [
        'name' => (string) $p['name'],
        'category' => (string) ($p['category_name'] ?? ''),
        'unit' => (string) ($p['unit'] ?: 'pcs'),
        'system_stock' => $stock,
        'purchase_price' => $price,
        'total_purchase_price' => $stock * $price,
        'product_id' => (int) $p['id'],
        'code' => (string) $p['product_code'],
    ];
}

$headers = ['name', 'category', 'unit', 'system_stock', 'physical_stock', 'purchase_price', 'total_purchase_price', 'note', 'product_id', 'product_code'];
$rows = [
    [['value' => 'TEMPLATE STOK OPNAME GUDANG - ' . $wh['name'], 'style' => 1, 'height' => 28]],
    [['value' => 'Isi hanya kolom physical_stock (stok fisik). Produk stok 0 tidak dimasukkan. Kolom ID teknis (J/K) jangan dihapus.', 'style' => 5]],
    array_map(fn($v) => ['value' => $v, 'style' => 2], $headers),
];
foreach ($data as $r) {
    $rows[] = [
        ['value' => $r['name'], 'style' => 5],
        ['value' => $r['category'], 'style' => 5],
        ['value' => $r['unit'], 'style' => 10],
        ['value' => $r['system_stock'], 'type' => 'number', 'style' => 4],
        ['value' => '', 'style' => 4],
        ['value' => $r['purchase_price'], 'type' => 'number', 'style' => 4],
        ['value' => $r['total_purchase_price'], 'type' => 'number', 'style' => 4],
        ['value' => '', 'style' => 5],
        ['value' => $r['product_id'], 'type' => 'number', 'style' => 4],
        ['value' => $r['code'], 'style' => 5],
    ];
}

Auth::log('download_warehouse_opname_template', 'Template opname gudang: ' . count($data) . ' baris (stok 0 dilewati: ' . $skipped . ')');
KpXlsx::download('template-opname-gudang-' . date('Ymd-His') . '.xlsx', [[
    'name' => 'Opname Gudang',
    'rows' => $rows,
    'widths' => [44, 22, 10, 14, 14, 18, 20, 34, 12, 18],
    'hidden_columns' => ['I', 'J'],
    'freeze_rows' => 3,
    'auto_filter' => true,
    'filter_row' => 3,
    'merges' => ['A1:J1', 'A2:J2'],
]]);
