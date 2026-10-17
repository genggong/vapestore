<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/Marketplace.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/MarketplaceV16.php';
require_once __DIR__ . '/../../../app/Modules/CatalogFast.php';
require_once __DIR__ . '/../branches/_helpers.php';
require_once __DIR__ . '/_helpers.php';

warehouse_require_manage();
$pageTitle = 'Edit Stok';
$db = Database::connect();
branch_install($db);
warehouse_install($db);

$warehouses = warehouse_all($db, false);
$warehouseId = (int)($_GET['warehouse_id'] ?? $_POST['warehouse_id'] ?? 0);
if ($warehouseId <= 0 || !array_filter($warehouses, fn($w) => (int)$w['id'] === $warehouseId)) {
    $warehouseId = warehouse_main_id($db);
}
$warehouseLabel = warehouse_name($db, $warehouseId);

$mainBranchId = 0;
$mainBranchName = '';
try {
    $mb = $db->query("SELECT id, name FROM branches WHERE is_main=1 ORDER BY id ASC LIMIT 1")->fetch();
    if (!$mb) $mb = $db->query("SELECT id, name FROM branches ORDER BY id ASC LIMIT 1")->fetch();
    if ($mb) { $mainBranchId = (int)$mb['id']; $mainBranchName = (string)$mb['name']; }
} catch (Throwable $e) {}

/** Kunci baris: "pid" untuk produk, "pid-vid" untuk varian. */
$rowKey = static fn(int $pid, int $vid = 0): string => $vid > 0 ? $pid . '-' . $vid : (string)$pid;
$parseKey = static function (string $key): array {
    $parts = explode('-', $key, 2);
    return [(int)$parts[0], isset($parts[1]) ? (int)$parts[1] : 0];
};
$num = static function ($value): float {
    $value = str_replace([' ', '.'], '', (string)$value);
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float)$value : 0.0;
};

