<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'Laporan Stok';
$db = Database::connect();

$rows = $db->query("
    SELECT p.*, c.name AS category_name,
           COALESCE(vx.variant_count,0) AS variant_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN (SELECT product_id, COUNT(*) AS variant_count
               FROM marketplace_product_variants WHERE is_active=1
               GROUP BY product_id) vx ON vx.product_id = p.id
    ORDER BY p.stock ASC, p.name ASC
")->fetchAll();

// V17.0.11: nilai modal stok disamakan dgn template stock opname & export Excel —
// produk ber-varian yg harga beli INDUKNYA 0 (belum diisi) dihitung pakai harga
// beli varian (yg riil), sehingga tidak under-report (kasus: Dripstate hb 0
// tetapi variannya 112.500 x 2 = 225.000). Produk ber-varian dengan harga induk
// sudah terisi tetap memakai harga induk (konsisten dgn opname).
$stockValue = 0.0;
$totalStock = 0;
$rowCalc = [];   // id => ['stock'=>..,'price'=>..] utk konsistensi total & baris
foreach ($rows as $r) {
    $qty = (int)$r['stock'];
    $price = (float)$r['purchase_price'];
    if ((int)($r['variant_count'] ?? 0) > 0 && $price <= 0.0) {
        try {
            // Harga beli rata-rata varian aktif (stok>0); kalau >0, pakai itu
            $v = $db->prepare("SELECT COALESCE(SUM(mpvs.stock),0) AS stok,
                                      CASE WHEN SUM(mpvs.stock)>0 THEN COALESCE(SUM(mpvs.stock*mpv.purchase_price)/SUM(mpvs.stock),0) ELSE 0 END AS hb
                               FROM marketplace_product_variant_stocks mpvs
                               JOIN marketplace_product_variants mpv ON mpv.id=mpvs.variant_id
                               WHERE mpv.product_id=? AND mpv.is_active=1 AND mpvs.stock>0");
            $v->execute([(int)$r['id']]);
            $vr = $v->fetch();
            if ($vr && (int)$vr['stok'] > 0 && (float)$vr['hb'] > 0.0) { $qty = (int)$vr['stok']; $price = (float)$vr['hb']; }
        } catch (Throwable $ignore) {}
    }
    $rowCalc[(int)$r['id']] = ['stock' => max(0,(int)$r['stock']), 'price' => 0];
    // Baris ditampilkan tetap stok induk + harga terpakai; nilai = stok terpakai x harga terpakai
    $rowCalc[(int)$r['id']]['stock_used'] = $qty;
    $rowCalc[(int)$r['id']]['price_used'] = $price;
    $totalStock += $qty > (int)$r['stock'] ? (int)$r['stock'] : $qty;   // utk angka total stok, pakai stok induk jika varian dipakai utk nilai
    $stockValue += $qty * $price;
}
$lowStock = count(array_filter($rows, fn($r) => (int)$r['stock'] <= (int)$r['minimum_stock']));

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/reports.css?v=' . time()); ?>">

<main class="admin-main reports-wrap">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1>Laporan Stok</h1>
            <p>Pantau stok akhir, nilai modal stok, dan produk yang perlu restock.</p>
        </div>
        <div class="topbar-actions"><button onclick="window.print()" class="report-btn primary"><i class="bi bi-printer"></i> Print</button></div>
    </header>

    <section class="report-panel mt-4">
        <div class="report-grid">
            <div class="report-card"><div class="icon"><i class="bi bi-boxes"></i></div><span>Total Produk</span><h3><?= count($rows); ?></h3><p>Produk tercatat</p></div>
            <div class="report-card"><div class="icon"><i class="bi bi-stack"></i></div><span>Total Stok</span><h3><?= e($totalStock); ?></h3><p>Semua satuan</p></div>
            <div class="report-card"><div class="icon"><i class="bi bi-wallet2"></i></div><span>Nilai Modal</span><h3><?= rupiah($stockValue); ?></h3><p>Stok x harga beli</p></div>
            <div class="report-card"><div class="icon"><i class="bi bi-exclamation-triangle"></i></div><span>Stok Menipis</span><h3><?= e($lowStock); ?></h3><p>Perlu restock</p></div>
        </div>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead><tr><th>Produk</th><th>Kategori</th><th>Stok</th><th>Minimum</th><th>Harga Beli</th><th>Nilai Modal</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $isLow = (int)$r['stock'] <= (int)$r['minimum_stock'];
                        $rc = $rowCalc[(int)$r['id']] ?? ['stock_used' => (int)$r['stock'], 'price_used' => (float)$r['purchase_price']];
                        $rcStock = (int)($rc['stock_used'] ?? (int)$r['stock']);
                        $rcPrice = (float)($rc['price_used'] ?? (float)$r['purchase_price']);
                        $rcValue = $rcStock * $rcPrice;
                    ?>
                        <tr>
                            <td><b><?= e($r['name']); ?></b><br><small><?= e($r['product_code']); ?><?= (int)($r['variant_count'] ?? 0) > 0 ? ' · ' . (int)$r['variant_count'] . ' varian' : ''; ?></small></td>
                            <td><?= e($r['category_name'] ?? '-'); ?></td>
                            <td><b><?= e($rcStock); ?> <?= e($r['unit']); ?></b></td>
                            <td><?= e($r['minimum_stock']); ?></td>
                            <td><?= rupiah($rcPrice); ?></td>
                            <td><b><?= rupiah($rcValue); ?></b></td>
                            <td><span class="report-badge <?= $isLow ? 'red' : 'green'; ?>"><?= $isLow ? 'Restock' : 'Aman'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="7" class="report-empty">Belum ada produk.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
