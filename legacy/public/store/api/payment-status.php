<?php
require_once __DIR__ . '/_api.php';

try {
    $orderNumber = trim((string) ($_GET['order'] ?? ''));
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($orderNumber === '' || $token === '') throw new InvalidArgumentException('Pesanan tidak valid.');
    $db = Database::connect();
    $order = marketplace_get_order($db, $orderNumber, $token);
    if (!$order) marketplace_api_json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
    $order = marketplace_expire_if_needed($db, $order);
    // V2 fallback: sinkronkan status langsung dari Pakasir setiap polling customer (JS 9 detik),
    // sehingga checkout tetap update walau webhook terlambat/gagal.
    if (($order['payment_status'] ?? '') === 'pending' && !empty($order['payment_txn_id'])) {
        try { $order = marketplace_payment_sync_status($db, $order); } catch (Throwable $ignore) {}
    }
    marketplace_api_json([
        'success' => true,
        'payment_status' => $order['payment_status'],
        'payment_label' => marketplace_payment_status_label((string) $order['payment_status']),
        'order_status' => $order['order_status'],
        'order_label' => marketplace_order_status_label((string) $order['order_status']),
        'paid' => ($order['payment_status'] === 'paid'),
        'reload' => in_array((string) $order['payment_status'], ['paid','expired','failed'], true),
    ]);
} catch (Throwable $e) {
    marketplace_api_error($e);
}
