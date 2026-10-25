<?php

/**
 * Vapekong System Upgrade / Schema Doctor v17.0.48
 * Safe policy: CREATE/ALTER/INDEX only. No DROP/TRUNCATE and no business-row DELETE.
 */

function vk_sys_table_exists(PDO $db, string $table): bool
{
    try {
        $s = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function vk_sys_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $s = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $s->execute([$table, $column]);
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function vk_sys_index_exists(PDO $db, string $table, string $index): bool
{
    try {
        $s = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $s->execute([$table, $index]);
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function vk_sys_count(PDO $db, string $table): ?int
{
    if (!vk_sys_table_exists($db, $table)) return null;
    try { return (int)$db->query('SELECT COUNT(*) FROM `'.str_replace('`','',$table).'`')->fetchColumn(); }
    catch (Throwable $e) { return null; }
}

function vk_sys_expected_schema(): array
{
    return [
        'activity_logs' => ['id','user_id','action','description','ip_address','user_agent','created_at'],
        'roles' => ['id','name','slug','description','created_at'],
        'role_permissions' => ['id','role_id','permission_key','is_allowed','created_at'],
        'users' => ['id','role_id','branch_id','name','username','email','phone','password','status','last_login_at','created_at'],
        'branches' => ['id','code','name','phone','address','is_main','is_active','created_at'],
        'categories' => ['id','name','slug','icon','color','description','image','sort_order','is_active','created_at'],
        'suppliers' => ['id','name','phone','email','address','note','is_active','created_at'],
        'customers' => ['id','name','phone','email','address','member_code','member_level','price_level','points','credit_limit','status','created_at'],
        'payment_methods' => ['id','name','code','icon','is_active','sort_order','created_at'],
        'products' => ['id','category_id','supplier_id','product_code','barcode','name','purchase_price','selling_price','stock','minimum_stock','unit','is_active','brand_id','weight_gram'],
        'product_branch_stocks' => ['id','branch_id','product_id','stock','minimum_stock'],
        'marketplace_product_variants' => ['id','product_id','name','sku','barcode','selling_price','purchase_price','weight_gram','image_path','is_active','sort_order'],
        'marketplace_product_variant_stocks' => ['id','variant_id','branch_id','stock','minimum_stock'],
        'transactions' => ['id','invoice_number','customer_id','branch_id','cashier_id','subtotal','discount','tax','grand_total','paid_amount','change_amount','debt_amount','payment_method','status','transaction_date','created_at'],
        'transaction_items' => ['id','transaction_id','item_type','product_id','variant_id','bundle_id','product_name','variant_name','variant_sku','qty','unit_price','price_level','purchase_price','discount','subtotal'],
        'stock_movements' => ['id','product_id','user_id','branch_id','type','qty','stock_before','stock_after','reference_type','reference_id','created_at'],
        'cashier_shifts' => ['id','shift_code','user_id','branch_id','opening_cash','closing_cash','cash_sales','non_cash_sales','status','opened_at','closed_at'],
        'purchases' => ['id','purchase_number','supplier_id','user_id','stock_destination_type','warehouse_id','branch_id','purchase_date','subtotal','grand_total','status'],
        'purchase_items' => ['id','purchase_id','product_id','variant_id','product_name','variant_name','variant_sku','product_code','unit','qty','purchase_price','line_total','stock_before','stock_after'],
        'warehouses' => ['id','code','name','phone','address','is_main','is_active','note'],
        'warehouse_stocks' => ['id','warehouse_id','product_id','stock','minimum_stock'],
        'warehouse_variant_stocks' => ['id','warehouse_id','product_id','variant_id','stock','minimum_stock'],
        'branch_stock_requests' => ['id','request_number','branch_id','warehouse_id','requested_by','status','total_cost','created_at'],
        'branch_stock_request_items' => ['id','request_id','product_id','variant_id','product_name','variant_name','variant_sku','qty_requested','qty_fulfilled','unit_cost','line_total'],
        'stock_opnames' => ['id','code','branch_id','status','created_at'],
        'stock_opname_items' => ['id','opname_id','product_id','variant_id','product_name','variant_name','system_stock','physical_stock','difference','purchase_price','selling_price','difference_value'],
        'online_orders' => ['id','order_number','customer_id','branch_id','subtotal','discount','shipping_cost','grand_total','payment_method','payment_txn_id','payment_amount','payment_qr_string','payment_va_number','payment_status','payment_expired_at','order_status','created_at'],
        'online_order_items' => ['id','order_id','product_id','variant_id','product_name','variant_name','variant_sku','qty','unit_price','purchase_price','weight_gram','line_total'],
        'marketplace_couriers' => ['id','branch_id','name','phone','email','username','password_hash','vehicle_type','vehicle_number','status','last_login_at','created_at','updated_at'],
        'marketplace_courier_assignments' => ['id','order_id','courier_id','assigned_by','status','assigned_at','accepted_at','picked_up_at','on_the_way_at','delivered_at','failed_at','returned_at','delivery_proof','recipient_name','delivery_note','failure_reason','latitude','longitude','location_updated_at','created_at','updated_at'],
        'marketplace_courier_events' => ['id','assignment_id','order_id','courier_id','status','title','note','proof_path','created_at'],
        'marketplace_brands' => ['id','name','slug','is_active'],
        'finance_categories' => ['id','name','type','icon','color','is_active','created_at'],
        'finance_transactions' => ['id','category_id','user_id','branch_id','type','amount','title','transaction_date'],
        'debts' => ['id','supplier_id','user_id','branch_id','title','amount','paid_amount','status'],
        'receivables' => ['id','customer_name','customer_phone','user_id','title','amount','paid_amount','due_date','status'],
        'customer_debt_qris' => ['id','debt_id','customer_id','branch_id','order_id','txn_id','base_amount','unique_code','amount','payment_number','status','expired_at','created_at'],
        'settings' => ['id','setting_key','setting_value'],
        'shipments' => ['id','order_id','awb_number','shipping_order_id','courier','service','status','created_at'],
    ];
}

function vk_sys_expected_indexes(): array
{
    return [
        'products' => ['idx_products_name','idx_products_stock','idx_products_active','idx_products_brand'],
        'transactions' => ['idx_transactions_status_date'],
        'transaction_items' => ['idx_transaction_items_transaction','idx_transaction_items_product','idx_transaction_items_variant'],
        'purchase_items' => ['idx_purchase_items_purchase','idx_purchase_items_product','idx_purchase_items_variant'],
        'product_branch_stocks' => ['uniq_product_branch','idx_pbs_stock'],
        'marketplace_product_variants' => ['idx_mp_variants_product','uniq_mp_variant_sku'],
        'marketplace_product_variant_stocks' => ['uniq_mp_variant_branch','idx_mp_variant_stock_branch'],
        'warehouse_variant_stocks' => ['uniq_warehouse_variant','idx_wvs_product','idx_wvs_stock'],
        'branch_stock_request_items' => ['idx_bsri_request','idx_bsri_product','idx_bsri_variant'],
        'stock_opname_items' => ['idx_opname_items_opname','idx_opname_items_product','uniq_opname_product_variant'],
        'finance_transactions' => ['idx_finance_branch'],
        'debts' => ['idx_debts_branch'],
        'customer_debt_payments' => ['idx_customer_debt_payments_debt','idx_customer_debt_payments_customer','idx_customer_debt_payments_branch','idx_customer_debt_payments_created'],
        'customer_debt_qris' => ['idx_customer_debt_qris_txn'],
        'marketplace_couriers' => ['uniq_marketplace_courier_username','idx_marketplace_courier_status','idx_marketplace_courier_branch'],
        'marketplace_courier_assignments' => ['uniq_store_courier_order','idx_store_courier_courier','idx_store_courier_status'],
        'marketplace_courier_events' => ['idx_store_courier_event_assignment','idx_store_courier_event_order'],
    ];
}


function vk_sys_normalize_media_path(string $path): string
{
    $path=trim(str_replace('\\','/',$path));
    if($path==='' || preg_match('~^(?:https?:|data:|blob:)~i',$path)) return $path;
    $path=(string)(parse_url($path,PHP_URL_PATH)??$path);
    $path=ltrim($path,'/');
    foreach(['legacy/public/','public/','pos/public/','pos/'] as $prefix){
        if(str_starts_with($path,$prefix)){ $path=substr($path,strlen($prefix)); break; }
    }
    if(!str_starts_with($path,'uploads/')) return $path;
    $parts=[];foreach(explode('/',$path) as $part){if($part===''||$part==='.')continue;if($part==='..')return '';$parts[]=$part;}
    return implode('/',$parts);
}

function vk_sys_media_candidates(string $path): array
{
    $rel=vk_sys_normalize_media_path($path);
    if($rel==='' || !str_starts_with($rel,'uploads/')) return [];
    $root=dirname(__DIR__,4);
    return [
        'root'=>$root.'/'.$rel,
        'legacy'=>$root.'/legacy/public/'.$rel,
        'public'=>$root.'/public/'.$rel,
        'pos_public'=>$root.'/pos/public/'.$rel,
        'pos'=>$root.'/pos/'.$rel,
    ];
}

function vk_sys_media_find(string $path): ?array
{
    foreach(vk_sys_media_candidates($path) as $location=>$file){
        if(is_file($file)) return ['location'=>$location,'file'=>$file,'path'=>vk_sys_normalize_media_path($path)];
    }
    return null;
}

function vk_sys_migrate_legacy_uploads(PDO $db): array
{
    $audit=vk_sys_media_storage_audit($db);
    $log=[];$root=dirname(__DIR__,4);$copied=0;$already=0;$failed=0;
    foreach(($audit['references']??[]) as $rel=>$sources){
        $candidates=vk_sys_media_candidates($rel);$target=$candidates['root']??'';
        if($target!=='' && is_file($target)){ $already++; continue; }
        $found=vk_sys_media_find($rel);
        if(!$found){ continue; }
        if(($found['location']??'')==='root'){ $already++; continue; }
        $dir=dirname($target);
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){ $failed++; $log[]='GAGAL MEDIA: folder '.$dir.' tidak dapat dibuat'; continue; }
        if(!@copy((string)$found['file'],$target)){ $failed++; $log[]='GAGAL MEDIA: '.$rel.' dari '.$found['location']; continue; }
        @chmod($target,0644);$copied++;
    }
    $log[]="MEDIA: {$copied} file lama disalin ke /uploads canonical, {$already} sudah canonical, {$failed} gagal.";
    return $log;
}


function vk_sys_column_extra(PDO $db, string $table, string $column): string
{
    try{
        $q=$db->prepare("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
        $q->execute([$table,$column]); return strtolower((string)($q->fetchColumn()?:''));
    }catch(Throwable $e){ return ''; }
}

function vk_sys_checkout_integrity(PDO $db): array
{
    $checks=[];
    $orderAi=str_contains(vk_sys_column_extra($db,'online_orders','id'),'auto_increment');
    $itemAi=str_contains(vk_sys_column_extra($db,'online_order_items','id'),'auto_increment');
    $checks[]=['label'=>'online_orders.id AUTO_INCREMENT','ok'=>$orderAi,'detail'=>$orderAi?'aktif':'belum aktif'];
    $checks[]=['label'=>'online_order_items.id AUTO_INCREMENT','ok'=>$itemAi,'detail'=>$itemAi?'aktif':'belum aktif'];
    $fk=false;
    try{$q=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_order_items' AND COLUMN_NAME='order_id' AND REFERENCED_TABLE_NAME='online_orders' AND REFERENCED_COLUMN_NAME='id'");$fk=(int)$q->fetchColumn()>0;}catch(Throwable $e){}
    $checks[]=['label'=>'FK item checkout → online_orders.id','ok'=>$fk,'detail'=>$fk?'terpasang':'belum terpasang'];
    $orphans=0;
    try{if(vk_sys_table_exists($db,'online_order_items')&&vk_sys_table_exists($db,'online_orders'))$orphans=(int)$db->query("SELECT COUNT(*) FROM online_order_items i LEFT JOIN online_orders o ON o.id=i.order_id WHERE o.id IS NULL")->fetchColumn();}catch(Throwable $e){}
    $checks[]=['label'=>'Item checkout tanpa parent','ok'=>$orphans===0,'detail'=>$orphans.' orphan'];
    return $checks;
}

function vk_sys_fix_checkout_integrity(PDO $db, array &$log): void
{
    foreach([['online_orders','id'],['online_order_items','id'],['online_order_events','id']] as [$table,$column]){
        if(!vk_sys_table_exists($db,$table)||!vk_sys_column_exists($db,$table,$column))continue;
        if(str_contains(vk_sys_column_extra($db,$table,$column),'auto_increment')){$log[]="OK AUTO_INCREMENT: {$table}.{$column}";continue;}
        try{$db->exec("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT NOT NULL AUTO_INCREMENT");$log[]="FIX AUTO_INCREMENT: {$table}.{$column}";}catch(Throwable $e){$log[]="GAGAL AUTO_INCREMENT {$table}.{$column}: ".$e->getMessage();}
    }

    try{
        $q=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_order_items' AND COLUMN_NAME='order_id' AND REFERENCED_TABLE_NAME='online_orders' AND REFERENCED_COLUMN_NAME='id'");
        if((int)$q->fetchColumn()===0){
            $orphans=(int)$db->query("SELECT COUNT(*) FROM online_order_items i LEFT JOIN online_orders o ON o.id=i.order_id WHERE o.id IS NULL")->fetchColumn();
            if($orphans===0){$db->exec("ALTER TABLE online_order_items ADD CONSTRAINT fk_online_order_items_order FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE");$log[]='FIX FK: online_order_items.order_id → online_orders.id';}
            else{$log[]='INFO FK: tidak ditambah karena ada '.$orphans.' item orphan; data tidak dihapus otomatis.';}
        }else{$log[]='OK FK: online_order_items.order_id → online_orders.id';}
    }catch(Throwable $e){$log[]='GAGAL FK checkout: '.$e->getMessage();}
}

/**
 * Kompres gambar existing secara batch tanpa mengubah nama/path database.
 * Aman dipanggil berulang; file kecil dilewati. Batch dibatasi agar shared-hosting tidak timeout.
 */
function vk_sys_optimize_upload_images(int $limit=60): array
{
    $log=[];
    if(!function_exists('legacy_optimize_existing_image_file')) return ['GAGAL: helper kompresi gambar tidak tersedia.'];
    if(!extension_loaded('gd')) return ['INFO: PHP GD belum aktif. Aktifkan extension gd agar kompresi gambar server-side berjalan.'];
    $root=dirname(__DIR__,4);
    $roots=[$root.'/uploads',$root.'/legacy/public/uploads'];
    $files=[];
    foreach($roots as $base){
        if(!is_dir($base))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f){
            if(!$f->isFile())continue;
            $ext=strtolower($f->getExtension()); if(!in_array($ext,['jpg','jpeg','png','webp'],true))continue;
            $files[$f->getPathname()]=$f->getSize();
        }
    }
    arsort($files,SORT_NUMERIC);
    $processed=0;$changed=0;$before=0;$after=0;$failed=0;
    foreach($files as $file=>$size){
        if($processed>=$limit)break;
        if($size<220*1024)continue;
        $processed++;$r=legacy_optimize_existing_image_file($file,1600,1600,78);
        $before+=(int)($r['before']??0);$after+=(int)($r['after']??($r['before']??0));
        if(!empty($r['changed']))$changed++; elseif(empty($r['ok']))$failed++;
    }
    $saved=max(0,$before-$after);
    $log[]='KOMPRES MEDIA: '.$processed.' file diperiksa, '.$changed.' diperkecil, '.$failed.' gagal/dilewati berat.';
    $log[]='HEMAT STORAGE batch ini: '.number_format($saved/1048576,2).' MB ('.number_format($before/1048576,2).' MB → '.number_format($after/1048576,2).' MB).';
    if(count($files)>$processed)$log[]='INFO: proses dibatasi '.$limit.' file besar per klik agar hosting tidak timeout. Tombol boleh ditekan ulang.';
    return $log;
}


function vk_sys_cleanup_duplicate_uploads(int $limit=200): array
{
    $root=dirname(__DIR__,4);$canonical=$root.'/uploads';
    if(!is_dir($canonical)) return ['INFO: folder /uploads canonical belum ada. Jalankan migrasi media lebih dulu.'];
    $legacyRoots=[$root.'/legacy/public/uploads',$root.'/public/uploads',$root.'/pos/public/uploads',$root.'/pos/uploads'];
    $checked=0;$deleted=0;$saved=0;$failed=0;
    foreach($legacyRoots as $base){
        if(!is_dir($base))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $f){
            if($checked>=$limit)break 2;
            if(!$f->isFile())continue;
            $checked++;
            $rel=str_replace('\\','/',substr($f->getPathname(),strlen($base)+1));
            $target=$canonical.'/'.$rel;
            if(!is_file($target))continue;
            $size=(int)$f->getSize(); if($size!==(int)filesize($target))continue;
            $same=false;try{$same=hash_file('sha1',$f->getPathname())===hash_file('sha1',$target);}catch(Throwable $e){}
            if(!$same)continue;
            if(@unlink($f->getPathname())){$deleted++;$saved+=$size;}else{$failed++;}
        }
    }
    // Hapus folder kosong saja; tidak menyentuh folder yang masih punya file unik.
    foreach($legacyRoots as $base){
        if(!is_dir($base))continue;
        try{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())@rmdir($f->getPathname());}}catch(Throwable $e){}
    }
    $out=[];$out[]='DUPLIKAT MEDIA: '.$checked.' file lama diperiksa, '.$deleted.' duplikat identik dihapus, '.$failed.' gagal dihapus.';
    $out[]='HEMAT STORAGE duplikat: '.number_format($saved/1048576,2).' MB.';
    if($checked>=$limit)$out[]='INFO: dibatasi '.$limit.' file per klik. Tombol boleh ditekan ulang.';
    return $out;
}

function vk_sys_pakasir_v2_audit(PDO $db): array
{
    $root=dirname(__DIR__,4);
    $checks=[];
    $sourceChecks=[
        ['label'=>'POS create transaction memakai Pakasir API v2','file'=>$root.'/legacy/public/admin/pos/index.php','needle'=>'/api/v2/create-transaction/'],
        ['label'=>'POS cek status memakai Pakasir API v2','file'=>$root.'/legacy/public/admin/pakasir/status.php','needle'=>'/api/v2/transaction-status/'],
        ['label'=>'Marketplace create transaction memakai Pakasir API v2','file'=>$root.'/legacy/app/Modules/Marketplace/Marketplace.php','needle'=>'/api/v2/create-transaction/'],
        ['label'=>'Marketplace status fallback memakai Pakasir API v2','file'=>$root.'/legacy/app/Modules/Marketplace/Marketplace.php','needle'=>'/api/v2/transaction-status/'],
        ['label'=>'Piutang QRIS create memakai Pakasir API v2','file'=>$root.'/legacy/public/admin/debts/_helpers.php','needle'=>'/api/v2/create-transaction/'],
        ['label'=>'Piutang QRIS cek status memakai Pakasir API v2','file'=>$root.'/legacy/public/admin/debts/_helpers.php','needle'=>'/api/v2/transaction-status/'],
        ['label'=>'Webhook POS mendukung X-Secret v2','file'=>$root.'/legacy/public/pakasir-webhook.php','needle'=>'X-Secret'],
        ['label'=>'Webhook Marketplace mendukung X-Secret v2','file'=>$root.'/legacy/public/marketplace-pakasir-webhook.php','needle'=>'X-Secret'],
    ];
    foreach($sourceChecks as $c){
        $content=is_file($c['file'])?(string)@file_get_contents($c['file']):'';
        $checks[]=['label'=>$c['label'],'ok'=>$content!==''&&str_contains($content,$c['needle']),'detail'=>$c['file'],'severity'=>'core'];
    }
    $settings=[
        ['label'=>'Webhook Secret POS (X-Secret)','key'=>'pakasir_webhook_secret'],
        ['label'=>'Webhook Secret Marketplace (X-Secret)','key'=>'marketplace_pakasir_webhook_secret'],
    ];
    foreach($settings as $row){
        $value=function_exists('setting')?trim((string)setting($row['key'],'')):'';
        $checks[]=['label'=>$row['label'],'ok'=>$value!=='','detail'=>$value!==''?'sudah diisi':'belum diisi — salin dari detail project Pakasir','severity'=>'warning'];
    }
    return $checks;
}

function vk_sys_courier_audit(PDO $db): array
{
    $root=dirname(__DIR__,4);$checks=[];
    $sourceChecks=[
        ['label'=>'Portal Kurir login tersedia','file'=>$root.'/legacy/public/courier/login.php','needle'=>'courier-login-card'],
        ['label'=>'CSS Portal Kurir tersedia','file'=>$root.'/assets/css/courier-portal.css','needle'=>'courier-login-card'],
        ['label'=>'JS Portal Kurir tersedia','file'=>$root.'/assets/js/courier-portal.js','needle'=>'data-courier-location'],
        ['label'=>'Base URL courier kembali ke root aplikasi','file'=>$root.'/legacy/app/Helpers/helpers.php','needle'=>"'/courier/'"],
        ['label'=>'Clean route aksi kurir tersedia','file'=>$root.'/app/Controllers/LegacyController.php','needle'=>"'kurir/aksi' => 'courier/action.php'"],
        ['label'=>'Bukti antar memakai kompresi gambar','file'=>$root.'/legacy/app/Modules/Marketplace/StoreCourier.php','needle'=>'legacy_store_optimized_image'],
    ];
    foreach($sourceChecks as $c){
        $content=is_file($c['file'])?(string)@file_get_contents($c['file']):'';
        $checks[]=['label'=>$c['label'],'ok'=>$content!==''&&str_contains($content,$c['needle']),'detail'=>$c['file'],'severity'=>'core'];
    }

    $requiredTables=['marketplace_couriers','marketplace_courier_assignments','marketplace_courier_events'];
    foreach($requiredTables as $table){
        $ok=vk_sys_table_exists($db,$table);
        $checks[]=['label'=>'Tabel '.$table,'ok'=>$ok,'detail'=>$ok?'tersedia':'belum tersedia — jalankan Upgrade Otomatis','severity'=>'core'];
    }

    if(vk_sys_table_exists($db,'marketplace_courier_assignments')){
        try{
            $n=(int)$db->query("SELECT COUNT(*) FROM marketplace_courier_assignments a LEFT JOIN online_orders o ON o.id=a.order_id WHERE o.id IS NULL")->fetchColumn();
            $checks[]=['label'=>'Assignment tanpa order induk','ok'=>$n===0,'detail'=>$n.' data orphan','severity'=>'core'];
        }catch(Throwable $e){$checks[]=['label'=>'Assignment tanpa order induk','ok'=>false,'detail'=>$e->getMessage(),'severity'=>'core'];}
        try{
            $n=(int)$db->query("SELECT COUNT(*) FROM marketplace_courier_assignments a LEFT JOIN marketplace_couriers c ON c.id=a.courier_id WHERE c.id IS NULL")->fetchColumn();
            $checks[]=['label'=>'Assignment tanpa akun kurir','ok'=>$n===0,'detail'=>$n.' data orphan','severity'=>'core'];
        }catch(Throwable $e){$checks[]=['label'=>'Assignment tanpa akun kurir','ok'=>false,'detail'=>$e->getMessage(),'severity'=>'core'];}
        try{
            $valid="'assigned','accepted','picked_up','on_the_way','delivered','failed','returned'";
            $n=(int)$db->query("SELECT COUNT(*) FROM marketplace_courier_assignments WHERE status NOT IN({$valid})")->fetchColumn();
            $checks[]=['label'=>'Status assignment valid','ok'=>$n===0,'detail'=>$n.' status tidak dikenal','severity'=>'core'];
        }catch(Throwable $e){$checks[]=['label'=>'Status assignment valid','ok'=>false,'detail'=>$e->getMessage(),'severity'=>'core'];}
    }

    $active=0;
    if(vk_sys_table_exists($db,'marketplace_couriers')){
        try{$active=(int)$db->query("SELECT COUNT(*) FROM marketplace_couriers WHERE status='active'")->fetchColumn();}catch(Throwable $e){}
    }
    $enabled=function_exists('setting')&&setting('marketplace_store_courier_enabled','off')==='on';
    $checks[]=['label'=>'Kurir aktif tersedia','ok'=>!$enabled||$active>0,'detail'=>$enabled?($active.' akun aktif'):'fitur Kurir Toko sedang nonaktif','severity'=>'warning'];

    $uploadRoot=$root.'/uploads';$legacyUpload=$root.'/legacy/public/uploads';
    $writable=(is_dir($uploadRoot)&&is_writable($uploadRoot)) || (!is_dir($uploadRoot)&&is_writable($root)) || (is_dir($legacyUpload)&&is_writable($legacyUpload));
    $checks[]=['label'=>'Folder bukti antar dapat ditulis','ok'=>$writable,'detail'=>$writable?'upload siap':'periksa permission /uploads atau legacy/public/uploads (755/775)','severity'=>'warning'];
    return $checks;
}

function vk_sys_status(PDO $db): array
{
    $database = '';
    $mysql = '';
    $tableCount = 0;
    try { $database = (string)$db->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $e) {}
    try { $mysql = (string)$db->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) {}
    try { $tableCount = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn(); } catch (Throwable $e) {}

    $schema = [];
    $missingTables = 0; $missingColumns = 0;
    foreach (vk_sys_expected_schema() as $table => $columns) {
        $exists = vk_sys_table_exists($db, $table);
        $missing = [];
        if (!$exists) {
            $missingTables++;
            $missing = $columns;
        } else {
            foreach ($columns as $column) if (!vk_sys_column_exists($db, $table, $column)) $missing[] = $column;
            $missingColumns += count($missing);
        }
        $schema[$table] = ['exists'=>$exists,'count'=>$exists?vk_sys_count($db,$table):null,'missing'=>$missing,'ok'=>$exists && !$missing];
    }

    $indexes=[]; $missingIndexes=0;
    foreach(vk_sys_expected_indexes() as $table=>$names){
        foreach($names as $name){
            $ok=vk_sys_index_exists($db,$table,$name);
            if(!$ok)$missingIndexes++;
            $indexes[]=['table'=>$table,'index'=>$name,'ok'=>$ok];
        }
    }

    $publicRoot = dirname(__DIR__, 3) . '/public';
    $assetFiles = [
        'JS Storefront' => $publicRoot.'/assets/js/storefront.js',
        'CSS Storefront' => $publicRoot.'/assets/css/storefront.css',
        'CSS Admin' => $publicRoot.'/assets/css/admin.css',
        'Logo fallback' => $publicRoot.'/assets/storefront/logo-vapekong.svg',
        'Hero fallback' => $publicRoot.'/assets/storefront/hero-fallback.svg',
    ];
    $assets=[];
    foreach($assetFiles as $label=>$path)$assets[]=['label'=>$label,'path'=>$path,'exists'=>is_file($path),'size'=>is_file($path)?filesize($path):0];

    $media=[];
    foreach(['marketplace_logo','marketplace_favicon','marketplace_hero_image','marketplace_promo_image'] as $key){
        $value=function_exists('setting')?(string)setting($key,''):'';
        $rel=vk_sys_normalize_media_path($value);
        $found=$rel===''?null:vk_sys_media_find($rel);
        $exists=$rel===''?true:(preg_match('~^https?://~i',$rel)?true:(bool)$found);
        $media[]=['key'=>$key,'value'=>$value,'exists'=>$exists,'location'=>$found['location']??null];
    }

    $bridge=vk_sys_bridge_audit();
    $navigation=vk_sys_navigation_audit();
    $mediaStorage=vk_sys_media_storage_audit($db);
    $checkoutIntegrity=vk_sys_checkout_integrity($db);
    $pakasirV2=vk_sys_pakasir_v2_audit($db);
    $courierAudit=vk_sys_courier_audit($db);
    return compact('database','mysql','tableCount','schema','indexes','missingTables','missingColumns','missingIndexes','assets','media','bridge','navigation','mediaStorage','checkoutIntegrity','pakasirV2','courierAudit');
}

/**
 * Ringkasan kesehatan scanner v17.0.48.
 * Memisahkan masalah inti (DB/routing/asset/checkout) dari peringatan media lama.
 * Tujuannya agar file gambar lama yang hilang tidak lagi dianggap sebagai kegagalan upgrade database.
 */
function vk_sys_health_summary(array $status): array
{
    $dbSchema = (int)($status['missingTables'] ?? 0)
        + (int)($status['missingColumns'] ?? 0)
        + (int)($status['missingIndexes'] ?? 0);

    $checkout = count(array_filter($status['checkoutIntegrity'] ?? [], static fn($r) => empty($r['ok'])));
    $bridge = count(array_filter($status['bridge'] ?? [], static fn($r) => empty($r['ok'])));
    $assets = count(array_filter($status['assets'] ?? [], static fn($r) => empty($r['exists'])));

    // Target navigasi dapat muncul sekaligus pada missing_targets dan menu_bad.
    // Hitung URL unik agar satu link rusak tidak dilaporkan dua kali.
    $navKeys = [];
    foreach (array_keys($status['navigation']['missing_targets'] ?? []) as $url) {
        $navKeys[(string)$url] = true;
    }
    foreach (($status['navigation']['menu'] ?? []) as $row) {
        if (empty($row['ok'])) $navKeys[(string)($row['url'] ?? $row['label'] ?? 'menu')] = true;
    }
    $navigation = count($navKeys);

    // Media setting dan mediaStorage sering menunjuk file yang sama.
    // Deduplikasi berdasarkan path normalized supaya satu gambar hilang = satu warning.
    $mediaKeys = [];
    foreach (($status['mediaStorage']['missing'] ?? []) as $row) {
        $path = vk_sys_normalize_media_path((string)($row['path'] ?? ''));
        if ($path !== '') $mediaKeys[$path] = true;
    }
    foreach (($status['media'] ?? []) as $row) {
        if (!empty($row['exists'])) continue;
        $value = vk_sys_normalize_media_path((string)($row['value'] ?? ''));
        $key = $value !== '' ? $value : 'setting:'.(string)($row['key'] ?? 'media');
        $mediaKeys[$key] = true;
    }
    $mediaWarnings = count($mediaKeys);

    $pakasirCore = count(array_filter($status['pakasirV2'] ?? [], static fn($r) => ($r['severity'] ?? 'core') === 'core' && empty($r['ok'])));
    $pakasirWarnings = count(array_filter($status['pakasirV2'] ?? [], static fn($r) => ($r['severity'] ?? '') === 'warning' && empty($r['ok'])));
    $courierCore = count(array_filter($status['courierAudit'] ?? [], static fn($r) => ($r['severity'] ?? 'core') === 'core' && empty($r['ok'])));
    $courierWarnings = count(array_filter($status['courierAudit'] ?? [], static fn($r) => ($r['severity'] ?? '') === 'warning' && empty($r['ok'])));
    $routing = $bridge + $navigation;
    $dbProblems = $dbSchema + $checkout;
    $coreProblems = $dbProblems + $routing + $assets + $pakasirCore + $courierCore;

    return [
        'db_schema' => $dbSchema,
        'checkout' => $checkout,
        'db_problems' => $dbProblems,
        'bridge' => $bridge,
        'navigation' => $navigation,
        'routing' => $routing,
        'assets' => $assets,
        'media_warnings' => $mediaWarnings,
        'pakasir_core' => $pakasirCore,
        'pakasir_warnings' => $pakasirWarnings,
        'courier_core' => $courierCore,
        'courier_warnings' => $courierWarnings,
        'media_keys' => array_keys($mediaKeys),
        'core_problems' => $coreProblems,
        'total_notices' => $coreProblems + $mediaWarnings + $pakasirWarnings + $courierWarnings,
    ];
}

function vk_sys_add_column(PDO $db, string $table, string $column, string $definition, array &$log): void
{
    if (!vk_sys_table_exists($db,$table)) { $log[]="SKIP: {$table}.{$column} (tabel belum ada)"; return; }
    if (vk_sys_column_exists($db,$table,$column)) { $log[]="OK: {$table}.{$column}"; return; }
    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    $log[]="ADD: {$table}.{$column}";
}

function vk_sys_add_index(PDO $db, string $table, string $index, string $columns, bool $unique, array &$log): void
{
    if (!vk_sys_table_exists($db,$table)) return;
    if (vk_sys_index_exists($db,$table,$index)) { $log[]="OK INDEX: {$table}.{$index}"; return; }
    $db->exec("ALTER TABLE `{$table}` ADD ".($unique?'UNIQUE ':'')."INDEX `{$index}` ({$columns})");
    $log[]="ADD INDEX: {$table}.{$index}";
}

function vk_sys_run_installers(PDO $db, array &$log): void
{
    $pub = dirname(__DIR__, 3) . '/public/admin';
    $app = dirname(__DIR__, 2);
    $requires = [
        $pub.'/prices/_helpers.php', $pub.'/returns/_helpers.php', $pub.'/locations/_helpers.php',
        $pub.'/bundles/_helpers.php', $pub.'/purchases/_helpers.php', $pub.'/debts/_helpers.php',
        $pub.'/supplier-returns/_helpers.php', $pub.'/branches/_helpers.php', $pub.'/warehouse/_helpers.php',
        $pub.'/whatsapp/_helpers.php',
        // Modul marketplace ada di <app>/Modules/Marketplace (bug lama: <app>/Marketplace -> 7 installer
        // selalu "fungsi tidak ditemukan" sehingga tabel/index marketplace tak pernah diperbaiki).
        $app.'/Modules/Marketplace/Marketplace.php', $app.'/Modules/Marketplace/MarketplaceV15.php',
        $app.'/Modules/Marketplace/MarketplaceV16.php', $app.'/Modules/Marketplace/StorePickup.php',
        $app.'/Modules/Marketplace/StoreCourier.php', $app.'/Modules/Marketplace/CustomerService.php',
        $app.'/Modules/Marketplace/WaGateway.php',
        // fallback layout lama
        $app.'/Marketplace/Marketplace.php', $app.'/Marketplace/MarketplaceV15.php', $app.'/Marketplace/MarketplaceV16.php',
        $app.'/Marketplace/StorePickup.php', $app.'/Marketplace/StoreCourier.php', $app.'/Marketplace/CustomerService.php',
        $app.'/Marketplace/WaGateway.php',
    ];
    foreach($requires as $file) if(is_file($file)) require_once $file;

    $jobs = [
        'Multi Cabang'=>'branch_install', 'Harga Grosir'=>'price_install', 'Bundling'=>'bundle_install',
        'Rak/Lokasi'=>'location_install', 'Retur Penjualan'=>'sales_returns_install', 'Hutang/Piutang'=>'debt_install',
        'Pembelian'=>'purchases_install', 'Retur Supplier'=>'supplier_returns_install', 'Gudang & Request Stock'=>'warehouse_install',
        'WhatsApp POS'=>'wa_install', 'Marketplace Core'=>'marketplace_install', 'Marketplace V15'=>'marketplace_v15_install',
        'Marketplace V16 / Stok Varian'=>'marketplace_v16_install', 'Pickup Toko'=>'marketplace_store_pickup_install',
        'Kurir Toko'=>'marketplace_store_courier_install', 'Customer Service Marketplace'=>'marketplace_service_install',
        'WhatsApp Marketplace'=>'marketplace_wa_install',
    ];
    foreach($jobs as $label=>$fn){
        try{
            if(function_exists($fn)){ $fn($db); $log[]='OK INSTALLER: '.$label; }
            else $log[]='SKIP INSTALLER: '.$label.' (fungsi tidak ditemukan)';
        }catch(Throwable $e){ $log[]='GAGAL INSTALLER '.$label.': '.$e->getMessage(); }
    }
}

function vk_sys_upgrade(PDO $db): array
{
    $log=[];
    vk_sys_run_installers($db,$log);
    vk_sys_fix_checkout_integrity($db,$log);

    // Columns used by newer multi-branch/accounting/marketplace code but absent in older dumps.
    $columns = [
        ['products','brand_id','BIGINT NULL AFTER `category_id`'],
        ['products','weight_gram','INT NOT NULL DEFAULT 100 AFTER `unit`'],
        ['finance_transactions','branch_id','BIGINT NULL AFTER `user_id`'],
        ['debts','branch_id','BIGINT NULL AFTER `user_id`'],
        ['transaction_items','variant_id','BIGINT NULL AFTER `product_id`'],
        ['transaction_items','variant_name','VARCHAR(180) NULL AFTER `product_name`'],
        ['transaction_items','variant_sku','VARCHAR(120) NULL AFTER `variant_name`'],
        ['purchase_items','variant_id','BIGINT NULL AFTER `product_id`'],
        ['purchase_items','variant_name','VARCHAR(180) NULL AFTER `product_name`'],
        ['purchase_items','variant_sku','VARCHAR(120) NULL AFTER `variant_name`'],
        ['branch_stock_request_items','variant_id','BIGINT NULL AFTER `product_id`'],
        ['branch_stock_request_items','variant_name','VARCHAR(180) NULL AFTER `product_name`'],
        ['branch_stock_request_items','variant_sku','VARCHAR(120) NULL AFTER `variant_name`'],
        ['stock_opname_items','variant_id','BIGINT NULL AFTER `product_id`'],
        ['stock_opname_items','variant_name','VARCHAR(180) NULL AFTER `product_name`'],
        ['online_order_items','variant_id','BIGINT NULL AFTER `product_id`'],
        ['online_order_items','variant_name','VARCHAR(180) NULL AFTER `product_name`'],
        ['online_order_items','variant_sku','VARCHAR(120) NULL AFTER `variant_name`'],
        ['customer_debt_qris','txn_id','VARCHAR(120) NULL AFTER `order_id`'],
        ['marketplace_couriers','branch_id','BIGINT NULL'],
        ['marketplace_couriers','email','VARCHAR(190) NULL'],
        ['marketplace_couriers','vehicle_type',"VARCHAR(40) NOT NULL DEFAULT 'Motor'"],
        ['marketplace_couriers','vehicle_number','VARCHAR(80) NULL'],
        ['marketplace_couriers','status',"VARCHAR(20) NOT NULL DEFAULT 'active'"],
        ['marketplace_couriers','last_login_at','DATETIME NULL'],
        ['marketplace_couriers','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['marketplace_courier_assignments','assigned_by','BIGINT NULL'],
        ['marketplace_courier_assignments','accepted_at','DATETIME NULL'],
        ['marketplace_courier_assignments','picked_up_at','DATETIME NULL'],
        ['marketplace_courier_assignments','on_the_way_at','DATETIME NULL'],
        ['marketplace_courier_assignments','delivered_at','DATETIME NULL'],
        ['marketplace_courier_assignments','failed_at','DATETIME NULL'],
        ['marketplace_courier_assignments','returned_at','DATETIME NULL'],
        ['marketplace_courier_assignments','delivery_proof','VARCHAR(255) NULL'],
        ['marketplace_courier_assignments','recipient_name','VARCHAR(160) NULL'],
        ['marketplace_courier_assignments','delivery_note','TEXT NULL'],
        ['marketplace_courier_assignments','failure_reason','TEXT NULL'],
        ['marketplace_courier_assignments','latitude','DECIMAL(10,7) NULL'],
        ['marketplace_courier_assignments','longitude','DECIMAL(10,7) NULL'],
        ['marketplace_courier_assignments','location_updated_at','DATETIME NULL'],
        ['marketplace_courier_assignments','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['marketplace_courier_events','proof_path','VARCHAR(255) NULL'],
        // warehouses: dipakai installer & warehouse_main_id()/warehouse_all() tapi tidak ada di
        // daftar harapan lama -> halaman gudang bisa fatal "Unknown column is_main" pada dump tua.
        ['warehouses','phone','VARCHAR(50) NULL'],
        ['warehouses','address','TEXT NULL'],
        ['warehouses','is_main','TINYINT(1) NOT NULL DEFAULT 0'],
        ['warehouses','note','TEXT NULL'],
        // v17.0.24: foto kategori untuk kartu kategori storefront
        ['categories','image','VARCHAR(255) NULL'],
    ];
    foreach($columns as [$t,$c,$d]){
        try{ vk_sys_add_column($db,$t,$c,$d,$log); }catch(Throwable $e){ $log[]="GAGAL COLUMN {$t}.{$c}: ".$e->getMessage(); }
    }

    // Tracking compatibility table referenced by storefront. Safe even when a shipping provider is not configured.
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS shipments (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT NULL,
            awb_number VARCHAR(160) NULL,
            shipping_order_id VARCHAR(160) NULL,
            courier VARCHAR(100) NULL,
            service VARCHAR(100) NULL,
            status VARCHAR(80) NULL,
            status_detail VARCHAR(255) NULL,
            payload MEDIUMTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_shipments_order(order_id),
            INDEX idx_shipments_awb(awb_number),
            INDEX idx_shipments_shipping_order(shipping_order_id),
            INDEX idx_shipments_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $log[]='OK: tabel shipments';
    }catch(Throwable $e){$log[]='GAGAL shipments: '.$e->getMessage();}

    // Semua index di vk_sys_expected_indexes() harus bisa dibuat ulang dari halaman Doctor,
    // termasuk yang sebelumnya hanya ada di dalam CREATE TABLE installer (tabel sudah ada = tidak
    // pernah dibuat ulang) dan 5 index yang belum pernah didefinisikan di kode mana pun.


    // --- Kolom repair TURUNAN (dibuat otomatis dari definisi CREATE TABLE para installer) ---
    // Tabel yang sudah ada tidak pernah ditambah kolom oleh "CREATE TABLE IF NOT EXISTS", jadi
    // dump lama bisa kehilangan kolom yang dipakai halaman (fatal "Unknown column"). Daftar ini
    // diturunkan dengan audit/derive_columns.py + kolom yang dipakai kode rilis v17.0.16.
    $columnsTerdeteksi = [
        ['branch_stock_request_items','branch_stock_after','INT NULL'],
        ['branch_stock_request_items','branch_stock_before','INT NULL'],
        ['branch_stock_request_items','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['branch_stock_request_items','product_code','VARCHAR(80) NULL'],
        ['branch_stock_request_items','unit','VARCHAR(40) NOT NULL DEFAULT \'pcs\''],
        ['branch_stock_request_items','warehouse_stock_after','INT NULL'],
        ['branch_stock_request_items','warehouse_stock_before','INT NULL'],
        ['branch_stock_requests','action_note','TEXT NULL'],
        ['branch_stock_requests','approved_at','DATETIME NULL'],
        ['branch_stock_requests','approved_by','INT NULL'],
        ['branch_stock_requests','cancelled_at','DATETIME NULL'],
        ['branch_stock_requests','cancelled_by','INT NULL'],
        ['branch_stock_requests','fulfilled_at','DATETIME NULL'],
        ['branch_stock_requests','fulfilled_by','INT NULL'],
        ['branch_stock_requests','note','TEXT NULL'],
        ['branch_stock_requests','rejected_at','DATETIME NULL'],
        ['branch_stock_requests','rejected_by','INT NULL'],
        ['branch_stock_requests','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['branches','note','TEXT NULL'],
        ['branches','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['cashier_shifts','actual_cash','DECIMAL(15,2) NOT NULL DEFAULT 0.00'],
        ['cashier_shifts','cash_difference','DECIMAL(15,2) NOT NULL DEFAULT 0.00'],
        ['cashier_shifts','closing_note','TEXT NULL'],
        ['cashier_shifts','created_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['cashier_shifts','expected_cash','DECIMAL(15,2) NOT NULL DEFAULT 0.00'],
        ['cashier_shifts','location_name','VARCHAR(150) NULL'],
        ['cashier_shifts','opening_note','TEXT NULL'],
        ['cashier_shifts','total_refund','DECIMAL(15,2) NOT NULL DEFAULT 0.00'],
        ['cashier_shifts','total_transactions','INT NOT NULL DEFAULT 0'],
        ['cashier_shifts','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['customer_debt_qris','created_by','BIGINT NULL'],
        ['customer_debt_qris','note','TEXT NULL'],
        ['customer_debt_qris','paid_at','DATETIME NULL'],
        ['customer_debt_qris','raw_response','LONGTEXT NULL'],
        ['customer_debt_qris','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['customers','marketplace_last_login_at','DATETIME NULL'],
        ['customers','marketplace_password_hash','VARCHAR(255) NULL'],
        ['customers','marketplace_registered_at','DATETIME NULL'],
        ['customers','note','TEXT NULL'],
        ['customers','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['marketplace_brands','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['marketplace_product_variant_stocks','created_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['marketplace_product_variant_stocks','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['marketplace_product_variants','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['online_order_items','created_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['online_order_items','product_code','VARCHAR(100) NULL'],
        ['online_order_items','unit','VARCHAR(50) NULL'],
        ['online_orders','address','TEXT NOT NULL'],
        ['online_orders','awb_number','VARCHAR(120) NULL'],
        ['online_orders','cancelled_at','DATETIME NULL'],
        ['online_orders','city','VARCHAR(120) NULL'],
        ['online_orders','completed_at','DATETIME NULL'],
        ['online_orders','courier_code','VARCHAR(50) NOT NULL'],
        ['online_orders','courier_description','VARCHAR(255) NULL'],
        ['online_orders','courier_name','VARCHAR(120) NULL'],
        ['online_orders','courier_service','VARCHAR(100) NOT NULL'],
        ['online_orders','customer_note','TEXT NULL'],
        ['online_orders','destination_id','BIGINT NOT NULL'],
        ['online_orders','destination_label','VARCHAR(255) NOT NULL'],
        ['online_orders','district','VARCHAR(120) NULL'],
        ['online_orders','idempotency_key','VARCHAR(96) NULL'],
        ['online_orders','paid_at','DATETIME NULL'],
        ['online_orders','payment_fee','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['online_orders','payment_link','TEXT NULL'],
        ['online_orders','payment_provider','VARCHAR(40) NOT NULL DEFAULT \'pakasir\''],
        ['online_orders','payment_total','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['online_orders','points_awarded_at','DATETIME NULL'],
        ['online_orders','points_earned','INT NOT NULL DEFAULT 0'],
        ['online_orders','pos_invoice_number','VARCHAR(80) NULL'],
        ['online_orders','pos_recorded_at','DATETIME NULL'],
        ['online_orders','postal_code','VARCHAR(20) NULL'],
        ['online_orders','province','VARCHAR(120) NULL'],
        ['online_orders','public_token','VARCHAR(96) NOT NULL'],
        ['online_orders','recipient_email','VARCHAR(190) NULL'],
        ['online_orders','recipient_name','VARCHAR(160) NOT NULL'],
        ['online_orders','recipient_phone','VARCHAR(50) NOT NULL'],
        ['online_orders','shipped_at','DATETIME NULL'],
        ['online_orders','shipping_etd','VARCHAR(100) NULL'],
        ['online_orders','stock_released_at','DATETIME NULL'],
        ['online_orders','subdistrict','VARCHAR(120) NULL'],
        ['online_orders','total_weight_gram','INT NOT NULL DEFAULT 0'],
        ['online_orders','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['online_orders','voucher_code','VARCHAR(80) NULL'],
        ['online_orders','voucher_released_at','DATETIME NULL'],
        ['product_branch_stocks','created_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['product_branch_stocks','rack','VARCHAR(80) NULL'],
        ['product_branch_stocks','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['products','image','VARCHAR(255) NULL'],
        ['products','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['purchase_items','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['purchases','cancel_note','TEXT NULL'],
        ['purchases','cancelled_at','DATETIME NULL'],
        ['purchases','cancelled_by','INT NULL'],
        ['purchases','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['purchases','debt_amount','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['purchases','discount','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['purchases','note','TEXT NULL'],
        ['purchases','paid_amount','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['purchases','payment_status','ENUM(\'paid\',\'debt\') NOT NULL DEFAULT \'paid\''],
        ['purchases','shipping_cost','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['purchases','supplier_invoice','VARCHAR(120) NULL'],
        ['purchases','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['role_permissions','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['shipments','payload','MEDIUMTEXT NULL'],
        ['shipments','status_detail','VARCHAR(255) NULL'],
        ['shipments','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['stock_opnames','user_id','INT NULL'],
        ['transactions','reference_id','BIGINT NULL'],
        ['transactions','reference_type','VARCHAR(40) NULL'],
        ['transactions','shipping_cost','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['warehouse_stocks','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['warehouse_stocks','purchase_price','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['warehouse_stocks','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['warehouse_variant_stocks','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['warehouse_variant_stocks','purchase_price','DECIMAL(15,2) NOT NULL DEFAULT 0'],
        ['warehouse_variant_stocks','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
        ['warehouses','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['warehouses','updated_at','TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'],
    ];
    foreach($columnsTerdeteksi as [$t,$c,$d]){
        try{ vk_sys_add_column($db,$t,$c,$d,$log); }catch(Throwable $e){ $log[]="GAGAL KOLOM TURUNAN {$t}.{$c}: ".$e->getMessage(); }
    }
    $indexJobs = [
        ['purchase_items','idx_purchase_items_purchase','`purchase_id`',false],
        ['purchase_items','idx_purchase_items_product','`product_id`',false],
        ['product_branch_stocks','uniq_product_branch','`branch_id`,`product_id`',true],
        ['product_branch_stocks','idx_pbs_stock','`stock`',false],
        ['marketplace_product_variants','idx_mp_variants_product','`product_id`,`is_active`,`sort_order`',false],
        ['marketplace_product_variants','uniq_mp_variant_sku','`sku`',true],
        ['marketplace_product_variant_stocks','uniq_mp_variant_branch','`variant_id`,`branch_id`',true],
        ['marketplace_product_variant_stocks','idx_mp_variant_stock_branch','`branch_id`,`stock`',false],
        ['warehouse_variant_stocks','uniq_warehouse_variant','`warehouse_id`,`variant_id`',true],
        ['warehouse_variant_stocks','idx_wvs_product','`product_id`',false],
        ['warehouse_variant_stocks','idx_wvs_stock','`stock`',false],
        ['branch_stock_request_items','idx_bsri_request','`request_id`',false],
        ['branch_stock_request_items','idx_bsri_product','`product_id`',false],
        ['stock_opname_items','idx_opname_items_opname','`opname_id`',false],
        ['stock_opname_items','idx_opname_items_product','`product_id`',false],
        ['stock_opname_items','uniq_opname_product_variant','`opname_id`,`product_id`,`variant_id`',true],
        ['customer_debt_payments','idx_customer_debt_payments_debt','`debt_id`',false],
        ['customer_debt_payments','idx_customer_debt_payments_customer','`customer_id`',false],
        ['customer_debt_payments','idx_customer_debt_payments_branch','`branch_id`',false],
        ['products','idx_products_name','`name`',false],
        ['products','idx_products_stock','`stock`',false],
        ['products','idx_products_active','`is_active`',false],
        ['transaction_items','idx_transaction_items_transaction','`transaction_id`',false],
        ['transaction_items','idx_transaction_items_product','`product_id`',false],
        ['products','idx_products_brand','`brand_id`',false],
        ['transactions','idx_transactions_status_date','`status`,`transaction_date`',false],
        ['transaction_items','idx_transaction_items_variant','`variant_id`',false],
        ['purchase_items','idx_purchase_items_variant','`variant_id`',false],
        ['branch_stock_request_items','idx_bsri_variant','`variant_id`',false],
        ['finance_transactions','idx_finance_branch','`branch_id`',false],
        ['debts','idx_debts_branch','`branch_id`',false],
        ['customer_debt_payments','idx_customer_debt_payments_created','`created_at`',false],
        ['customer_debt_qris','idx_customer_debt_qris_txn','`txn_id`',false],
        ['marketplace_couriers','uniq_marketplace_courier_username','`username`',true],
        ['marketplace_couriers','idx_marketplace_courier_status','`status`',false],
        ['marketplace_couriers','idx_marketplace_courier_branch','`branch_id`',false],
        ['marketplace_courier_assignments','uniq_store_courier_order','`order_id`',true],
        ['marketplace_courier_assignments','idx_store_courier_courier','`courier_id`,`status`',false],
        ['marketplace_courier_assignments','idx_store_courier_status','`status`,`assigned_at`',false],
        ['marketplace_courier_events','idx_store_courier_event_assignment','`assignment_id`,`created_at`',false],
        ['marketplace_courier_events','idx_store_courier_event_order','`order_id`,`created_at`',false],
    ];
    foreach($indexJobs as [$t,$i,$cols,$unique]){
        try{vk_sys_add_index($db,$t,$i,$cols,$unique,$log);}catch(Throwable $e){$log[]="GAGAL INDEX {$t}.{$i}: ".$e->getMessage();}
    }

    // Normalize legacy NULL/zero variant ids where unique-key logic expects NULL for non-variant products.
    try{
        if(vk_sys_column_exists($db,'stock_opname_items','variant_id')){
            $db->exec("UPDATE stock_opname_items SET variant_id=NULL WHERE variant_id=0");
            $log[]='OK: normalisasi variant_id Stock Opname';
        }
    }catch(Throwable $e){$log[]='INFO: normalisasi Stock Opname dilewati: '.$e->getMessage();}

    try{ foreach(vk_sys_migrate_legacy_uploads($db) as $line) $log[]=$line; }
    catch(Throwable $e){ $log[]='INFO MEDIA: migrasi file lama dilewati: '.$e->getMessage(); }

    return $log;
}



function vk_sys_bridge_audit(): array
{
    $root = dirname(__DIR__, 4);
    $checks = [
        ['label'=>'CI4 catch-all memakai dispatch URI utuh','file'=>$root.'/app/Config/Routes.php','needle'=>'LegacyController::dispatch'],
        ['label'=>'CI4 multi-segment route compatibility aktif','file'=>$root.'/app/Config/Routing.php','needle'=>'public bool $multipleSegmentsOneParam = true;'],
        ['label'=>'Legacy bridge emulasi SCRIPT_NAME/PHP_SELF','file'=>$root.'/app/Controllers/LegacyController.php','needle'=>'VAPEKONG_LEGACY_TARGET'],
        ['label'=>'Base URL mengenali folder virtual courier','file'=>$root.'/legacy/app/Helpers/helpers.php','needle'=>"'/courier/'"],
        ['label'=>'Root rewrite ke front controller CI4','file'=>$root.'/.htaccess','needle'=>'RewriteRule ^ index.php [L]'],
        ['label'=>'Media compatibility endpoint aktif','file'=>$root.'/media.php','needle'=>'Vapekong media compatibility endpoint v17.0.4'],
    ];
    $out=[];
    foreach($checks as $c){
        $exists=is_file($c['file']);
        $content=$exists?(string)@file_get_contents($c['file']):'';
        $ok=$exists && str_contains($content,$c['needle']);
        $out[]=['label'=>$c['label'],'ok'=>$ok,'file'=>$c['file']];
    }
    return $out;
}

function vk_sys_clean_route_names(): array
{
    return [
        '', 'adm','admin','produk','masuk','daftar','akun','profil','pesanan-saya','alamat-saya',
        'wishlist','notifikasi','bantuan-pesanan','keluar-akun','keranjang','checkout','tracking',
        'kurir','kurir/login','kurir/logout','kurir/selesai','kurir/riwayat','kurir/profil','kurir/aksi','login','staff/login','admin/login','logout'
    ];
}

function vk_sys_route_target_ok(string $value, string $publicRoot): bool
{
    $value=trim(html_entity_decode($value,ENT_QUOTES,'UTF-8'));
    if($value==='' || preg_match('~^(?:https?:|mailto:|tel:|javascript:|#)~i',$value)) return true;
    $path=(string)(parse_url($value,PHP_URL_PATH)??'');
    $path=ltrim($path,'/');
    if(in_array(rtrim($path,'/'),vk_sys_clean_route_names(),true)) return true;
    if(in_array($path,['produk/','bantuan-pesanan/','pesanan/','kurir/paket/'],true)) return true;
    if(preg_match('~^(?:produk/[0-9]+(?:-[A-Za-z0-9-]+)?|bantuan-pesanan/[0-9]+|pesanan/[A-Za-z0-9-]+|kurir/paket/[0-9]+)$~',$path)) return true;
    if($path==='') return true;
    $target=$publicRoot.'/'.$path;
    return is_file($target) || is_dir($target) || (is_dir(dirname($target)) && str_ends_with($path,'/'));
}

function vk_sys_navigation_audit(): array
{
    $publicRoot=dirname(__DIR__,3).'/public';
    $files=[];
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicRoot,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){ if($file->isFile() && strtolower($file->getExtension())==='php') $files[]=$file->getPathname(); }

    $allTargets=[];
    $missing=[];
    foreach($files as $file){
        $src=(string)@file_get_contents($file);
        if($src==='') continue;
        if(preg_match_all('~legacy_base_url\\(\\s*([\\\'\"])([^\\\'\"]*)\\1~',$src,$m,PREG_SET_ORDER)){
            foreach($m as $row){
                $value=$row[2];
                // Dynamic concatenations are checked by their static prefix only when it is a complete path.
                if(str_contains($value,'$')) continue;
                $key=$value;
                $allTargets[$key]=true;
                if(!vk_sys_route_target_ok($value,$publicRoot)) $missing[$key]=str_replace($publicRoot.'/','',$file);
            }
        }
    }

    $sidebar=$publicRoot.'/layouts/admin-sidebar.php';
    $menu=[];
    if(is_file($sidebar)){
        $src=(string)file_get_contents($sidebar);
        if(preg_match_all('~menu_link\\(\\s*([\\\'\"])([^\\\'\"]+)\\1\\s*,\\s*([\\\'\"])([^\\\'\"]+)\\3\\s*,\\s*([\\\'\"])([^\\\'\"]+)\\5\\s*,\\s*([\\\'\"])([^\\\'\"]+)\\7~',$src,$m,PREG_SET_ORDER)){
            foreach($m as $row){
                $permission=$row[2];$url=$row[4];$label=$row[8];
                $menu[]=['label'=>$label,'permission'=>$permission,'url'=>$url,'ok'=>vk_sys_route_target_ok($url,$publicRoot)];
            }
        }
    }

    return [
        'php_files'=>count($files),
        'unique_targets'=>count($allTargets),
        'missing_targets'=>$missing,
        'menu'=>$menu,
        'menu_total'=>count($menu),
        'menu_bad'=>count(array_filter($menu,fn($r)=>!$r['ok'])),
    ];
}



function vk_sys_media_storage_audit(PDO $db): array
{
    $refs=[];
    $add=function(string $path,string $source) use (&$refs){
        $path=vk_sys_normalize_media_path($path);
        if($path==='' || preg_match('~^(?:https?:|data:|blob:)~i',$path) || !str_starts_with($path,'uploads/')) return;
        $refs[$path][]=$source;
    };

    $sources=[
        ['products','id','image'],['marketplace_product_images','id','image_path'],['marketplace_product_variants','id','image_path'],
        ['marketplace_brands','id','logo'],['marketplace_banners','id','image_desktop'],['marketplace_banners','id','image_mobile'],
        ['product_bundles','id','image'],['users','id','avatar'],['marketplace_courier_assignments','id','delivery_proof'],['marketplace_courier_events','id','proof_path'],
    ];
    foreach($sources as [$table,$idCol,$col]){
        if(!vk_sys_table_exists($db,$table) || !vk_sys_column_exists($db,$table,$col)) continue;
        try{$q=$db->query("SELECT `{$idCol}` AS rid, `{$col}` AS media_path FROM `{$table}` WHERE `{$col}` IS NOT NULL AND TRIM(`{$col}`)<>''");foreach($q->fetchAll() as $r)$add((string)$r['media_path'],$table.'#'.$r['rid'].'.'.$col);}catch(Throwable $e){}
    }
    if(vk_sys_table_exists($db,'settings')){
        try{$q=$db->query("SELECT setting_key,setting_value FROM settings WHERE setting_value LIKE '%uploads/%'");foreach($q->fetchAll() as $r){$value=(string)$r['setting_value'];if(preg_match_all('~(?:legacy/public/|public/|pos/public/|pos/)?uploads/[A-Za-z0-9_./-]+\\.(?:jpg|jpeg|png|webp|gif|svg|pdf|mp4|webm|mp3|wav|ogg)~i',$value,$mm)){foreach($mm[0] as $path)$add($path,'settings.'.$r['setting_key']);}else{$add($value,'settings.'.$r['setting_key']);}}}catch(Throwable $e){}
    }

    $missing=[];$locations=[];$recoverable=0;$canonical=0;
    foreach($refs as $path=>$sourcesForPath){
        $found=vk_sys_media_find($path);
        if(!$found){$missing[]=['path'=>$path,'source'=>implode(', ',array_slice($sourcesForPath,0,3))];continue;}
        $loc=(string)$found['location'];$locations[$loc]=($locations[$loc]??0)+1;
        if($loc==='root')$canonical++;else $recoverable++;
    }

    $diskFiles=[];$diskBytes=0;$root=dirname(__DIR__,4);
    $roots=[$root.'/uploads',$root.'/legacy/public/uploads',$root.'/public/uploads',$root.'/pos/public/uploads',$root.'/pos/uploads'];
    foreach($roots as $uploads){
        if(!is_dir($uploads))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){if(!$file->isFile())continue;$rel='uploads/'.str_replace('\\','/',substr($file->getPathname(),strlen($uploads)+1));if(in_array(basename($rel),['.gitkeep','.htaccess'],true))continue;if(!isset($diskFiles[$rel]))$diskBytes+=(int)$file->getSize();$diskFiles[$rel]=true;}
    }
    $unreferenced=[];foreach(array_keys($diskFiles) as $path){if(!isset($refs[$path]))$unreferenced[]=$path;}

    return [
        'referenced'=>count($refs),'references'=>$refs,'missing_count'=>count($missing),'missing'=>array_slice($missing,0,100),
        'disk_files'=>count($diskFiles),'disk_bytes'=>$diskBytes,'unreferenced_count'=>count($unreferenced),'unreferenced'=>array_slice($unreferenced,0,100),
        'canonical_count'=>$canonical,'recoverable_count'=>$recoverable,'locations'=>$locations,
    ];
}

function vk_sys_reset_missing_media(PDO $db): array
{
    $log=[]; $publicRoot=dirname(__DIR__,3).'/public';
    foreach(['marketplace_logo','marketplace_favicon','marketplace_hero_image','marketplace_promo_image'] as $key){
        $value=function_exists('setting')?(string)setting($key,''):'';
        if($value==='' || preg_match('~^https?://~i',$value)) continue;
        $rel=vk_sys_normalize_media_path($value);
        if(!vk_sys_media_find($rel)){
            $s=$db->prepare("UPDATE settings SET setting_value='', updated_at=NOW() WHERE setting_key=?");
            try{$s->execute([$key]);}catch(Throwable $e){
                $s=$db->prepare("UPDATE settings SET setting_value='' WHERE setting_key=?");$s->execute([$key]);
            }
            $log[]='RESET media hilang: '.$key;
        }
    }
    if(!$log)$log[]='OK: tidak ada path media marketplace yang perlu direset.';
    return $log;
}
