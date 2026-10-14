<?php
require_once __DIR__.'/_bootstrap.php';
$courier=marketplace_courier_require();
$courier=marketplace_courier_refresh_session($db);
if(!$courier)legacy_redirect('kurir/login');
if(!is_post()||!CSRF::verify()){flash('error','Token keamanan tidak valid.');legacy_redirect('kurir');}
$id=max(0,(int)($_POST['id']??0));
$action=trim((string)($_POST['action']??''));
try{
    if($id<=0) throw new RuntimeException('Paket tidak valid.');
    $lat=isset($_POST['latitude'])&&$_POST['latitude']!==''?(float)$_POST['latitude']:null;
    $lng=isset($_POST['longitude'])&&$_POST['longitude']!==''?(float)$_POST['longitude']:null;
    if($action==='location'){
        if($lat===null||$lng===null) throw new RuntimeException('Lokasi belum didapat. Aktifkan GPS/izin lokasi lalu coba lagi.');
        $job=marketplace_store_courier_assignment_by_id($db,$id,(int)$courier['id']);
        if(!$job) throw new RuntimeException('Paket tidak ditemukan atau bukan tugas Anda.');
        marketplace_courier_update_location($db,$id,(int)$courier['id'],$lat,$lng);
        flash('success','Lokasi terakhir berhasil diperbarui.');
    }else{
        if($lat!==null&&$lng!==null) marketplace_courier_update_location($db,$id,(int)$courier['id'],$lat,$lng);
        marketplace_courier_update_status($db,$id,(int)$courier['id'],$action,$_POST,$_FILES['proof']??null);
        flash('success','Status paket berhasil diperbarui.');
    }
}catch(Throwable $e){flash('error',$e->getMessage());}
legacy_redirect('kurir/paket/'.$id);