// ---------- SIMPAN ----------
if (is_post()) {
    if (!CSRF::verify()) {
        flash('error', 'Token keamanan tidak valid. Muat ulang halaman.');
        legacy_redirect('admin/warehouse/edit-stock.php?warehouse_id=' . $warehouseId);
    }

    $gudangStock = (array)($_POST['gudang_stock'] ?? []);
    $gudangMin   = (array)($_POST['gudang_min'] ?? []);
    $gudangPrice = (array)($_POST['gudang_price'] ?? []);
    $branchStock = (array)($_POST['branch_stock'] ?? []);
    $masterPrice = (array)($_POST['master_price'] ?? []);
    $note        = trim((string)($_POST['note'] ?? ''));

    $isFilled = static fn(array $arr, string $key): bool => array_key_exists($key, $arr) && trim((string)$arr[$key]) !== '';

    $keys = [];
    foreach ([$gudangStock, $gudangMin, $gudangPrice, $branchStock, $masterPrice] as $arr) {
        foreach ($arr as $k => $v) {
            if (trim((string)$v) !== '') $keys[(string)$k] = true;
        }
    }
    $keys = array_keys($keys);

    $changed = 0;
    $logLines = [];

    try {
        $db->beginTransaction();

        $selProduct = $db->prepare('SELECT id, name, stock, purchase_price, minimum_stock FROM products WHERE id=? LIMIT 1');
        $updProduct = $db->prepare('UPDATE products SET purchase_price=?, updated_at=NOW() WHERE id=?');
        $selVariant = $db->prepare('SELECT id, name, purchase_price FROM marketplace_product_variants WHERE id=? AND product_id=? LIMIT 1');
        $updVariant = $db->prepare('UPDATE marketplace_product_variants SET purchase_price=?, updated_at=NOW() WHERE id=? AND product_id=?');

        foreach ($keys as $key) {
            [$pid, $vid] = $parseKey((string)$key);
            if ($pid <= 0) continue;

            $selProduct->execute([$pid]);
            $product = $selProduct->fetch();
            if (!$product) continue;

            // ---- 1. stok & harga di GUDANG ----
            $newGudangStock = $isFilled($gudangStock, (string)$key) ? max(0, (int)$num($gudangStock[$key])) : null;
            $newGudangMin   = $isFilled($gudangMin, (string)$key)   ? max(0, (int)$num($gudangMin[$key]))   : null;
            $newGudangPrice = $isFilled($gudangPrice, (string)$key) ? max(0.0, $num($gudangPrice[$key]))    : null;

            if ($vid > 0) {
                $before = warehouse_variant_stock($db, $warehouseId, $pid, $vid, true);
                $curStock = (int)($before['stock'] ?? 0);
                $curMin   = (int)($before['minimum_stock'] ?? 0);
                $curPrice = (float)($before['purchase_price'] ?? 0);
                $stock = $newGudangStock ?? $curStock;
                $min   = $newGudangMin ?? $curMin;
                $price = $newGudangPrice ?? $curPrice;
                if ($stock !== $curStock || $min !== $curMin || $price !== $curPrice) {
                    warehouse_set_variant_stock($db, $warehouseId, $pid, $vid, $stock, $price, $min);
                    if ($stock !== $curStock) {
                        warehouse_record_movement($db, $warehouseId, $pid, 'edit_stock', abs($stock - $curStock), $curStock, $stock, $price, 'edit_stock', null, $note !== '' ? $note : 'Edit stok varian dari menu Gudang');
                    }
                    $changed++;
                    $logLines[] = 'varian #' . $vid . ' stok ' . $curStock . '→' . $stock . ' modal ' . $curPrice . '→' . $price;
                }
            } else {
                $before = warehouse_stock($db, $warehouseId, $pid, true);
                $curStock = (int)($before['stock'] ?? 0);
                $curMin   = (int)($before['minimum_stock'] ?? 0);
                $curPrice = (float)($before['purchase_price'] ?? 0);
                $stock = $newGudangStock ?? $curStock;
                $min   = $newGudangMin ?? $curMin;
                $price = $newGudangPrice ?? $curPrice;
                if ($stock !== $curStock || $min !== $curMin || $price !== $curPrice) {
                    $db->prepare('UPDATE warehouse_stocks SET stock=?,minimum_stock=?,purchase_price=?,updated_at=NOW() WHERE warehouse_id=? AND product_id=?')
                       ->execute([$stock, $min, $price, $warehouseId, $pid]);
                    if ($stock !== $curStock) {
                        warehouse_record_movement($db, $warehouseId, $pid, 'edit_stock', abs($stock - $curStock), $curStock, $stock, $price, 'edit_stock', null, $note !== '' ? $note : 'Edit stok dari menu Gudang');
                    }
                    $changed++;
                    $logLines[] = $product['name'] . ' stok gudang ' . $curStock . '→' . $stock . ' modal ' . $curPrice . '→' . $price;
                }
            }

            // ---- 2. stok CABANG UTAMA (yang dipakai POS & laporan) + sinkron stok master ----
            if ($mainBranchId > 0 && $isFilled($branchStock, (string)$key)) {
                $target = max(0, (int)$num($branchStock[$key]));
                if ($vid > 0) {
                    $before = marketplace_v16_variant_stock($db, $vid, $mainBranchId, true);
                    if ($target !== $before) {
                        $min = 0;
                        marketplace_v16_set_variant_stock($db, $vid, $mainBranchId, $target, 0);
                        marketplace_v16_sync_parent_stock($db, $pid, $mainBranchId);
                        try {
                            $db->prepare("INSERT INTO stock_movements (product_id,user_id,branch_id,type,qty,stock_before,stock_after,reference_type,reference_id,note,created_at) VALUES (?,?,?,?,?,?,?,'edit_stock',NULL,?,NOW())")
                               ->execute([$pid, Auth::id(), $mainBranchId, 'edit_stock', abs($target - $before), $before, $target, ($note !== '' ? $note . ' — ' : '') . 'Edit stok varian dari menu Gudang']);
                        } catch (Throwable $e) {}
                        $changed++;
                        $logLines[] = 'varian #' . $vid . ' stok cabang ' . $before . '→' . $target;
                    }
                } else {
                    $before = (int)$product['stock'];
                    try {
                        $q = $db->prepare('SELECT stock FROM product_branch_stocks WHERE branch_id=? AND product_id=? FOR UPDATE');
                        $q->execute([$mainBranchId, $pid]);
                        $x = $q->fetchColumn();
                        if ($x !== false) $before = (int)$x;
                    } catch (Throwable $e) {}
                    if ($target !== $before) {
                        $db->prepare('INSERT INTO product_branch_stocks (branch_id,product_id,stock,minimum_stock,created_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE stock=VALUES(stock),updated_at=NOW()')
                           ->execute([$mainBranchId, $pid, $target, (int)$product['minimum_stock']]);
                        $db->prepare('UPDATE products SET stock=?, updated_at=NOW() WHERE id=?')->execute([$target, $pid]);
                        try {
                            $db->prepare("INSERT INTO stock_movements (product_id,user_id,branch_id,type,qty,stock_before,stock_after,reference_type,reference_id,note,created_at) VALUES (?,?,?,?,?,?,?,'edit_stock',NULL,?,NOW())")
                               ->execute([$pid, Auth::id(), $mainBranchId, 'edit_stock', abs($target - $before), $before, $target, ($note !== '' ? $note . ' — ' : '') . 'Edit stok dari menu Gudang']);
                        } catch (Throwable $e) {}
                        $changed++;
                        $logLines[] = $product['name'] . ' stok cabang ' . $before . '→' . $target;
                    }
                }
            }

            // ---- 3. HARGA MODAL MASTER (dipakai laporan nilai modal) ----
            if ($isFilled($masterPrice, (string)$key)) {
                $target = max(0.0, $num($masterPrice[$key]));
                if ($vid > 0) {
                    $selVariant->execute([$vid, $pid]);
                    $variant = $selVariant->fetch();
                    if ($variant && (float)$variant['purchase_price'] !== $target) {
                        $updVariant->execute([$target, $vid, $pid]);
                        $changed++;
                        $logLines[] = 'varian #' . $vid . ' modal master ' . $variant['purchase_price'] . '→' . $target;
                    }
                } elseif ((float)$product['purchase_price'] !== $target) {
                    $updProduct->execute([$target, $pid]);
                    $changed++;
                    $logLines[] = $product['name'] . ' modal master ' . $product['purchase_price'] . '→' . $target;
                }
            }
        }

        $db->commit();
        Auth::log('edit_stock', 'Edit stok gudang ' . $warehouseLabel . ': ' . $changed . ' perubahan' . ($logLines ? ' — ' . implode('; ', array_slice($logLines, 0, 10)) : ''));

        if ($changed > 0) {
            flash('success', $changed . ' perubahan stok/harga disimpan ke gudang ' . $warehouseLabel . '.');
        } else {
            flash('error', 'Tidak ada nilai yang berubah — isi minimal satu kolom sebelum menyimpan.');
        }
        legacy_redirect('admin/warehouse/edit-stock.php?warehouse_id=' . $warehouseId);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Gagal menyimpan: ' . $e->getMessage());
        legacy_redirect('admin/warehouse/edit-stock.php?warehouse_id=' . $warehouseId);
    }
}

