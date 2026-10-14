<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/Marketplace.php';
require_once __DIR__ . '/../branches/_helpers.php';
require_once __DIR__ . '/../stock/_sync_helper.php';
require_once __DIR__ . '/../prices/_helpers.php';
Auth::requireRole(['super_admin','owner']);
function product_multiple_uploads(array $files): array
{
    $out=[];
    if (empty($files['name']) || !is_array($files['name'])) return $out;
    $count=min(12,count($files['name']));
    for($i=0;$i<$count;$i++){
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $one=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
        $path=upload_product_image($one);
        if($path) $out[]=$path;
    }
    return $out;
}
function product_save_gallery_images(PDO $db,int $productId,array $paths,?string $primaryImage=null): void
{
    if(!$paths) return;
    marketplace_install($db);
    try{$q=$db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM marketplace_product_images WHERE product_id=?");$q->execute([$productId]);$sort=(int)$q->fetchColumn();}catch(Throwable $e){$sort=0;}
    $stmt=$db->prepare("INSERT INTO marketplace_product_images(product_id,image_path,is_primary,sort_order,created_at) VALUES(?,?,?,?,NOW())");
    foreach($paths as $path){
        if($primaryImage && $path===$primaryImage) continue;
        $sort++;$stmt->execute([$productId,$path,0,$sort]);
    }
}

$db=Database::connect();
marketplace_install($db);
price_install($db);
$id=(int)($_GET['id']??0);
$stmt=$db->prepare('SELECT * FROM products WHERE id=?');
$stmt->execute([$id]);
$p=$stmt->fetch();
if($p){ $p['weight_gram']=marketplace_v15_product_weight($db,$id,marketplace_default_weight());$p['brand_id']=marketplace_v15_product_brand_id($db,$id); }
$hasVariants=$p?marketplace_v16_product_has_variants($db,$id,true):false;
if($hasVariants){$variantTotal=marketplace_v16_product_variant_stock_total($db,$id,branch_current_id($db),true);if($variantTotal>0)$p['stock']=$variantTotal;}
if(!$p){
    flash('error','Produk tidak ditemukan.');
    redirect('admin/products/index.php');
}
$pageTitle='Edit Produk';
$categories=$db->query('SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order ASC,name ASC')->fetchAll();
$suppliers=$db->query('SELECT id,name FROM suppliers WHERE is_active=1 ORDER BY name ASC')->fetchAll();
$brands=marketplace_v15_brands($db,false);

if(is_post()){
    if(!CSRF::verify()){
        flash('error','Token tidak valid.');
        redirect('admin/products/edit.php?id='.$id);
    }
    $name=trim($_POST['name']??'');
    if($name===''){
        flash('error','Nama wajib diisi.');
        redirect('admin/products/edit.php?id='.$id);
    }

    try{
        $image=$p['image'];
        $uploadedImages=product_multiple_uploads($_FILES['images']??[]);
        $newImage=$uploadedImages[0]??upload_product_image($_FILES['image']??[]);
        if($newImage){$image=$newImage;}
        $oldStock=(int)$p['stock'];
        $newStock=$hasVariants?marketplace_v16_product_variant_stock_total($db,$id,branch_current_id($db),true):(int)($_POST['stock']??0);
        $sellingPrice=max(0, (float)($_POST['selling_price']??0));
        $code=trim($_POST['product_code']??'');
        if($code===''){
            $code='PRD'.date('YmdHis').random_int(10,99);
        }

        $weightGram = max(1, (int)($_POST['weight_gram'] ?? marketplace_v15_product_weight($db, $id, marketplace_default_weight())));
        // Keep the legacy POS update independent from optional marketplace columns.
        // Some subdomain databases do not have products.weight_gram yet.
        $up=$db->prepare("UPDATE products SET category_id=?,supplier_id=?,product_code=?,barcode=?,name=?,description=?,image=?,purchase_price=?,selling_price=?,stock=?,minimum_stock=?,unit=?,is_active=? WHERE id=?");
        $up->execute([
            ($_POST['category_id']??'')?:null,
            ($_POST['supplier_id']??'')?:null,
            $code,
            trim($_POST['barcode']??'')?:null,
            $name,
            trim($_POST['description']??''),
            $image,
            (float)($_POST['purchase_price']??0),
            $sellingPrice,
            $newStock,
            (int)($_POST['minimum_stock']??0),
            trim($_POST['unit']??'pcs'),
            isset($_POST['is_active'])?1:0,
            $id
        ]);
        marketplace_v15_set_product_weight($db, $id, $weightGram);
        marketplace_v15_set_product_brand($db, $id, max(0,(int)($_POST['brand_id']??0)));
        product_save_gallery_images($db,$id,$uploadedImages,$image);

        // Wajib sinkron: POS membaca harga ecer dari product_price_levels.
        // Tanpa ini, keranjang/nota transaksi baru masih bisa memakai harga lama.
        price_save($db, $id, 'retail', $sellingPrice, 1);

        $branchStockSync = stock_sync_set_active_branch($db, $id, $newStock, (int)($_POST['minimum_stock']??0));

        if($newStock !== $oldStock){
            $type=$newStock>$oldStock?'stock_in':'stock_out';
            $qty=abs($newStock-$oldStock);
            $st=$db->prepare("INSERT INTO stock_movements (product_id,user_id,type,qty,stock_before,stock_after,note) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$id,Auth::id(),$type,$qty,$oldStock,$newStock,'Update stok dari edit produk']);
        }
        flash('success','Produk berhasil diperbarui.');
        redirect('admin/products/index.php');
    }catch(Throwable $e){
        flash('error','Gagal update produk: '.$e->getMessage());
    }
}

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link href="<?= base_url('assets/css/product-barcode.css?v=' . time()); ?>" rel="stylesheet">

<style>
.product-price-priority{background:linear-gradient(135deg,#faf5ff,#fff1f2);border:1px solid #e9d5ff;border-radius:20px;padding:14px}.product-price-priority label{color:#6b21a8!important}.product-edit-quick-save{display:flex!important;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 0 2px}.product-edit-quick-save .btn-premium{min-height:50px}.product-edit-quick-save small{color:#64748b;font-weight:750}@media(max-width:720px){.product-edit-quick-save{position:sticky;top:8px;z-index:15;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border:1px solid #f3e8ff;border-radius:18px;padding:10px;box-shadow:0 12px 28px rgba(107,33,168,.12)}.product-edit-quick-save .btn-premium{width:100%;justify-content:center}.premium-form{padding-bottom:115px}}
</style>

<main class="admin-main">
    <header class="admin-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1>Edit Produk</h1>
            <p>Perbarui data produk, barcode, gambar, harga, dan stok.</p>
        </div>
        <div class="topbar-actions">
            <a href="<?= base_url('admin/marketplace-products/detail.php?id='.$id); ?>" class="btn-soft"><i class="bi bi-sliders"></i> Varian & Galeri Marketplace</a>
            <a href="<?= base_url('admin/products/label.php?id='.$id); ?>" class="btn-premium" target="_blank"><i class="bi bi-upc-scan"></i> Cetak Label</a>
        </div>
    </header>

    <section class="premium-card mt-4">
        <form method="POST" enctype="multipart/form-data" class="premium-form">
            <?= CSRF::input(); ?>
            <div class="form-grid">
                <div class="form-group"><label>Nama Produk <small>• bisa diubah</small></label><input name="name" value="<?= e($p['name']); ?>" required></div>
                <div class="form-group product-price-priority"><label>Harga Jual / Harga POS <small>• bisa diubah</small></label><input id="sellingPriceInput" type="number" min="0" step="1" inputmode="numeric" name="selling_price" value="<?= e($p['selling_price']); ?>" required><small>Harga baru langsung disinkronkan ke Retail/Ecer untuk POS, keranjang, dan nota transaksi baru.</small></div>
                <div class="form-group full product-edit-quick-save"><button type="submit" class="btn-premium"><i class="bi bi-check2-circle"></i> Simpan Nama & Harga Sekarang</button><small>Tidak perlu scroll sampai bawah. Tombol ini menyimpan seluruh perubahan pada form.</small></div>

                <div class="form-group">
                    <label>Kode Produk</label>
                    <div class="input-action-wrap">
                        <input id="productCodeInput" name="product_code" value="<?= e($p['product_code']); ?>" required>
                        <button type="button" class="btn-mini-tool js-generate-code" data-target="#productCodeInput"><i class="bi bi-magic"></i> Generate</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Barcode</label>
                    <div class="input-action-wrap">
                        <input id="barcodeInput" name="barcode" value="<?= e($p['barcode']); ?>" placeholder="Scan kamera / scanner fisik / isi manual">
                        <button type="button" class="btn-mini-tool js-scan-barcode" data-target="#barcodeInput"><i class="bi bi-camera"></i> Scan</button>
                    </div>
                    <div class="input-mini-actions">
                        <button type="button" class="btn-mini-tool js-copy-code-to-barcode" data-from="#productCodeInput" data-to="#barcodeInput"><i class="bi bi-files"></i> Pakai kode produk sebagai barcode</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category_id"><option value="">Pilih kategori</option><?php foreach($categories as $c): ?><option value="<?= $c['id']; ?>" <?= (int)$p['category_id']===(int)$c['id']?'selected':''; ?>><?= e($c['name']); ?></option><?php endforeach; ?></select>
                </div>

                <div class="form-group">
                    <label>Brand / Merek <small>(opsional)</small></label>
                    <select name="brand_id"><option value="0">Tanpa brand / generik</option><?php foreach($brands as $b): ?><option value="<?= (int)$b['id']; ?>" <?= (int)($p['brand_id']??0)===(int)$b['id']?'selected':''; ?>><?= e($b['name']); ?><?= $b['is_active']?'':' (nonaktif)'; ?></option><?php endforeach; ?></select>
                    <small>Brand tidak wajib. Gunakan untuk merek/pabrikan agar filter marketplace lebih rapi.</small>
                </div>

                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id"><option value="">Pilih supplier</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id']; ?>" <?= (int)$p['supplier_id']===(int)$s['id']?'selected':''; ?>><?= e($s['name']); ?></option><?php endforeach; ?></select>
                </div>

                <div class="form-group"><label>Satuan</label><input name="unit" value="<?= e($p['unit']); ?>"></div>
                <div class="form-group"><label>Berat Marketplace (gram)</label><input type="number" min="1" max="50000" name="weight_gram" value="<?= e((string)($p['weight_gram'] ?? marketplace_default_weight())); ?>"><small>Dipakai untuk perhitungan ongkir marketplace.</small></div>
                <div class="form-group"><label>Harga Beli</label><input type="number" name="purchase_price" value="<?= e($p['purchase_price']); ?>"></div>
                
                <div class="form-group"><label>Stok</label><input type="number" name="stock" value="<?= e($p['stock']); ?>" <?= $hasVariants?'readonly':''; ?>><?php if($hasVariants): ?><small>Stok induk otomatis = total stok seluruh varian. Ubah stok dari menu Varian & Galeri Marketplace.</small><?php endif; ?></div>
                <div class="form-group"><label>Stok Minimum</label><input type="number" name="minimum_stock" value="<?= e($p['minimum_stock']); ?>"></div>
                <div class="form-group"><label>Foto Produk Baru <small>(bisa multiple)</small></label><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><?php if($p['image']): ?><small>Foto pertama yang dipilih menjadi gambar utama baru; foto berikutnya masuk galeri Marketplace. Kosongkan bila tidak ingin mengganti foto.</small><?php endif; ?></div>
                <div class="form-group switch-line"><label><input type="checkbox" name="is_active" <?= $p['is_active']?'checked':''; ?>> Produk aktif</label></div>
                <div class="form-group full"><label>Deskripsi</label><textarea name="description"><?= e($p['description']); ?></textarea></div>
            </div>
            <div class="form-actions"><a class="btn-soft" href="<?= base_url('admin/products/index.php'); ?>">Batal</a><button class="btn-premium"><i class="bi bi-save"></i> Update Produk</button></div>
        </form>
    </section>
</main>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
if (typeof window.Html5Qrcode === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"><\/script>');
}
</script>
<script src="<?= base_url('assets/js/product-barcode.js?v=' . time()); ?>"></script>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
