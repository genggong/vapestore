<?php
/**
 * V17.0.50 — Payment Gateway CasaKu (QRIS otomatis, switchable via setting
 * marketplace_payment_gateway = 'pakasir' | 'casaku').
 *
 * Casaku: QRIS statis→dinamis; pembayaran dideteksi aplikasi listener HP.
 * API base: https://api.casaku.id  (header x-license-key).
 * Hanya mendukung metode 'qris'. Pakasir tetap dipakai bila gateway 'pakasir'.
 */

function marketplace_casaku_config(PDO $db = null): array
{
    return [
        'license_key' => trim((string) setting('marketplace_casaku_license_key', '')),
        'webhook_secret' => trim((string) setting('marketplace_casaku_webhook_secret', '')),
        'expired_minutes' => max(1, min(1440, (int) setting('marketplace_casaku_expired_minutes', '15'))),
        'base_url' => trim((string) setting('marketplace_casaku_base_url', 'https://api.casaku.id')),
    ];
}

function marketplace_payment_gateway(): string
{
    $g = strtolower(trim((string) setting('marketplace_payment_gateway', 'pakasir')));
    return in_array($g, ['pakasir', 'casaku'], true) ? $g : 'pakasir';
}

/** Metode pembayaran yg tersedia sesuai gateway aktif. */
function marketplace_payment_methods(): array
{
    if (marketplace_payment_gateway() === 'casaku') {
        return ['qris' => 'QRIS (CasaKu)'];
    }
    return marketplace_pakasir_methods();
}

/** Dispatcher: buat instruksi pembayaran lewat gateway aktif. */
function marketplace_payment_create(PDO $db, string $orderId, int $amount, string $method): array
{
    if (marketplace_payment_gateway() === 'casaku') {
        return marketplace_casaku_create($db, $orderId, $amount, $method);
    }
    return marketplace_pakasir_create($db, $orderId, $amount, $method);
}

/** Cek / sinkronkan status pembayaran pesanan via gateway aktif. */
function marketplace_payment_sync_status(PDO $db, array $order): array
{
    if (marketplace_payment_gateway() === 'casaku') {
        return marketplace_casaku_sync_status($db, $order);
    }
    return marketplace_pakasir_sync_status($db, $order);
}

/** Buat transaksi QRIS CasaKu v2. */
function marketplace_casaku_create(PDO $db, string $orderId, int $amount, string $method): array
{
    if ($method !== 'qris') throw new InvalidArgumentException('CasaKu hanya mendukung metode QRIS.');
    $config = marketplace_casaku_config($db);
    if ($config['license_key'] === '') throw new RuntimeException('License Key CasaKu belum diatur di Pengaturan Marketplace.');
    if ($amount <= 0) throw new InvalidArgumentException('Nominal pembayaran tidak valid.');

    $json = marketplace_http_json($config['base_url'] . '/api/generate/v2/qris', 'POST', [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-license-key: ' . $config['license_key'],
    ], [
        'qr_id' => $orderId,
        'amount' => $amount,
        'useUniqueCode' => false,
        'packageIds' => [],
        'expiredInMinutes' => $config['expired_minutes'],
        'qrType' => 'dynamic',
        'paymentMethod' => 'qris',
        'useQris' => true,
    ]);

    if ((int) ($json['status'] ?? 0) !== 200 || empty($json['data']['transactionId'])) {
        throw new RuntimeException((string) ($json['message'] ?? 'Gagal membuat QRIS CasaKu.'));
    }
    $d = $json['data'];
    return [
        'txn_id' => (string) ($d['transactionId'] ?? ''),
        'order_id' => $orderId,
        'amount' => (int) ($d['originalAmount'] ?? $amount),
        'fee' => 0,
        'total_payment' => (int) ($d['totalAmount'] ?? $d['originalAmount'] ?? $amount),
        'payment_method' => 'qris',
        'qr_string' => (string) ($d['qr_string'] ?? ''),
        'va_number' => '',
        'payment_link' => (string) ($d['payment_url'] ?? ''),
        'expired_at' => '',
        'status' => strtolower((string) ($d['status'] ?? 'pending')),
        'is_sandbox' => false,
        'raw' => $json,
    ];
}

/** Sinkronkan status CasaKu via check-status (untuk polling order). */
function marketplace_casaku_sync_status(PDO $db, array $order): array
{
    $txnId = trim((string) ($order['payment_txn_id'] ?? ''));
    if ($txnId === '') return $order;
    if (in_array((string) ($order['payment_status'] ?? ''), ['paid','expired','failed','cancelled'], true)) return $order;
    $config = marketplace_casaku_config($db);
    if ($config['license_key'] === '') return $order;

    $json = marketplace_http_json($config['base_url'] . '/api/generate/check-status', 'POST', [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-license-key: ' . $config['license_key'],
    ], ['transactionId' => $txnId]);

    $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
    $status = strtolower((string) ($data['status'] ?? ''));
    $amount = (int) ($data['amount'] ?? 0);
    $expected = (int) round((float) ($order['payment_amount'] ?: $order['grand_total']));
    if ($amount > 0 && $amount !== $expected) throw new RuntimeException('Nominal status CasaKu tidak sesuai.');

    $payload = [
        'txn_id' => $txnId,
        'order_id' => (string) ($order['order_number'] ?? ''),
        'amount' => $amount > 0 ? $amount : $expected,
        'status' => $status === 'paid' ? 'paid' : ($status === 'expired' ? 'expired' : 'pending'),
        'completed_at' => (string) ($data['paidAt'] ?? ''),
    ];
    $updated = marketplace_apply_payment_webhook($db, $payload);
    return $updated ?: $order;
}

/** Verifikasi HMAC-SHA256 webhook CasaKu (constant-time). */
function marketplace_casaku_verify_signature(string $rawBody, string $signature, string $secret): bool
{
    if ($signature === '' || $secret === '') return false;
    $calc = hash_hmac('sha256', $rawBody, $secret, false);
    return hash_equals($calc, $signature);
}
