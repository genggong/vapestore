<?php

require_once __DIR__ . '/asset_helpers.php';

function app_config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../Config/app.php';
    }
    return $config[$key] ?? $default;
}


function legacy_project_root(): string
{
    return dirname(__DIR__, 3);
}

function legacy_normalize_upload_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || preg_match('~^(?:https?:|data:|blob:)~i', $path)) return $path;
    $path = (string)(parse_url($path, PHP_URL_PATH) ?? $path);
    $path = ltrim($path, '/');
    foreach (['legacy/public/', 'public/', 'pos/public/', 'pos/'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
            break;
        }
    }
    if (!str_starts_with($path, 'uploads/')) return $path;
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') return '';
        $parts[] = $part;
    }
    return implode('/', $parts);
}

function legacy_upload_candidates(string $relative): array
{
    $relative = legacy_normalize_upload_path($relative);
    if ($relative === '' || !str_starts_with($relative, 'uploads/')) return [];
    $root = legacy_project_root();
    return [
        'root' => $root . '/' . $relative,
        'legacy' => $root . '/legacy/public/' . $relative,
        'public' => $root . '/public/' . $relative,
        'pos_public' => $root . '/pos/public/' . $relative,
        'pos' => $root . '/pos/' . $relative,
    ];
}

function legacy_upload_public_url(string $path, string $baseUrl): string
{
    if ($path === '' || preg_match('~^(?:https?:|data:|blob:)~i', $path)) return $path;
    $relative = legacy_normalize_upload_path($path);
    if ($relative === '' || !str_starts_with($relative, 'uploads/')) return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $candidates = legacy_upload_candidates($relative);
    if (!empty($candidates['root']) && is_file($candidates['root'])) {
        $v = @filemtime($candidates['root']);
        return rtrim($baseUrl, '/') . '/' . $relative . ($v ? '?v=' . $v : '');
    }
    foreach ($candidates as $key => $candidate) {
        if ($key === 'root') continue;
        if (is_file($candidate)) {
            $v = @filemtime($candidate);
            return rtrim($baseUrl, '/') . '/media.php?path=' . rawurlencode($relative) . ($v ? '&v=' . $v : '');
        }
    }
    return rtrim($baseUrl, '/') . '/' . $relative;
}

function legacy_base_url(string $path = ''): string
{
    $configured = trim((string) app_config('base_url', ''));

    if ($configured !== '') {
        $baseUrl = rtrim($configured, '/');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

        // Clean storefront routes may be internally rewritten to /public/* by Apache.
        // Always resolve the application root, not the current /store or /admin directory.
        $basePath = '';
        $rootResolved = false;
        $markers = ['/public/', '/store/', '/admin/', '/courier/'];
        foreach ($markers as $marker) {
            $pos = strpos($script, $marker);
            if ($pos !== false) {
                $basePath = substr($script, 0, $pos);
                $rootResolved = true;
                break;
            }
        }

        if (!$rootResolved) {
            if (preg_match('~^(.*?)/public(?:/|$)~', $script, $match)) {
                $basePath = $match[1] ?? '';
            } else {
                $dir = str_replace('\\', '/', dirname($script));
                $basePath = ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');
            }
        }

        $baseUrl = rtrim($scheme . '://' . $host . $basePath, '/');
    }

    $path = ltrim($path, '/');
    if ($path !== '' && (str_starts_with($path, 'uploads/') || str_starts_with($path, 'public/uploads/') || str_starts_with($path, 'legacy/public/uploads/') || str_starts_with($path, 'pos/public/uploads/') || str_starts_with($path, 'pos/uploads/'))) {
        return legacy_upload_public_url($path, $baseUrl);
    }
    return $path ? $baseUrl . '/' . $path : $baseUrl;
}

