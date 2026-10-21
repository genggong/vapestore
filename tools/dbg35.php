<?php
/**
 * Diagnostik v17.0.35 — cek kenapa halaman checkout error.
 * Pakai: taruh di docroot (~/public_html), buka https://vapekongstore.com/dbg35.php?k=dbg35
 * Kirim hasilnya, lalu HAPUS file ini.
 */
if ((string) ($_GET['k'] ?? '') !== 'dbg35') {
    http_response_code(403);
    exit('403');
}
ob_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);
$out = [];
$base = __DIR__;

$out[] = 'PHP: ' . PHP_VERSION;
$out[] = 'docroot: ' . $base;
foreach (['legacy/app/bootstrap.php', 'app/bootstrap.php', 'legacy/public/store/checkout.php', 'VERSION'] as $b) {
    $out[] = $b . ': ' . (is_file($base . '/' . $b) ? 'ADA' : 'TIDAK ADA');
}
$out[] = 'VERSION: ' . (@file_get_contents($base . '/VERSION') ?: '-');

try {
    require $base . '/legacy/public/store/_data.php';
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $out), "\nBOOTSTRAP GAGAL: ", get_class($e), ': ', $e->getMessage(), "\n", $e->getFile(), ':', $e->getLine(), "\n";
    exit;
}

$out[] = '';
foreach ([
    'marketplace_cod_enabled',
    'marketplace_cod_allowed',
    'marketplace_store_pickup_deadline',
    'marketplace_store_pickup_close_time',
    'marketplace_store_pickup_deadline_passed',
    'marketplace_store_pickup_deadline_label',
    'marketplace_store_pickup_config',
    'marketplace_pakasir_config',
    'marketplace_pakasir_methods',
] as $fn) {
    $out[] = str_pad($fn, 42) . ' ' . (function_exists($fn) ? 'ADA' : 'TIDAK ADA');
}

try {
    $db = storefront_db();
    $out[] = '';
    $out[] = 'db: ' . ($db ? 'OK' : 'NULL');
    if ($db) {
        foreach (['marketplace_store_pickup_hours', 'marketplace_store_pickup_max_days', 'marketplace_cod_enabled', 'marketplace_store_pickup_enabled'] as $k) {
            $st = $db->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
            $st->execute([$k]);
            $out[] = str_pad($k, 42) . ' ' . var_export($st->fetchColumn(), true);
        }
        $col = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_orders' AND COLUMN_NAME='pickup_deadline_at'")->fetchColumn();
        $out[] = str_pad('kolom pickup_deadline_at', 42) . ' ' . ((int) $col > 0 ? 'ADA' : 'TIDAK ADA');
        $out[] = str_pad('pickup_close_time()', 42) . ' ' . (function_exists('marketplace_store_pickup_close_time') ? marketplace_store_pickup_close_time() : '-');
        $out[] = str_pad('deadline()', 42) . ' ' . (function_exists('marketplace_store_pickup_deadline') ? marketplace_store_pickup_deadline() : '-');
    }
} catch (Throwable $e) {
    $out[] = 'DB GAGAL: ' . $e->getMessage();
}

$out[] = '';
$out[] = '--- render checkout.php ---';
$target = $base . '/legacy/public/store/checkout.php';
$_SERVER['SCRIPT_NAME'] = '/store/checkout.php';
$_SERVER['PHP_SELF'] = '/store/checkout.php';
$_SERVER['REQUEST_URI'] = '/checkout';
$_SERVER['REQUEST_METHOD'] = 'GET';
chdir(dirname($target));
$level = ob_get_level();
ob_start();
try {
    require $target;
    $html = ob_get_clean();
    $out[] = 'render OK (' . strlen((string) $html) . ' bytes)';
} catch (Throwable $e) {
    while (ob_get_level() > $level) { ob_end_clean(); }
    $out[] = 'render GAGAL';
    $out[] = get_class($e) . ': ' . $e->getMessage();
    $out[] = $e->getFile() . ':' . $e->getLine();
    foreach (array_slice($e->getTrace(), 0, 5) as $f) {
        $out[] = '  at ' . ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['function'] ?? '');
    }
}
$last = error_get_last();
if ($last) {
    $out[] = '';
    $out[] = 'last error: ' . $last['message'] . ' @ ' . $last['file'] . ':' . $last['line'];
}
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $out), "\n\nSELESAI\n";