// ---------- DATA UNTUK DITAMPILKAN ----------
$products = [];
try {
    $products = $db->query("SELECT p.id,p.name,p.product_code,p.unit,p.stock,p.minimum_stock,p.purchase_price,
                                   COALESCE(c.name,'-') AS category_name
                            FROM products p LEFT JOIN categories c ON c.id=p.category_id
                            WHERE p.is_active=1 ORDER BY p.name ASC")->fetchAll() ?: [];
} catch (Throwable $e) { $products = []; }

$ids = array_map(fn($p) => (int)$p['id'], $products);
$variantsByProduct = catalog_fast_variants_by_product($db, $ids, $mainBranchId > 0 ? $mainBranchId : null, true);
$warehouseVariantMap = catalog_fast_warehouse_variant_stocks($db, $warehouseId, $ids);

$warehouseStockMap = [];
$branchStockMap = [];
$variantBranchMap = [];
if ($ids) {
    try {
        $ph = catalog_fast_placeholders(count($ids));
        $stmt = $db->prepare("SELECT product_id,stock,minimum_stock,purchase_price FROM warehouse_stocks WHERE warehouse_id=? AND product_id IN ({$ph})");
        $stmt->execute(array_merge([$warehouseId], $ids));
        foreach ($stmt->fetchAll() as $row) $warehouseStockMap[(int)$row['product_id']] = $row;
    } catch (Throwable $e) {}
    if ($mainBranchId > 0) {
        try {
            $ph = catalog_fast_placeholders(count($ids));
            $stmt = $db->prepare("SELECT product_id,stock FROM product_branch_stocks WHERE branch_id=? AND product_id IN ({$ph})");
            $stmt->execute(array_merge([$mainBranchId], $ids));
            foreach ($stmt->fetchAll() as $row) $branchStockMap[(int)$row['product_id']] = (int)$row['stock'];
        } catch (Throwable $e) {}
        try {
            $ph = catalog_fast_placeholders(count($ids));
            $stmt = $db->prepare("SELECT variant_id,stock FROM marketplace_product_variant_stocks WHERE branch_id=? AND variant_id IN (SELECT id FROM marketplace_product_variants WHERE product_id IN ({$ph}))");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $variantBranchMap[(int)$row['variant_id']] = (int)$row['stock'];
        } catch (Throwable $e) {}
    }
}

$movements = [];
try {
    $stmt = $db->prepare("SELECT m.id,m.type,m.qty,m.stock_before,m.stock_after,m.note,m.created_at,p.name AS product_name,u.name AS user_name
                          FROM warehouse_stock_movements m
                          LEFT JOIN products p ON p.id=m.product_id
                          LEFT JOIN users u ON u.id=m.user_id
                          WHERE m.warehouse_id=? ORDER BY m.id DESC LIMIT 12");
    $stmt->execute([$warehouseId]);
    $movements = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {}

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= legacy_base_url('assets/css/warehouse.css?v=' . APP_ASSET_VERSION); ?>">
<style>
.edit-stock-list{display:flex;flex-direction:column;gap:8px;max-height:70vh;overflow:auto}
.edit-stock-group{border:1px solid #e5e7eb;border-radius:14px;background:#fff;flex:0 0 auto}
.edit-stock-head{display:flex;gap:10px;align-items:center;padding:10px 12px;background:#f8fafc;border-bottom:1px solid #eef2f7}
.edit-stock-row{display:grid;grid-template-columns:minmax(0,1fr) repeat(2,110px) 130px repeat(2,110px);gap:8px;padding:9px 12px;border-bottom:1px solid #f1f5f9;align-items:center}
.edit-stock-row:last-child{border-bottom:0}
.edit-stock-row.variant{background:#fcfcfd}
.edit-stock-row input{width:100%;border:1px solid #e2e5ea;border-radius:9px;padding:7px 8px;font-size:12px}
.edit-stock-row small{display:block;color:#6b7280;font-size:10px}
.edit-stock-name small{display:block;color:#6b7280;font-size:10px}
.edit-stock-cols{display:grid;grid-template-columns:minmax(0,1fr) repeat(2,110px) 130px repeat(2,110px);gap:8px;padding:0 12px 8px;color:#6b7280;font-size:10px;font-weight:800}
</style>

<main class="admin-main warehouse-wrap">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1>Edit Stok</h1>
            <p>Ubah stok gudang, stok cabang utama, minimal stok, dan harga modal. Semua perubahan dicatat di riwayat gudang.</p>
        </div>
        <div class="topbar-actions"><a class="btn-premium outline" href="<?= legacy_base_url('admin/warehouse/index.php'); ?>"><i class="bi bi-arrow-left"></i> Kembali</a></div>
    </header>

    <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m); ?></div><?php endif; ?>
    <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= e($m); ?></div><?php endif; ?>

    <section class="accounting-basis">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Cara pakai</strong>
            <p>Isi kolom yang ingin diubah (biarkan kosong = tidak diubah). <b>Stok Gudang</b> = stok di gudang ini.
               <b>Stok Cabang</b> = stok cabang <b><?= e($mainBranchName ?: '-'); ?></b> yang dipakai POS &amp; laporan (stok master ikut disesuaikan).
               <b>Modal Gudang</b> mengubah harga beli di gudang, <b>Modal Master</b> mengubah harga beli produk/varian yang dipakai
               perhitungan nilai modal di Dashboard, Laporan Stok, dan Akuntansi. Perubahan stok otomatis tercatat sebagai pergerakan gudang.</p>
        </div>
    </section>

    <section class="premium-card mt-4">
        <div class="card-head">
            <div><h3>Pilih Gudang</h3><p>Stok &amp; harga di bawah mengikuti gudang yang dipilih.</p></div>
        </div>
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Gudang</label>
                <select name="warehouse_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id']; ?>" <?= (int)$w['id'] === $warehouseId ? 'selected' : ''; ?>><?= e($w['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </section>

    <form method="post" class="mt-4">
        <?= CSRF::input(); ?>
        <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId; ?>">
        <section class="premium-card">
            <div class="card-head">
                <div><h3>Stok &amp; Harga — <?= e($warehouseLabel); ?></h3><p><?= count($products); ?> produk aktif. Ketik di kotak cari untuk menyaring.</p></div>
                <input type="search" id="editStockSearch" class="form-control" style="max-width:280px" placeholder="Cari nama / kode produk...">
            </div>

            <div class="edit-stock-cols">
                <span>Produk</span><span>Stok Gudang</span><span>Min Gudang</span><span>Modal Gudang</span>
                <span>Stok Cabang</span><span>Modal Master</span>
            </div>

            <div class="edit-stock-list" id="editStockList">
            <?php foreach ($products as $p):
                $pid = (int)$p['id'];
                $ws = $warehouseStockMap[$pid] ?? ['stock' => 0, 'minimum_stock' => (int)$p['minimum_stock'], 'purchase_price' => (float)$p['purchase_price']];
                $variants = $variantsByProduct[$pid] ?? [];
                ?>
                <div class="edit-stock-group" data-search="<?= e(strtolower($p['name'] . ' ' . $p['product_code'] . ' ' . $p['category_name'])); ?>">
                    <div class="edit-stock-head">
                        <i class="bi bi-box-seam"></i>
                        <div class="edit-stock-name">
                            <b><?= e($p['name']); ?></b>
                            <small><?= e($p['product_code'] ?: '-'); ?> • <?= e($p['category_name']); ?> • <?= e($p['unit']); ?><?= $variants ? ' • ' . count($variants) . ' varian' : ''; ?></small>
                        </div>
                    </div>
                    <?php
                    $rows = [];
                    $rows[] = ['key' => $rowKey($pid), 'name' => $p['name'], 'code' => $p['product_code'], 'unit' => $p['unit'],
                               'is_variant' => false, 'g_stock' => (int)($ws['stock'] ?? 0), 'g_min' => (int)($ws['minimum_stock'] ?? 0),
                               'g_price' => (float)($ws['purchase_price'] ?? 0), 'b_stock' => $branchStockMap[$pid] ?? null,
                               'm_price' => (float)$p['purchase_price']];
                    foreach ($variants as $v) {
                        $vid = (int)$v['id'];
                        $wv = $warehouseVariantMap[$pid][$vid] ?? ['stock' => 0, 'purchase_price' => 0];
                        $rows[] = ['key' => $rowKey($pid, $vid), 'name' => $v['display_name'] ?? $v['name'], 'code' => $v['sku'] ?? '', 'unit' => $p['unit'],
                                   'is_variant' => true, 'g_stock' => (int)($wv['stock'] ?? 0), 'g_min' => (int)($wv['minimum_stock'] ?? 0),
                                   'g_price' => (float)($wv['purchase_price'] ?? 0), 'b_stock' => $variantBranchMap[$vid] ?? null,
                                   'm_price' => (float)($v['purchase_price'] ?? 0)];
                    }
                    foreach ($rows as $r): ?>
                        <div class="edit-stock-row<?= $r['is_variant'] ? ' variant' : ''; ?>">
                            <div class="edit-stock-name">
                                <b style="font-size:12px"><?= $r['is_variant'] ? '↳ ' : ''; ?><?= e($r['name']); ?></b>
                                <small><?= e($r['code'] ?: '-'); ?></small>
                            </div>
                            <input type="number" min="0" step="1" name="gudang_stock[<?= e($r['key']); ?>]" placeholder="<?= $r['g_stock']; ?>">
                            <input type="number" min="0" step="1" name="gudang_min[<?= e($r['key']); ?>]" placeholder="<?= $r['g_min']; ?>">
                            <input type="number" min="0" step="0.01" name="gudang_price[<?= e($r['key']); ?>]" placeholder="<?= rupiah($r['g_price']); ?>">
                            <input type="number" min="0" step="1" name="branch_stock[<?= e($r['key']); ?>]" placeholder="<?= $r['b_stock'] === null ? 'n/a' : (int)$r['b_stock']; ?>" <?= $r['b_stock'] === null ? 'disabled' : ''; ?>>
                            <input type="number" min="0" step="0.01" name="master_price[<?= e($r['key']); ?>]" placeholder="<?= rupiah($r['m_price']); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </section>

        <div class="d-flex gap-2 align-items-center mt-3">
            <input type="text" name="note" class="form-control" style="max-width:420px" placeholder="Catatan perubahan (opsional)">
            <button type="submit" class="btn-premium"><i class="bi bi-save"></i> Simpan Perubahan</button>
            <small class="text-muted">Nilai pada kotak abu-abu = angka saat ini. Kosongkan = tidak diubah.</small>
        </div>
    </form>

    <section class="premium-card mt-4">
        <div class="card-head"><div><h3>Riwayat Perubahan Gudang</h3><p>12 pergerakan terakhir di gudang ini.</p></div></div>
        <div class="table-premium-card">
            <table class="premium-table">
                <thead><tr><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Jumlah</th><th>Stok</th><th>Catatan</th><th>Oleh</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string)$m['created_at']))); ?></td>
                        <td><b><?= e($m['product_name'] ?: '-'); ?></b></td>
                        <td><span class="badge-soft info"><?= e($m['type']); ?></span></td>
                        <td><?= (int)$m['qty']; ?></td>
                        <td><?= (int)$m['stock_before']; ?> → <b><?= (int)$m['stock_after']; ?></b></td>
                        <td><small><?= e((string)($m['note'] ?? '-')); ?></small></td>
                        <td><?= e($m['user_name'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movements): ?><tr><td colspan="7" class="text-center text-muted">Belum ada pergerakan.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
(() => {
  const input = document.getElementById('editStockSearch');
  const list = document.getElementById('editStockList');
  if (!input || !list) return;
  input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    list.querySelectorAll('.edit-stock-group').forEach(group => {
      group.hidden = q !== '' && !(group.dataset.search || '').includes(q);
    });
  });
})();
</script>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>