function legacy_redirect(string $path)
{
    header('Location: ' . legacy_base_url($path));
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($number): string
{
    return 'Rp ' . number_format((float) $number, 0, ',', '.');
}


function payment_method_label(?string $method): string
{
    $method = strtolower(trim((string) $method));
    $labels = [
        'cash' => 'Cash',
        'cash_debt' => 'Cash + Piutang',
        'qris' => 'QRIS',
        'qris_pakasir' => 'QRIS Otomatis',
        'qris_manual' => 'QRIS Manual',
        'transfer' => 'Transfer',
    ];
    return $labels[$method] ?? ($method !== '' ? strtoupper(str_replace('_', ' ', $method)) : '-');
}

function now_datetime(): string
{
    return date('Y-m-d H:i:s');
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function legacy_old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash(string $key, $message = null)
{
    if ($message === null) {
        if (!empty($_SESSION['_flash'][$key])) {
            $msg = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $msg;
        }
        return null;
    }
    $_SESSION['_flash'][$key] = $message;
}

function set_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function setting(string $key, $default = null)
{
    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row['setting_value'] ?? $default;
    } catch (Throwable $e) {
        return $default;
    }
}


function maintenance_is_enabled(): bool
{
    return strtolower((string) setting('maintenance_mode', 'off')) === 'on';
}

function maintenance_allowed_role(): bool
{
    if (!class_exists('Auth')) return false;
    $role = Auth::role();
    return in_array($role, ['super_admin', 'owner'], true);
}

function maintenance_should_bypass(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    $allowed = [
        '/maintenance.php',
        '/login.php',
        '/logout.php',
        '/admin/settings/index.php',
        '/courier/',
        '/kurir',
        '/assets/',
        '/uploads/',
        '/pwa/',
        '/manifest',
        '/service-worker',
        '/favicon',
    ];

    foreach ($allowed as $path) {
        if (strpos($uri, $path) !== false || strpos($script, $path) !== false) {
            return true;
        }
    }

    return maintenance_allowed_role();
}

function maintenance_guard(): void
{
    if (!maintenance_is_enabled()) return;
    if (maintenance_should_bypass()) return;

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || strpos($accept, 'application/json') !== false;

    if ($isAjax) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => setting('maintenance_message', 'Sistem sedang maintenance. Silakan coba lagi nanti.'),
            'maintenance' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . legacy_base_url('maintenance.php'));
    exit;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'item-' . time();
}

function pagination_meta(int $total, int $page, int $perPage): array
{
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $lastPage));
    $offset = ($page - 1) * $perPage;
    return compact('total', 'page', 'perPage', 'lastPage', 'offset');
}

function legacy_image_upload_error_message(int $error): string
{
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran gambar melebihi upload_max_filesize hosting.',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar melebihi batas form.',
        UPLOAD_ERR_PARTIAL => 'Upload gambar hanya terkirim sebagian.',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file gambar yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary PHP tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'Hosting gagal menulis file upload.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan extension PHP.',
    ];
    return $messages[$error] ?? ('Upload gambar gagal. Kode: ' . $error);
}

function legacy_image_create_resource(string $file, int $type)
{
    try {
        if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($file);
        if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) return @imagecreatefrompng($file);
        if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($file);
    } catch (Throwable $e) {}
    return false;
}

function legacy_image_fix_orientation($image, string $file, int $type)
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data') || !function_exists('imagerotate')) return $image;
    try {
        $exif = @exif_read_data($file);
        $orientation = (int)($exif['Orientation'] ?? 1);
        if ($orientation === 3) return @imagerotate($image, 180, 0) ?: $image;
        if ($orientation === 6) return @imagerotate($image, -90, 0) ?: $image;
        if ($orientation === 8) return @imagerotate($image, 90, 0) ?: $image;
    } catch (Throwable $e) {}
    return $image;
}

/**
 * Simpan upload gambar dengan resize + kompresi server-side.
 * - Output WEBP bila GD mendukung imagewebp (hemat storage hosting).
 * - Fallback mempertahankan format asli bila GD/WebP tidak tersedia.
 * - Path return selalu relatif `uploads/...` agar kompatibel dengan media.php.
 */
