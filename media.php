<?php
/**
 * Vapekong media compatibility endpoint v17.0.4
 * Serves legacy upload files that may still live in /public, /pos/public,
 * or /legacy/public after the CI4 migration. No PHP/script files are allowed.
 */
$raw = (string)($_GET['path'] ?? '');
$raw = trim(str_replace('\\', '/', urldecode($raw)));
$raw = ltrim($raw, '/');
foreach (['legacy/public/', 'public/', 'pos/public/', 'pos/'] as $prefix) {
    if (str_starts_with($raw, $prefix)) { $raw = substr($raw, strlen($prefix)); break; }
}
$parts=[];
foreach (explode('/', $raw) as $part) {
    if ($part === '' || $part === '.') continue;
    if ($part === '..') { http_response_code(400); exit('Invalid media path'); }
    $parts[]=$part;
}
$relative=implode('/',$parts);
if ($relative === '' || !str_starts_with($relative, 'uploads/')) { http_response_code(404); exit; }
$ext=strtolower(pathinfo($relative, PATHINFO_EXTENSION));
$allowed=['jpg','jpeg','png','webp','gif','svg','ico','pdf','mp4','webm','mov','mp3','wav','ogg'];
if (!in_array($ext,$allowed,true)) { http_response_code(403); exit; }
$root=__DIR__;
$candidates=[
    $root.'/'.$relative,
    $root.'/legacy/public/'.$relative,
    $root.'/public/'.$relative,
    $root.'/pos/public/'.$relative,
    $root.'/pos/'.$relative,
];
$file=null;
foreach($candidates as $candidate){ if(is_file($candidate) && is_readable($candidate)){ $file=$candidate; break; } }
if(!$file){ http_response_code(404); header('Cache-Control: no-store'); exit; }
$mime='application/octet-stream';
if(function_exists('finfo_open')){ $fi=@finfo_open(FILEINFO_MIME_TYPE); if($fi){$det=@finfo_file($fi,$file); if(is_string($det)&&$det!=='')$mime=$det; @finfo_close($fi);} }
if($mime==='application/octet-stream'){
    $mimeMap=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml','ico'=>'image/x-icon','pdf'=>'application/pdf','mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg'];
    $mime=$mimeMap[$ext]??$mime;
}
$size=(int)filesize($file);$mtime=(int)filemtime($file);$etag='"'.sha1($file.'|'.$size.'|'.$mtime).'"';
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
header('Content-Type: '.$mime);
header('Content-Length: '.$size);
header('Cache-Control: public, max-age=604800, immutable');
header('ETag: '.$etag);
header('Last-Modified: '.gmdate('D, d M Y H:i:s',$mtime).' GMT');
header('X-Content-Type-Options: nosniff');
readfile($file);
