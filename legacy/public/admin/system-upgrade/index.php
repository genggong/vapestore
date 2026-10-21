<?php
require_once __DIR__.'/../../../app/bootstrap.php';
require_once __DIR__.'/../../../app/Modules/System/SystemUpgrade.php';
Auth::requireLogin();
$db=Database::connect();
$pageTitle='Upgrade Sistem & Database';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$log=[];$error='';

if(is_post()){
    if(!CSRF::verify()) $error='Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    else{
        $action=(string)($_POST['action']??'upgrade');
        try{
            if($action==='upgrade'){
                $log=vk_sys_upgrade($db);
                flash('success','Upgrade database selesai. Status di bawah sudah dipindai ulang dari database aktif.');
            }elseif($action==='migrate_media'){
                $log=vk_sys_migrate_legacy_uploads($db);
                flash('success','Migrasi media selesai. File lama yang ditemukan di folder public/pos/legacy disalin aman ke /uploads canonical tanpa menghapus file sumber.');
            }elseif($action==='reset_missing_media'){
                $log=vk_sys_reset_missing_media($db);
                flash('success','Pemeriksaan media selesai. Path file yang memang hilang direset agar fallback bawaan dapat dipakai.');
            }elseif($action==='optimize_media'){
                $log=vk_sys_optimize_upload_images(60);
                flash('success','Optimasi gambar selesai untuk batch ini. Tekan lagi bila masih ada gambar besar.');
            }elseif($action==='cleanup_duplicate_media'){
                $log=vk_sys_cleanup_duplicate_uploads(200);
                flash('success','Pembersihan duplikat media selesai. Hanya salinan lama yang identik dengan /uploads utama yang dihapus.');
            }
        }catch(Throwable $e){$error=$e->getMessage();}
    }
}
$status=vk_sys_status($db);
$health=vk_sys_health_summary($status);
$success=flash('success');
$successType='success';
$badAssets=array_filter($status['assets'],fn($r)=>!$r['exists']);
$badMedia=array_filter($status['media'],fn($r)=>!$r['exists']);
$badBridge=array_filter($status['bridge']??[],fn($r)=>!$r['ok']);
$badCheckout=count(array_filter($status['checkoutIntegrity']??[],fn($r)=>empty($r['ok'])));
$problemTotal=(int)$health['core_problems'];
$warningTotal=(int)$health['media_warnings']+(int)($health['pakasir_warnings']??0)+(int)($health['courier_warnings']??0);