function legacy_store_optimized_image(
    array $file,
    string $relativeDir,
    string $prefix = 'image',
    int $maxInputBytes = 8388608,
    int $maxWidth = 1600,
    int $maxHeight = 1600,
    int $quality = 78
): ?string {
    if (empty($file['name']) || empty($file['tmp_name'])) return null;

    $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) throw new Exception(legacy_image_upload_error_message($error));
    if (($file['size'] ?? 0) > $maxInputBytes) throw new Exception('Ukuran gambar maksimal ' . round($maxInputBytes / 1048576, 1) . 'MB.');

    $tmp = (string)$file['tmp_name'];
    $info = @getimagesize($tmp);
    $type = (int)($info[2] ?? 0);
    $width = max(0, (int)($info[0] ?? 0));
    $height = max(0, (int)($info[1] ?? 0));
    $sourceExt = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp'][$type] ?? null;
    if (!$sourceExt || $width <= 0 || $height <= 0) throw new Exception('File bukan gambar JPG, PNG, atau WEBP yang valid.');

    $relativeDir = trim(str_replace('\\','/',$relativeDir), '/');
    if (!str_starts_with($relativeDir, 'uploads/')) $relativeDir = 'uploads/' . $relativeDir;
    if (str_contains($relativeDir, '..')) throw new Exception('Folder upload tidak valid.');

    $root = legacy_project_root();
    $dir = $root . '/' . $relativeDir;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $dir = $root . '/legacy/public/' . $relativeDir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new Exception('Folder ' . $relativeDir . ' tidak dapat dibuat. Periksa permission 755/775.');
        }
    }
    if (!is_writable($dir)) throw new Exception('Folder upload tidak writable: ' . $relativeDir);

    $canGd = function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
    $canWebp = $canGd && function_exists('imagewebp');
    $outputExt = $canWebp ? 'webp' : $sourceExt;
    $filename = preg_replace('/[^a-z0-9_-]+/i','-',trim($prefix)) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $outputExt;
    $target = $dir . '/' . $filename;

    // Hindari OOM di shared hosting untuk foto ekstrem. File tetap disimpan valid,
    // tetapi tanpa decode jika estimasi pixel terlalu besar.
    $pixelCount = $width * $height;
    if (!$canGd || $pixelCount > 20000000) {
        if (!move_uploaded_file($tmp, $target)) throw new Exception('Gagal menyimpan gambar ke hosting.');
        @chmod($target, 0644);
        return $relativeDir . '/' . $filename;
    }

    $source = legacy_image_create_resource($tmp, $type);
    if (!$source) {
        // Jika codec tertentu tidak tersedia di GD, jangan gagalkan upload.
        $outputExt = $sourceExt;
        $filename = preg_replace('/[^a-z0-9_-]+/i','-',trim($prefix)) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $outputExt;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) throw new Exception('Gagal menyimpan gambar ke hosting.');
        @chmod($target, 0644);
        return $relativeDir . '/' . $filename;
    }

    $source = legacy_image_fix_orientation($source, $tmp, $type);
    $srcW = imagesx($source); $srcH = imagesy($source);
    $scale = min(1, $maxWidth / max(1,$srcW), $maxHeight / max(1,$srcH));
    $dstW = max(1, (int)round($srcW * $scale));
    $dstH = max(1, (int)round($srcH * $scale));
    $dest = imagecreatetruecolor($dstW, $dstH);
    if (!$dest) { imagedestroy($source); throw new Exception('Gagal menyiapkan kompresi gambar.'); }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $dstW, $dstH, $transparent);
    } else {
        $white = imagecolorallocate($dest, 255, 255, 255);
        imagefilledrectangle($dest, 0, 0, $dstW, $dstH, $white);
    }
    imagecopyresampled($dest, $source, 0,0,0,0,$dstW,$dstH,$srcW,$srcH);

    $saved = false;
    if ($canWebp) $saved = @imagewebp($dest, $target, max(45,min(92,$quality)));
    elseif ($type === IMAGETYPE_JPEG && function_exists('imagejpeg')) $saved = @imagejpeg($dest, $target, max(45,min(92,$quality)));
    elseif ($type === IMAGETYPE_PNG && function_exists('imagepng')) $saved = @imagepng($dest, $target, 7);
    elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) $saved = @imagewebp($dest, $target, max(45,min(92,$quality)));

    imagedestroy($dest); imagedestroy($source);
    if (!$saved || !is_file($target)) throw new Exception('Gagal mengompres/menyimpan gambar di hosting.');
    @chmod($target, 0644);
    return $relativeDir . '/' . $filename;
}

