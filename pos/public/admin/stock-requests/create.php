<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/Marketplace.php';
require_once __DIR__ . '/../branches/_helpers.php';
require_once __DIR__ . '/../warehouse/_helpers.php';
warehouse_require_request_access();
$pageTitle = 'Buat Request Stock';
$db = Database::connect();
branch_install($db);
warehouse_install($db);
$currentBranch = branch_current($db);
$branchId = (int)$currentBranch['id'];
$warehouseId = warehouse_main_id($db);
$warehouseName = warehouse_name($db, $warehouseId);

if (is_post()) {
    if (!CSRF::verify()) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('admin/stock-requests/create.php');
    }
    $note = trim((string)($_POST['note'] ?? ''));
    $rawItems = (string)($_POST['items_json'] ?? '');
    $decoded = json_decode($rawItems, true);
    if (!is_array($decoded) || !$decoded) {
        flash('error', 'Pilih minimal 1 produk untuk request stok.');
        redirect('admin/stock-requests/create.php');
    }

    $items = [];
    foreach ($decoded as $row) {
        $pid=(int)($row['product_id']??0);$variantId=(int)($row['variant_id']??0);$qty=max(0,(int)($row['qty']??0));
        $key=$pid.':'.$variantId;
        if($pid>0&&$qty>0){if(!isset($items[$key]))$items[$key]=['product_id'=>$pid,'variant_id'=>$variantId,'qty'=>0];$items[$key]['qty']+=$qty;}
    }
    if (!$items) {
        flash('error', 'Item request tidak valid.');
        redirect('admin/stock-requests/create.php');
    }

    try {
        $db->beginTransaction();
        $requestNumber = warehouse_request_number($db);
        $detailRows = [];
        $total = 0.0;

        foreach ($items as $itemReq) {
            $productId=(int)$itemReq['product_id'];$variantId=(int)$itemReq['variant_id'];$qty=(int)$itemReq['qty'];
            $stmt = $db->prepare("SELECT id,name,product_code,unit,purchase_price FROM products WHERE id=? AND is_active=1 LIMIT 1 FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception('Ada produk yang tidak ditemukan / tidak aktif.');

            $variant=null;if($variantId>0){$vs=$db->prepare("SELECT * FROM marketplace_product_variants WHERE id=? AND product_id=? LIMIT 1");$vs->execute([$variantId,$productId]);$variant=$vs->fetch();if(!$variant)throw new Exception('Varian produk tidak ditemukan.');$ws=warehouse_variant_stock($db,$warehouseId,$productId,$variantId,true);}else{$ws=warehouse_stock($db,$warehouseId,$productId,true);}
            $available = (int)$ws['stock'];
            if ($available < $qty) {
                throw new Exception('Stok gudang untuk ' . $product['name'] . ' tidak cukup. Tersedia ' . $available . ' ' . $product['unit'] . '.');
            }
            $unitCost = (float)$ws['purchase_price'];
            if ($unitCost <= 0) $unitCost = (float)$product['purchase_price'];
            $lineTotal = $qty * $unitCost;
            $total += $lineTotal;
            $detailRows[] = [$product,$variant,$qty,$unitCost,$lineTotal];
        }

        $stmt = $db->prepare("INSERT INTO branch_stock_requests (request_number,branch_id,warehouse_id,requested_by,status,total_cost,note,created_at) VALUES (?,?,?,?, 'pending', ?, ?, NOW())");
        $stmt->execute([$requestNumber,$branchId,$warehouseId,Auth::id(),$total,$note ?: null]);
        $requestId = (int)$db->lastInsertId();

        $insert=$db->prepare("INSERT INTO branch_stock_request_items (request_id,product_id,variant_id,product_name,variant_name,variant_sku,product_code,unit,qty_requested,unit_cost,line_total,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        foreach($detailRows as [$product,$variant,$qty,$unitCost,$lineTotal]){
            $insert->execute([$requestId,$product['id'],$variant['id']??null,$product['name'],$variant['name']??null,$variant['sku']??null,($variant['sku']??'')?:$product['product_code'],$product['unit'],$qty,$unitCost,$lineTotal]);
        }

        Auth::log('create_stock_request', 'Membuat request stok ' . $requestNumber . ' untuk ' . $currentBranch['name']);
        $db->commit();
        flash('success', 'Request stok berhasil dikirim ke gudang.');
        redirect('admin/stock-requests/detail.php?id=' . $requestId);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Gagal membuat request: ' . $e->getMessage());
        redirect('admin/stock-requests/create.php');
    }
}