// Jangan tampilkan pesan "berhasil" yang menyesatkan bila upgrade masih menyisakan masalah DB.
// Gambar/media lama dipisah sebagai warning karena bukan kegagalan CREATE/ALTER/INDEX.
$logFailures=count(array_filter($log,static fn($line)=>str_starts_with(strtoupper(trim((string)$line)),'GAGAL')));
if($_SERVER['REQUEST_METHOD']==='POST' && !$error){
    $postedAction=(string)($_POST['action']??'upgrade');
    if($postedAction==='upgrade'){
        if((int)$health['db_problems']===0){
            $success='Upgrade database selesai. Schema, index dan integritas checkout yang diperiksa sudah sehat.';
            if($logFailures>0){ $successType='warning'; $success.=' Ada '.$logFailures.' log bertanda GAGAL; lihat Log Upgrade untuk memastikan apakah hanya modul opsional atau perlu tindakan.'; }
        }else{
            $successType='error';
            $success='Upgrade sudah dijalankan, tetapi masih ada '.(int)$health['db_problems'].' masalah database/checkout yang perlu diperiksa pada tabel di bawah.';
        }
    }elseif($postedAction==='migrate_media'){
        $success='Migrasi media selesai. Scanner menemukan '.$warningTotal.' file/path media yang masih perlu perhatian.';
    }elseif($postedAction==='reset_missing_media'){
        $success='Pemeriksaan/reset media selesai. Scanner menemukan '.$warningTotal.' file/path media yang masih perlu perhatian.';
    }elseif($postedAction==='optimize_media'){
        $success='Optimasi gambar selesai untuk batch ini.';
    }elseif($postedAction==='cleanup_duplicate_media'){
        $success='Pembersihan duplikat media selesai.';
    }
}
require __DIR__.'/../../layouts/admin-header.php';
require __DIR__.'/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?=legacy_base_url('assets/css/marketplace-customer-admin.css?v=17.0.6')?>">
<style>
.su-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}.su-card{background:#fff;border:1px solid #e8edf3;border-radius:18px;padding:16px;min-width:0}.su-card small{display:block;color:#64748b;font-weight:800;font-size:11px}.su-card b{display:block;margin-top:6px;font-size:20px;overflow-wrap:anywhere}.su-actions{display:flex;gap:10px;flex-wrap:wrap}.su-table{width:100%;border-collapse:collapse}.su-table th,.su-table td{padding:11px 12px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}.su-table th{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}.su-pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900}.su-pill.ok{background:#dcfce7;color:#166534}.su-pill.bad{background:#fee2e2;color:#991b1b}.su-code{font:12px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere}.su-log{max-height:360px;overflow:auto;background:#111827;color:#e5e7eb;border-radius:16px;padding:14px;font:12px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace}.su-log div+div{margin-top:4px}.su-note{padding:13px 14px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;line-height:1.65}.su-danger{background:#fff7ed;border-color:#fed7aa}.su-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap}.su-section-head h3{margin:0 0 4px}.su-section-head p{margin:0;color:#64748b}.su-file-ok{color:#166534;font-weight:800}.su-file-bad{color:#991b1b;font-weight:800}.su-alert-warning{margin:14px 0;padding:18px 20px;border:1px solid #fde68a;border-radius:18px;background:#fffbeb;color:#92400e;font-weight:700;line-height:1.65}.su-health-grid{margin-top:14px}@media(max-width:1000px){.su-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.su-grid{grid-template-columns:1fr}.table-premium-card{overflow:auto}.su-table{min-width:760px}.su-actions{width:100%}.su-actions form,.su-actions button{width:100%}}
</style>
<main class="admin-main mp-admin">
<header class="admin-topbar page-topbar"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><div class="topbar-title"><h1>Upgrade Sistem & Database v17.0.37</h1><p>Scanner penuh untuk routing CI4, 54 menu admin, detail produk, POS, varian, pembelian, request stock, stock opname, marketplace, finance, file asset, gambar produk lama/baru dan database.</p></div></header>
<?php if($success):?><?php if($successType==='error'):?><div class="mp-alert error"><?=e($success)?></div><?php elseif($successType==='warning'):?><div class="su-alert-warning"><?=e($success)?></div><?php else:?><div class="mp-alert success"><?=e($success)?></div><?php endif;?><?php endif;?>
<?php if($error):?><div class="mp-alert error"><?=e($error)?></div><?php endif;?>
<?php if($problemTotal>0):?>
<div class="mp-alert error"><b>Ditemukan <?=$problemTotal?> masalah inti.</b>
<?php if((int)$health['db_problems']>0):?> Database/checkout: <b><?=(int)$health['db_problems']?></b>. Jalankan <b>Upgrade Otomatis</b> lalu cek detail yang masih merah.<?php endif;?>
<?php if((int)$health['routing']>0):?> Routing/menu: <b><?=(int)$health['routing']?></b> — ini masalah file/route dan tidak akan selesai hanya dengan ALTER database.<?php endif;?>
<?php if((int)$health['assets']>0):?> Asset inti hilang: <b><?=(int)$health['assets']?></b> — upload ulang patch bila diperlukan.<?php endif;?>
<?php if((int)($health['pakasir_core']??0)>0):?> Integrasi Pakasir v2: <b><?=(int)$health['pakasir_core']?></b>.<?php endif;?><?php if((int)($health['courier_core']??0)>0):?> Portal Kurir: <b><?=(int)$health['courier_core']?></b> — cek bagian Audit Portal Kurir.<?php endif;?>
</div>
<?php elseif($warningTotal>0):?>
<div class="su-alert-warning"><b>Database & fitur inti sudah sehat.</b> Masih ada <b><?=$warningTotal?> peringatan konfigurasi/media</b>. Ini <b>bukan error upgrade database</b>. Cek bagian <b>Pakasir API v2</b>, <b>Audit Portal Kurir</b>, dan <b>Audit File Upload vs Database</b> untuk detailnya.</div>
<?php else:?><div class="mp-alert success"><b>Semua pemeriksaan utama sehat.</b> Database, checkout, routing, menu admin, asset inti dan media yang direferensikan tidak menemukan masalah.</div><?php endif;?>

<section class="su-grid su-health-grid" style="grid-template-columns:repeat(7,minmax(0,1fr))">
 <div class="su-card"><small>Masalah DB / Schema</small><b><?=(int)$health['db_schema']?></b></div>
 <div class="su-card"><small>Checkout</small><b><?=(int)$health['checkout']?></b></div>
 <div class="su-card"><small>Routing / Menu</small><b><?=(int)$health['routing']?></b></div>
 <div class="su-card"><small>Asset Inti</small><b><?=(int)$health['assets']?></b></div>
 <div class="su-card"><small>Pakasir v2</small><b><?=(int)($health['pakasir_core']??0)?></b></div>
 <div class="su-card"><small>Portal Kurir</small><b><?=(int)($health['courier_core']??0)?></b></div>
 <div class="su-card"><small>Peringatan Config/Media</small><b><?=$warningTotal?></b></div>
</section>

<section class="su-grid">
 <div class="su-card"><small>Database aktif</small><b><?=e($status['database']?:'-')?></b></div>
 <div class="su-card"><small>Total tabel DB</small><b><?=number_format($status['tableCount'])?></b></div>
 <div class="su-card"><small>Tabel kurang</small><b><?=$status['missingTables']?></b></div>
 <div class="su-card"><small>Kolom kurang</small><b><?=$status['missingColumns']?></b></div>
 <div class="su-card"><small>Index kurang</small><b><?=$status['missingIndexes']?></b></div>
</section>

<section class="mp-panel"><div class="su-section-head"><div><h3>Upgrade Otomatis v17.0.37</h3><p>Menyelaraskan schema tanpa reset database. Termasuk Produk + Varian, POS, Pembelian, Request Stock, Stock Opname, Marketplace, Gudang, Finance dan tracking.</p></div><div class="su-actions"><form method="post" onsubmit="return confirm('Jalankan upgrade database sekarang? Proses tidak melakukan DROP/TRUNCATE dan tidak menghapus transaksi/order/produk.')"><?=CSRF::input()?><input type="hidden" name="action" value="upgrade"><button class="mp-btn"><i class="bi bi-database-up"></i> Jalankan Upgrade Otomatis</button></form></div></div>
<div class="su-note su-danger" style="margin-top:14px"><b>Sebelum menjalankan:</b> backup database sekali dari phpMyAdmin. Upgrade dibuat idempotent, jadi aman ditekan kembali jika koneksi hosting sempat timeout.</div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Bridge CI4 & Routing</h3><p>Audit khusus untuk kasus menu admin, halaman detail, API, dan URL bertingkat yang tidak bisa dibuka setelah migrasi CI4.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Pemeriksaan</th><th>Status</th></tr></thead><tbody>
<?php foreach(($status['bridge']??[]) as $r):?><tr><td><?=e($r['label'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'OK':'PERLU PATCH'?></span></td></tr><?php endforeach;?>
</tbody></table></div><div class="su-note" style="margin-top:12px"><b>Navigasi source:</b> <?=number_format((int)($status['navigation']['php_files']??0))?> file PHP dipindai, <?=number_format((int)($status['navigation']['unique_targets']??0))?> target URL lokal ditemukan, <?=number_format((int)($status['navigation']['menu_total']??0))?> menu admin diperiksa. Target hilang: <b><?=count($status['navigation']['missing_targets']??[])?></b>, menu rusak: <b><?=number_format((int)($status['navigation']['menu_bad']??0))?></b>.</div>
<?php if(!empty($status['navigation']['missing_targets'])):?><div class="su-log" style="margin-top:12px"><?php foreach($status['navigation']['missing_targets'] as $url=>$source):?><div>HILANG: <?=e($url)?> ← <?=e($source)?></div><?php endforeach;?></div><?php endif;?></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Audit 54 Menu Admin</h3><p>Memastikan tombol sidebar benar-benar menunjuk file/route yang tersedia, bukan hanya tampil secara visual.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Menu</th><th>Hak Akses</th><th>Target</th><th>Status</th></tr></thead><tbody>
<?php foreach(($status['navigation']['menu']??[]) as $r):?><tr><td><b><?=e($r['label'])?></b></td><td class="su-code"><?=e($r['permission'])?></td><td class="su-code"><?=e($r['url'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'ADA':'HILANG'?></span></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Pakasir API v2</h3><p>Audit endpoint create/status dan keamanan webhook untuk POS, Marketplace, serta QRIS piutang.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Pemeriksaan</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php foreach(($status['pakasirV2']??[]) as $r):?><tr><td><?=e($r['label'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'OK':(($r['severity']??'core')==='warning'?'PERLU SETTING':'PERLU PATCH')?></span></td><td class="su-code"><?=e($r['detail']??'-')?></td></tr><?php endforeach;?>
</tbody></table></div><div class="su-note" style="margin-top:12px"><b>API v2:</b> transaksi baru memakai <code>/api/v2/create-transaction</code>, cek status memakai <code>/api/v2/transaction-status</code>, API Key dikirim lewat <code>X-Api-Key</code>, dan webhook dapat diverifikasi dengan <code>X-Secret</code>. Transaksi lama tanpa <code>txn_id</code> tetap diberi fallback v1 sementara untuk histori lama.</div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Audit Portal Kurir</h3><p>Memeriksa route <code>/kurir</code>, CSS/JS, schema kurir, assignment orphan, akun aktif, folder bukti antar, kompresi foto, dan clean action route.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Pemeriksaan</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php foreach(($status['courierAudit']??[]) as $r):?><tr><td><?=e($r['label'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'OK':(($r['severity']??'core')==='warning'?'PERINGATAN':'PERLU PERBAIKAN')?></span></td><td class="su-code"><?=e($r['detail']??'-')?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Integritas Checkout Customer</h3><p>Audit khusus untuk error FK 1452 pada <code>online_order_items.order_id</code>. v17.0.12 mempertahankan perbaikan pengambilan ID order langsung setelah INSERT dan memverifikasi parent sebelum item disimpan.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Pemeriksaan</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php foreach(($status['checkoutIntegrity']??[]) as $r):?><tr><td><?=e($r['label'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'OK':'PERLU UPGRADE'?></span></td><td class="su-code"><?=e($r['detail']??'-')?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Schema Aplikasi</h3><p>Kolom yang diperiksa adalah kolom yang benar-benar dipakai oleh source v17.0.37.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Tabel</th><th>Status</th><th>Data</th><th>Kolom Kurang</th></tr></thead><tbody>
<?php foreach($status['schema'] as $name=>$r):?><tr><td class="su-code"><b><?=e($name)?></b></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'OK':'PERLU UPGRADE'?></span></td><td><?=$r['count']===null?'-':number_format($r['count'])?></td><td class="su-code"><?=e($r['missing']?implode(', ',$r['missing']):'-')?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Index Performa</h3><p>Index penting untuk produk banyak, varian, POS, pembelian, stok dan laporan multi-cabang.</p></div></div><div class="table-premium-card"><table class="su-table"><thead><tr><th>Tabel</th><th>Index</th><th>Status</th></tr></thead><tbody>
<?php foreach($status['indexes'] as $r):?><tr><td class="su-code"><?=e($r['table'])?></td><td class="su-code"><?=e($r['index'])?></td><td><span class="su-pill <?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'ADA':'BELUM ADA'?></span></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="su-section-head"><div><h3>File Web & Media Marketplace</h3><p>Bagian ini membantu mendeteksi kasus halaman putih, animasi tidak jalan, logo/gambar pecah, atau asset tidak ikut ter-upload.</p></div><?php if($badMedia):?><div class="su-actions"><form method="post" onsubmit="return confirm('Reset hanya path logo/favicon/hero/promo yang file fisiknya benar-benar tidak ada? Data produk dan transaksi tidak disentuh.')"><?=CSRF::input()?><input type="hidden" name="action" value="reset_missing_media"><button class="mp-btn soft"><i class="bi bi-image"></i> Reset Path Media Hilang</button></form></div><?php endif;?></div>
<div class="table-premium-card" style="margin-top:14px"><table class="su-table"><thead><tr><th>Komponen</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
<?php foreach($status['assets'] as $r):?><tr><td><?=e($r['label'])?></td><td class="<?=$r['exists']?'su-file-ok':'su-file-bad'?>"><?=$r['exists']?'ADA':'HILANG'?></td><td class="su-code"><?=$r['exists']?number_format($r['size']/1024,1).' KB':'Upload ulang patch/full package'?></td></tr><?php endforeach;?>
<?php foreach($status['media'] as $r):?><tr><td class="su-code"><?=e($r['key'])?></td><td class="<?=$r['exists']?'su-file-ok':'su-file-bad'?>"><?=$r['exists']?'OK':'FILE HILANG'?></td><td class="su-code"><?=e($r['value']?:'(fallback bawaan)')?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="mp-panel"><div class="su-section-head"><div><h3>Audit File Upload vs Database</h3><p>Memeriksa gambar produk, varian, galeri, brand, banner, user, bukti kurir dan setting yang menyimpan path <code>uploads/</code>. Upload baru otomatis resize + kompres; gambar lama bisa dikompres bertahap tanpa mengubah path database.</p></div><div class="su-actions"><form method="post" onsubmit="return confirm('Cari file gambar lama di folder public/pos/legacy lalu salin ke /uploads utama? File sumber tidak dihapus dan data database tidak diubah.')"><?=CSRF::input()?><input type="hidden" name="action" value="migrate_media"><button class="mp-btn soft"><i class="bi bi-images"></i> Pulihkan / Migrasikan Gambar Lama</button></form><form method="post" onsubmit="return confirm('Kompres maksimal 60 gambar besar pada batch ini? Nama/path file tetap sama, jadi referensi database tidak berubah.')"><?=CSRF::input()?><input type="hidden" name="action" value="optimize_media"><button class="mp-btn soft"><i class="bi bi-file-earmark-zip"></i> Kompres Gambar Upload</button></form><form method="post" onsubmit="return confirm('Hapus hanya file duplikat IDENTIK di folder legacy/public/pos jika salinan canonical /uploads sudah ada? File unik tidak akan dihapus.')"><?=CSRF::input()?><input type="hidden" name="action" value="cleanup_duplicate_media"><button class="mp-btn soft"><i class="bi bi-trash3"></i> Bersihkan Duplikat Media Lama</button></form></div></div>
<div class="su-note" style="margin:14px 0"><b>Status media tidak menentukan keberhasilan upgrade database.</b> File gambar lama yang hilang/terpindah dilaporkan sebagai peringatan terpisah agar tombol Upgrade Otomatis tidak terlihat gagal padahal schema DB sudah sehat.</div>
<div class="su-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))"><div class="su-card"><small>Path direferensikan DB</small><b><?=number_format((int)($status['mediaStorage']['referenced']??0))?></b></div><div class="su-card"><small>Sudah di /uploads utama</small><b><?=number_format((int)($status['mediaStorage']['canonical_count']??0))?></b></div><div class="su-card"><small>Ditemukan di folder lama</small><b><?=number_format((int)($status['mediaStorage']['recoverable_count']??0))?></b></div><div class="su-card"><small>Benar-benar hilang</small><b><?=number_format((int)($status['mediaStorage']['missing_count']??0))?></b></div><div class="su-card"><small>File upload di disk</small><b><?=number_format((int)($status['mediaStorage']['disk_files']??0))?></b></div></div>
<?php if(!empty($status['mediaStorage']['missing'])):?><div class="su-log"><?php foreach($status['mediaStorage']['missing'] as $r):?><div>FILE HILANG: <?=e($r['path'])?> ← <?=e($r['source'])?></div><?php endforeach;?></div><div class="su-note su-danger" style="margin-top:10px"><b>Jangan reset massal gambar produk.</b> v17.0.5 mencari file lama di <code>/uploads</code>, <code>/legacy/public/uploads</code>, <code>/public/uploads</code>, <code>/pos/public/uploads</code> dan <code>/pos/uploads</code>. Jika masih terdeteksi hilang, restore file dari backup ke salah satu path tersebut.</div><?php else:?><div class="su-note"><b>OK:</b> tidak ada path upload yang direferensikan database tetapi file fisiknya hilang.</div><?php endif;?>
<?php if(!empty($status['mediaStorage']['unreferenced'])):?><details style="margin-top:12px"><summary><b>Lihat calon file tak direferensikan (jangan hapus otomatis)</b></summary><div class="su-log" style="margin-top:10px"><?php foreach($status['mediaStorage']['unreferenced'] as $path):?><div><?=e($path)?></div><?php endforeach;?></div></details><?php endif;?>
</section>

<?php if($log):?><section class="mp-panel"><div class="mp-panel-head"><div><h3>Log Upgrade</h3><p>GAGAL = perlu diperiksa; OK/ADD = sudah sehat atau berhasil ditambahkan.</p></div></div><div class="su-log"><?php foreach($log as $line):?><div><?=e($line)?></div><?php endforeach;?></div></section><?php endif;?>

<section class="mp-panel"><div class="mp-panel-head"><div><h3>Catatan Diagnosa</h3></div></div><div class="su-note"><b>Menu/detail tidak bisa dibuka setelah CI4:</b> v17.0.12 tetap membawa perbaikan routing v17.0.3: catch-all route agar URL multi-segment seperti <code>/admin/products/index.php</code>, <code>/admin/marketplace-products/detail.php</code>, <code>/store/api/...</code>, dan <code>/produk/123-slug</code> tidak dipecah menjadi banyak parameter controller. Bridge juga meniru <code>SCRIPT_NAME</code>/<code>PHP_SELF</code> legacy supaya halaman create/edit lama tetap membaca konteks file yang benar.</div><div class="su-note" style="margin-top:10px"><b>Error SQLSTATE 21S01 / 1136:</b> itu berasal dari jumlah placeholder/value query INSERT yang tidak cocok dengan daftar kolom. Source POS v17 sudah menggunakan daftar kolom eksplisit untuk <code>transaction_items</code>; halaman ini memastikan kolom varian yang dibutuhkan juga tersedia di database.</div><div class="su-note" style="margin-top:10px"><b>Error Checkout FK 1452:</b> v17.0.5 memperbaiki urutan pengambilan <code>lastInsertId()</code>. ID <code>online_orders</code> sekarang diambil tepat setelah INSERT, diverifikasi ulang berdasarkan <code>order_number</code>, baru kemudian <code>online_order_items</code> disimpan. Upgrader juga mengecek AUTO_INCREMENT dan foreign key checkout.</div><div class="su-note" style="margin-top:10px"><b>Pembelian Produk + Varian:</b> hasil pencarian sekarang menampilkan nama Produk Induk terlebih dahulu lalu Varian, kode/barcode, stok dan modal. Baris tambahan memakai <code>item_key[]</code> yang sama dengan server agar produk varian bisa disimpan.</div><div class="su-note" style="margin-top:10px"><b>Error Pembelian Supplier “There is no active transaction”:</b> v17.0.12 mencegah installer schema <code>warehouse_install()</code> dan <code>marketplace_v16_install()</code> menjalankan DDL ketika transaksi pembelian sedang aktif. MySQL melakukan implicit COMMIT pada CREATE/ALTER TABLE; sebelumnya hal itu dapat memutus transaksi ketika membeli produk varian ke Gudang/Cabang. Installer kini diselesaikan sebelum <code>beginTransaction()</code>, sedangkan helper runtime stok varian tidak boleh memutus transaksi aktif. Pembatalan pembelian varian juga sudah mengoreksi stok varian, bukan stok induk.</div><div class="su-note" style="margin-top:10px"><b>List Produk POS v17.0.12:</b> kartu POS sekarang memisahkan Nama Produk Induk, Varian, Barcode, Kode/SKU, kategori, stok dan harga. Picker Pembelian juga memakai format yang sama agar pencarian manual tidak tertukar antar varian. Root <code>/assets</code> dan fallback <code>legacy/public/assets</code> disinkronkan supaya patch JS/CSS benar-benar terpakai di shared hosting.</div><div class="su-note" style="margin-top:10px"><b>Pakasir API v2:</b> v17.0.12 memigrasikan pembuatan QRIS POS, cek status POS, QRIS piutang, webhook POS/Marketplace, metode VA Marketplace, dan fallback polling customer ke endpoint v2. Isi juga Webhook Secret (X-Secret) di Pengaturan POS dan Marketplace sesuai secret pada masing-masing project Pakasir.</div><div class="su-note" style="margin-top:10px"><b>Portal Kurir tanpa CSS:</b> v17.0.12 memperbaiki <code>legacy_base_url()</code> yang sebelumnya membaca virtual <code>SCRIPT_NAME=/courier/login.php</code> sebagai root <code>/courier</code>. Akibatnya stylesheet dipanggil ke <code>/courier/assets/...</code> dan 404. Sekarang folder virtual <code>/courier/</code> selalu dikembalikan ke root aplikasi, clean route aksi kurir tersedia, foto bukti dikompres, dan scanner mempunyai Audit Portal Kurir.</div><div class="su-note" style="margin-top:10px"><b>Halaman marketplace hanya header/footer lalu tengah kosong:</b> biasanya JavaScript storefront tidak termuat sehingga elemen <code>.reveal</code> tetap tersembunyi. v17.0.12 tetap memiliki fallback agar konten muncul walau JS gagal. Untuk gambar, v17.0.5 menambah <code>media.php</code> compatibility endpoint dan penyimpanan upload canonical di <code>/uploads</code>.</div></section>
</main>
<?php require __DIR__.'/../../layouts/admin-footer.php';?>
