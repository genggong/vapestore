<?php
// V17.0.16c: cache-bust aset versi stabil — ganti ?v=time() yang memaksa
// browser unduh ulang semua CSS/JS tiap buka halaman. Browser kini cache
// aset antar halaman; versi berubah otomatis saat file VERSION diganti.
if (!defined('APP_ASSET_VERSION')) {
    $vReal = '';
    foreach ([__DIR__ . '/../../VERSION', __DIR__ . '/../../../VERSION'] as $c) {
        if (is_file($c)) { $vReal = (string)@file_get_contents($c); if (trim($vReal) !== '') break; }
    }
    define('APP_ASSET_VERSION', trim($vReal) !== '' ? trim($vReal) : '1');
}

// Akses: aset_url('assets/js/app.js', '/admin') -> /admin/assets/js/app.js?v=<VERSION>
if (!function_exists('aset_url')) {
    function aset_url(string $path, string $prefix = '/admin')
    {
        return $prefix . '/' . $path . '?v=' . APP_ASSET_VERSION;
    }
}