$stmt=$db->prepare("SELECT p.id,p.name,p.product_code,p.barcode,p.unit,p.image,c.name category_name,COALESCE(ws.stock,0) warehouse_stock,COALESCE(NULLIF(ws.purchase_price,0),p.purchase_price,0) warehouse_cost FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN warehouse_stocks ws ON ws.product_id=p.id AND ws.warehouse_id=? WHERE p.is_active=1 ORDER BY p.name ASC");
$stmt->execute([$warehouseId]);$baseProducts=$stmt->fetchAll();$products=[];
foreach($baseProducts as $p){
    $vars=marketplace_v15_variants($db,(int)$p['id'],null,true);
    if($vars){foreach($vars as $v){$wvs=warehouse_variant_stock($db,$warehouseId,(int)$p['id'],(int)$v['id']);$r=$p;$r['variant_id']=(int)$v['id'];$r['variant_name']=$v['name'];$r['variant_sku']=$v['sku']??'';$r['name']=$p['name'].' - '.$v['name'];$r['product_code']=$v['sku']?:$p['product_code'];$r['barcode']=$v['barcode']?:$p['barcode'];$r['warehouse_stock']=(int)$wvs['stock'];$r['warehouse_cost']=(float)($wvs['purchase_price']?:($v['purchase_price']?:$p['warehouse_cost']));$products[]=$r;}}
    else{$p['variant_id']=0;$p['variant_name']='';$p['variant_sku']='';$products[]=$p;}
}
usort($products,fn($a,$b)=>(((int)$a['warehouse_stock']<=0)<=>((int)$b['warehouse_stock']<=0))?:strnatcasecmp($a['name'],$b['name']));

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/warehouse.css?v=' . time()); ?>">
<main class="admin-main warehouse-wrap">
<header class="admin-topbar page-topbar"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><div class="topbar-title"><h1>Buat Request Stock</h1><p>Pilih barang seperti POS. Harga otomatis mengikuti modal gudang dari supplier tanpa margin.</p></div><div class="topbar-actions"><a class="btn-premium outline" href="<?= base_url('admin/stock-requests/index.php'); ?>"><i class="bi bi-arrow-left"></i> Kembali</a></div></header>

<section class="request-source-bar"><div><i class="bi bi-buildings"></i><span><small>Dari Gudang</small><b><?= e($warehouseName); ?></b></span></div><i class="bi bi-arrow-right"></i><div><i class="bi bi-shop"></i><span><small>Ke Cabang</small><b><?= e($currentBranch['name']); ?></b></span></div></section>

<form method="POST" id="stockRequestForm"><?= CSRF::input(); ?><input type="hidden" name="items_json" id="requestItemsJson">
<div class="request-pos-grid">
    <section class="request-products-panel">
        <div class="request-search"><i class="bi bi-search"></i><input type="search" id="requestProductSearch" placeholder="Cari nama, kode, barcode, kategori..."></div>
        <div class="request-product-list" id="requestProductList">
        <?php foreach($products as $p): $stock=(int)$p['warehouse_stock']; $cost=(float)$p['warehouse_cost']; ?>
            <article class="request-product-row" data-search="<?= e(strtolower($p['name'].' '.$p['product_code'].' '.$p['barcode'].' '.$p['category_name'])); ?>" data-product-id="<?= e($p['id']); ?>" data-variant-id="<?= e($p['variant_id']??0); ?>" data-name="<?= e($p['name']); ?>" data-parent-name="<?= e(preg_replace('/\s+-\s+[^-]+$/','',$p['name'])); ?>" data-code="<?= e($p['product_code']); ?>" data-unit="<?= e($p['unit']); ?>" data-stock="<?= $stock; ?>" data-cost="<?= e($cost); ?>">
                <div class="request-product-icon"><i class="bi bi-box-seam"></i></div>
                <div class="request-product-copy"><small><?= e($p['category_name'] ?: 'Produk'); ?></small><h3><?= e($p['name']); ?></h3><p><?= e($p['product_code']); ?><?= $p['barcode'] ? ' • '.e($p['barcode']) : ''; ?></p></div>
                <div class="request-product-meta"><span class="stock-pill <?= $stock<=0?'out':'ok'; ?>">Gudang <?= $stock; ?> <?= e($p['unit']); ?></span><b><?= rupiah($cost); ?></b><small>modal / <?= e($p['unit']); ?></small></div>
                <button type="button" class="request-add-btn" <?= $stock<=0?'disabled':''; ?>><i class="bi bi-plus-lg"></i></button>
            </article>
        <?php endforeach; ?>
        </div>
    </section>

    <aside class="request-cart-panel">
        <div class="request-cart-head"><div><small>DAFTAR REQUEST</small><h2>Barang Cabang</h2></div><span id="requestCartCount">0 item</span></div>
        <div id="requestCartItems" class="request-cart-items"><div class="request-empty"><i class="bi bi-cart-plus"></i><b>Belum ada barang</b><span>Pilih produk dari daftar gudang.</span></div></div>
        <div class="request-cart-total"><span>Total Modal</span><strong id="requestCartTotal">Rp 0</strong><small>Harga supplier, tanpa margin gudang.</small></div>
        <label class="request-note"><span>Catatan Request</span><textarea name="note" rows="3" placeholder="Contoh: kebutuhan restock cabang minggu ini"></textarea></label>
        <button class="btn-premium request-submit" type="submit" id="requestSubmit" disabled><i class="bi bi-send-check"></i> Kirim Request ke Gudang</button>
    </aside>
</div>
</form>
</main>
<script src="<?= base_url('assets/js/stock-request.js?v=' . time()); ?>"></script>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
