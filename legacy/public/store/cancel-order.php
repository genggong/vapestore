<?php
/**
 * V17.0.37 — pembatalan pesanan oleh pembeli (maks N menit setelah checkout, semua metode bayar).
 */
require_once __DIR__ . '/_data.php';
$db = storefront_db();
$orderNumber = trim((string) ($_POST['order'] ?? $_GET['order'] ?? ''));
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$back = 'pesanan/' . rawurlencode($orderNumber) . '?token=' . rawurlencode($token);

if (!$db || $orderNumber === '' || $token === '') {
    flash('error', 'Pesanan tidak valid.');
    legacy_redirect('pesanan-saya');
}
if (!is_post()) {
    legacy_redirect($back);
}
if (!CSRF::verify()) {
    flash('error', 'Token keamanan tidak valid. Muat ulang halaman pesanan.');
    legacy_redirect($back);
}

try {
    $res = marketplace_customer_cancel_order($db, $orderNumber, $token);
    flash($res['ok'] ? 'success' : 'error', $res['message']);
} catch (Throwable $e) {
    flash('error', 'Pembatalan gagal: ' . $e->getMessage());
}
legacy_redirect($back);
