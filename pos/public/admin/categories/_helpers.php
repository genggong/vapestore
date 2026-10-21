<?php
// V17.0.24 — helper kategori (foto/ikon).

/** Pastikan kolom `image` ada walau "Jalankan Upgrade Otomatis" belum dijalankan. */
function categories_ensure_image_column(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $chk = $db->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if (!$chk->fetch()) {
            $db->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL");
        }
    } catch (Throwable $e) {
        error_log('[categories][image] ensure gagal: ' . $e->getMessage());
    }
}

/** URL publik foto kategori (aman untuk semua tree). */
function category_image_url(?string $image): string
{
    $image = trim((string)$image);
    if ($image === '') return '';
    if (preg_match('~^https?://~i', $image)) return $image;
    if (function_exists('legacy_upload_public_url')) {
        $base = function_exists('legacy_base_url') ? legacy_base_url() : base_url();
        return legacy_upload_public_url($image, $base);
    }
    return function_exists('legacy_base_url') ? legacy_base_url($image) : base_url($image);
}