/** Optimasi gambar existing tanpa mengubah path/extension database. */
function legacy_optimize_existing_image_file(string $absolutePath, int $maxWidth = 1600, int $maxHeight = 1600, int $quality = 78): array
{
    $before = is_file($absolutePath) ? (int)filesize($absolutePath) : 0;
    if ($before <= 0) return ['ok'=>false,'changed'=>false,'before'=>0,'after'=>0,'message'=>'file tidak ada'];
    $info = @getimagesize($absolutePath); $type=(int)($info[2]??0);
    if (!in_array($type,[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'format dilewati'];
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'GD tidak aktif'];
    $w=(int)$info[0];$h=(int)$info[1];
    if ($w*$h > 20000000) return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'resolusi terlalu besar untuk shared hosting'];
    if ($before < 220*1024 && $w <= $maxWidth && $h <= $maxHeight) return ['ok'=>true,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'sudah ringan'];
    $src=legacy_image_create_resource($absolutePath,$type); if(!$src)return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'codec GD tidak tersedia'];
    $srcW=imagesx($src);$srcH=imagesy($src);$scale=min(1,$maxWidth/max(1,$srcW),$maxHeight/max(1,$srcH));$dstW=max(1,(int)round($srcW*$scale));$dstH=max(1,(int)round($srcH*$scale));
    $dst=imagecreatetruecolor($dstW,$dstH);if(!$dst){imagedestroy($src);return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'alokasi gambar gagal'];}
    if($type===IMAGETYPE_PNG||$type===IMAGETYPE_WEBP){imagealphablending($dst,false);imagesavealpha($dst,true);$tr=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$dstW,$dstH,$tr);}else{$white=imagecolorallocate($dst,255,255,255);imagefilledrectangle($dst,0,0,$dstW,$dstH,$white);}
    imagecopyresampled($dst,$src,0,0,0,0,$dstW,$dstH,$srcW,$srcH);
    $tmp=$absolutePath.'.opt-'.bin2hex(random_bytes(3));$saved=false;
    if($type===IMAGETYPE_JPEG&&function_exists('imagejpeg'))$saved=@imagejpeg($dst,$tmp,max(45,min(92,$quality)));
    elseif($type===IMAGETYPE_PNG&&function_exists('imagepng'))$saved=@imagepng($dst,$tmp,7);
    elseif($type===IMAGETYPE_WEBP&&function_exists('imagewebp'))$saved=@imagewebp($dst,$tmp,max(45,min(92,$quality)));
    imagedestroy($dst);imagedestroy($src);
    if(!$saved||!is_file($tmp)){@unlink($tmp);return ['ok'=>false,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'gagal encode'];}
    $after=(int)filesize($tmp);
    if($after>0 && ($after < $before || $w>$maxWidth || $h>$maxHeight)) {@rename($tmp,$absolutePath);@chmod($absolutePath,0644);clearstatcache(true,$absolutePath);$after=(int)filesize($absolutePath);return ['ok'=>true,'changed'=>true,'before'=>$before,'after'=>$after,'message'=>'dikompres'];}
    @unlink($tmp);return ['ok'=>true,'changed'=>false,'before'=>$before,'after'=>$before,'message'=>'hasil tidak lebih kecil'];
}

function legacy_media_reference_count(PDO $db, string $path): int
{
    $path = trim(str_replace('\\','/',$path));
    if ($path === '' || !str_starts_with(ltrim($path,'/'),'uploads/')) return 0;
    $path = ltrim($path,'/'); $count=0;
    $checks = [
        ['products','image'],['marketplace_product_images','image_path'],['marketplace_product_variants','image_path'],
        ['marketplace_brands','logo'],['marketplace_banners','image_desktop'],['marketplace_banners','image_mobile'],
        ['product_bundles','image'],['users','avatar'],['marketplace_courier_assignments','delivery_proof'],['marketplace_courier_events','proof_path']
    ];
    foreach($checks as [$table,$column]){
        try{
            $exists=$db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$exists->execute([$table,$column]);if(!(int)$exists->fetchColumn())continue;
            $q=$db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=?");$q->execute([$path]);$count+=(int)$q->fetchColumn();
        }catch(Throwable $e){}
    }
    try{$q=$db->prepare("SELECT COUNT(*) FROM settings WHERE setting_value LIKE ?");$q->execute(['%'.$path.'%']);$count+=(int)$q->fetchColumn();}catch(Throwable $e){}
    return $count;
}

function legacy_delete_media_if_unreferenced(PDO $db, ?string $path): bool
{
    $path = ltrim(trim(str_replace('\\','/',(string)$path)),'/');
    if ($path === '' || !str_starts_with($path,'uploads/') || legacy_media_reference_count($db,$path)>0) return false;
    $root=legacy_project_root();$deleted=false;
    foreach([$root.'/'.$path,$root.'/legacy/public/'.$path,$root.'/public/'.$path,$root.'/pos/public/'.$path,$root.'/pos/'.$path] as $file){
        if(is_file($file) && @unlink($file))$deleted=true;
    }
    return $deleted;
}

function upload_product_image(array $file): ?string
{
    return legacy_store_optimized_image($file, 'uploads/products', 'product', 8*1024*1024, 1600, 1600, 78);
}

// V17.0.24: foto kategori (kartu kategori storefront bisa memakai gambar, bukan icon Bootstrap)
function upload_category_image(array $file): ?string
{
    if (empty($file['name']) || empty($file['tmp_name'])) return null;
    return legacy_store_optimized_image($file, 'uploads/categories', 'category', 4 * 1024 * 1024, 900, 900, 82);
}

