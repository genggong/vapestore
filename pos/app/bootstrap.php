<?php

require_once __DIR__ . '/Security/security.php';
security_basic_request_guard();
security_boot_session();
security_send_headers();

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/CSRF.php';
require_once __DIR__ . '/Core/Auth.php';
require_once __DIR__ . '/Helpers/helpers.php';

// V17.0.18: versi aset WAJIB selalu terdefinisi. Kalau helpers.php versi lama (belum
// me-require asset_helpers.php) atau file helper-nya hilang, layout admin fatal dengan
// "Undefined constant APP_ASSET_VERSION" -> seluruh halaman admin 500. Definisi di sini
// tidak bergantung pada helpers.php sehingga halaman tetap jalan.
if (!function_exists('vk_asset_version')) {
    function vk_asset_version(): string
    {
        static $v = null;
        if ($v !== null) return $v;
        $v = '';
        foreach ([
            dirname(__DIR__, 2) . '/VERSION',   // docroot
            dirname(__DIR__) . '/VERSION',      // folder tree
            __DIR__ . '/../../VERSION',
        ] as $candidate) {
            if (is_file($candidate)) {
                $v = trim((string) @file_get_contents($candidate));
                if ($v !== '') break;
            }
        }
        return $v !== '' ? $v : '1';
    }
}
if (!defined('APP_ASSET_VERSION')) {
    define('APP_ASSET_VERSION', vk_asset_version());
}

date_default_timezone_set(app_config('timezone', 'Asia/Jakarta'));

if (app_config('debug', false)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}


if (function_exists('maintenance_guard')) {
    maintenance_guard();
}

