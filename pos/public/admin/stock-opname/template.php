<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/DataExchange/Xlsx.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/Marketplace.php';
require_once __DIR__ . '/../branches/_helpers.php';
Auth::requireRole(['super_admin','owner']);
$db=Database::connect();marketplace_install($db);branch_install($db);$branchId=branch_current_id($db);$branch=branch_current($db);
$products=$db->query("SELECT p.id,p.name,p.product_code,p.unit,p.stock,p.purchase_price,p.selling_price,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.name,p.id")->fetchAll();
$data=[];
foreach($products as $p){
 $bid=marketplace_v15_product_brand_id($db,(int)$p['id']);$brand='';if($bid>0){$b=marketplace_v15_brand($db,$bid);$brand=(string)($b['name']??'');}
 $vars=marketplace_v15_variants($db,(int)$p['id'],$branchId,true);
 if($vars){foreach($vars as $v){$label=trim((string)($v['display_name']??$v['name']));$data[]=['name'=>$p['name'].' - '.$label,'variant'=>$label,'brand'=>$brand,'category'=>$p['category_name']??'','unit'=>$p['unit']?:'pcs','system_stock'=>(int)$v['stock'],'physical_stock'=>'','note'=>'','product_id'=>(int)$p['id'],'variant_id'=>(int)$v['id']];}}
 else{$stock=(int)$p['stock'];try{$q=$db->prepare('SELECT stock FROM product_branch_stocks WHERE branch_id=? AND product_id=?');$q->execute([$branchId,$p['id']]);$x=$q->fetchColumn();if($x!==false)$stock=(int)$x;}catch(Throwable $e){}$data[]=['name'=>$p['name'],'variant'=>'','brand'=>$brand,'category'=>$p['category_name']??'','unit'=>$p['unit']?:'pcs','system_stock'=>$stock,'physical_stock'=>'','note'=>'','product_id'=>(int)$p['id'],'variant_id'=>0];}
}
$headers=['name','variant','brand','category','unit','system_stock','physical_stock','note','product_id','variant_id'];
$rows=[[[ 'value'=>'TEMPLATE STOCK OPNAME - '.($branch['name']??'Cabang'),'style'=>1,'height'=>28]],[[ 'value'=>'Produk bervarian ditampilkan per varian. Isi physical_stock. Kolom product_id/variant_id jangan diubah.','style'=>5]],array_map(fn($v)=>['value'=>$v,'style'=>2],$headers)];
foreach($data as $r){$rows[]=[['value'=>$r['name'],'style'=>5],['value'=>$r['variant'],'style'=>5],['value'=>$r['brand'],'style'=>5],['value'=>$r['category'],'style'=>5],['value'=>$r['unit'],'style'=>10],['value'=>$r['system_stock'],'type'=>'number','style'=>4],['value'=>'','style'=>4],['value'=>'','style'=>5],['value'=>$r['product_id'],'type'=>'number','style'=>4],['value'=>$r['variant_id'],'type'=>'number','style'=>4]];}
Auth::log('download_stock_opname_template','Download template stock opname variant-aware: '.count($data).' baris');
KpXlsx::download('template-stock-opname-'.date('Ymd-His').'.xlsx',[['name'=>'Stock Opname','rows'=>$rows,'widths'=>[48,22,20,22,10,15,16,34,12,12],'freeze_rows'=>3,'auto_filter'=>true,'filter_row'=>3,'merges'=>['A1:J1','A2:J2']]]);
