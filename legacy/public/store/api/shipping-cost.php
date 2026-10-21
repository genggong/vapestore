<?php
require_once __DIR__ . '/_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    marketplace_api_json(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

try {
    marketplace_api_throttle('shipping', 300);
    $payload = marketplace_api_payload();
    marketplace_api_require_csrf($payload);
    $db = Database::connect();
    marketplace_install($db);
    $cart = marketplace_cart($db, is_array($payload['items'] ?? null) ? $payload['items'] : []);
    $destinationId = (int) ($payload['destination_id'] ?? 0);
    if ($destinationId <= 0) throw new InvalidArgumentException('Pilih tujuan pengiriman terlebih dahulu.');
    $options = [];
    $rajaError = '';
    try {
        $options = marketplace_rajaongkir_cost($destinationId, (int) $cart['weight_gram']);
    } catch (Throwable $shippingError) {
        $rajaError = $shippingError->getMessage();
    }
    // V17.0.29 — ongkir Kurir Toko mengikuti cabang pengirim yang memenuhi keranjang.
    $senderBranchId = marketplace_delivery_branch($db, is_array($payload['items'] ?? null) ? $payload['items'] : []);
    $senderBranchName = '';
    try {
        $bs = $db->prepare("SELECT name FROM branches WHERE id=? LIMIT 1");
        $bs->execute([$senderBranchId]);
        $senderBranchName = (string) ($bs->fetchColumn() ?: '');
    } catch (Throwable $e) {}
    $storeOption = marketplace_store_courier_option(
        trim((string)($payload['province'] ?? '')),
        trim((string)($payload['city'] ?? '')),
        trim((string)($payload['district'] ?? '')),
        trim((string)($payload['subdistrict'] ?? '')),
        $destinationId,
        $senderBranchId
    );
    if ($storeOption) array_unshift($options, $storeOption);
    if (!$options) throw new RuntimeException($rajaError !== '' ? $rajaError : 'Tidak ada layanan pengiriman untuk rute ini. Coba tujuan/kurir lain.');
    marketplace_api_json([
        'success' => true,
        'subtotal' => $cart['subtotal'],
        'weight_gram' => $cart['weight_gram'],
        'sender_branch_id' => $senderBranchId,
        'sender_branch_name' => $senderBranchName,
        'data' => $options,
    ]);
} catch (Throwable $e) {
    marketplace_api_error($e, 502);
}
