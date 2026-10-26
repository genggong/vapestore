<?php
// V17.0.50 — Webhook CasaKu QRIS (callback saat transaksi LUNAS).
// Verifikasi HMAC-SHA256 raw body dengan X-Casaku-Signature vs Webhook Secret.
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Modules/Marketplace/Marketplace.php';

header('Content-Type: application/json; charset=utf-8');

function casaku_webhook_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function casaku_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) return trim((string) $_SERVER[$key]);
    if (function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) return trim((string) $v);
        }
    }
    return '';
}

try {
    if (!function_exists('marketplace_casaku_config')) {
        require_once __DIR__ . '/../app/Modules/Marketplace/CasaKuPayment.php';
    }
    $db = Database::connect();
    marketplace_install($db);

    $secret = marketplace_casaku_config($db)['webhook_secret'];
    $rawBody = (string) file_get_contents('php://input');
    $sig = casaku_header('X-Casaku-Signature');

    // V17.0.51 — log diagnostik 403 untuk telusuri kenapa webhook ditolak.
    $dbgLine = sprintf(
        "[%s] sig_in=%s secret_set=%s|len=%d body_len=%d headers=%s",
        date('Y-m-d H:i:s'),
        $sig !== '' ? substr($sig, 0, 16) . '…' : '(kosong)',
        $secret !== '' ? 'ya' : 'TIDAK',
        strlen($secret),
        strlen($rawBody),
        json_encode(array_keys(array_change_key_case(isset($_SERVER) ? $_SERVER : [], CASE_LOWER)) ?: [])
    );
    if ($secret !== '' && $sig !== '') {
        $dbgLine .= ' computed=' . substr(hash_hmac('sha256', $rawBody, $secret), 0, 16) . '…';
    }
    try {
        $logDir = dirname(__DIR__) . '/writable/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        @file_put_contents($logDir . '/casaku-webhook.log', $dbgLine . "\n", FILE_APPEND);
    } catch (Throwable $e) {}

    if ($secret === '') {
        casaku_webhook_out(['success' => false, 'message' => 'Webhook Secret Casaku belum diatur.'], 403);
    }
    if (!marketplace_casaku_verify_signature($rawBody, $sig, $secret)) {
        casaku_webhook_out(['success' => false, 'message' => 'Signature CasaKu tidak valid.'], 403);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) casaku_webhook_out(['success' => false, 'message' => 'Body bukan JSON.'], 400);

    $txnId = trim((string) ($payload['transactionId'] ?? ''));
    $amount = (int) ($payload['amount'] ?? 0);
    $status = strtolower((string) ($payload['status'] ?? ''));
    if ($txnId === '') casaku_webhook_out(['success' => false, 'message' => 'transactionId wajib.'], 400);

    // temukan pesanan via payment_txn_id
    $st = $db->prepare("SELECT * FROM online_orders WHERE payment_txn_id=? LIMIT 1");
    $st->execute([$txnId]);
    $order = $st->fetch();
    if (!$order) casaku_webhook_out(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);

    if ($status === 'paid') {
        $expected = (int) round((float) ($order['payment_amount'] ?: $order['grand_total']));
        $paidAmount = $amount > 0 ? $amount : $expected;
        $applied = marketplace_apply_payment_webhook($db, [
            'txn_id' => $txnId,
            'order_id' => (string) ($order['order_number'] ?? ''),
            'amount' => $paidAmount,
            'status' => 'paid',
        ]);
        if (!$applied) casaku_webhook_out(['success' => false, 'message' => 'Gagal memproses webhook.'], 422);
    } else {
        casaku_webhook_out(['success' => true, 'message' => 'Webhook diterima (status belum lunas).']);
    }

    try { if (function_exists('marketplace_wa_process_queue')) marketplace_wa_process_queue($db, 10); } catch (Throwable $e) {}
    casaku_webhook_out([
        'success' => true,
        'message' => 'Webhook CasaKu diproses.',
        'order_id' => (string) ($order['order_number'] ?? ''),
        'payment_status' => 'paid',
        'txn_id' => $txnId,
    ]);
} catch (Throwable $e) {
    casaku_webhook_out(['success' => false, 'message' => $e->getMessage()], 422);
}
