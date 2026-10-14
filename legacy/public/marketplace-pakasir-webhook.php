<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Modules/Marketplace/Marketplace.php';
header('Content-Type: application/json; charset=utf-8');

function marketplace_pakasir_webhook_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function marketplace_pakasir_header(string $name): string
{
    $key='HTTP_'.strtoupper(str_replace('-','_',$name));
    if(isset($_SERVER[$key])) return trim((string)$_SERVER[$key]);
    if(function_exists('getallheaders')) foreach((array)getallheaders() as $k=>$v) if(strcasecmp((string)$k,$name)===0) return trim((string)$v);
    return '';
}

try {
    $db = Database::connect();
    marketplace_install($db);
    $config = marketplace_pakasir_config($db);
    $secret = trim((string)($config['webhook_secret'] ?? ''));
    $incomingSecret = marketplace_pakasir_header('X-Secret');

    if ($secret !== '') {
        if ($incomingSecret === '' || !hash_equals($secret, $incomingSecret)) {
            marketplace_pakasir_webhook_json(['success'=>false,'message'=>'X-Secret webhook Pakasir v2 tidak valid.'], 403);
        }
    } else {
        // Kompatibilitas URL webhook lama yang memakai ?token= selama masa migrasi.
        $legacySecret = trim((string)($config['webhook_token'] ?? ''));
        $legacyIncoming = trim((string)($_GET['token'] ?? ''));
        if ($legacySecret !== '' && $legacyIncoming !== '' && !hash_equals($legacySecret, $legacyIncoming)) {
            marketplace_pakasir_webhook_json(['success'=>false,'message'=>'Token webhook legacy tidak valid.'], 403);
        }
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = $_POST;
    $orderId = trim((string) ($payload['order_id'] ?? ''));
    if ($orderId === '') marketplace_pakasir_webhook_json(['success'=>false,'message'=>'order_id wajib tersedia.'], 400);
    $order = marketplace_get_order($db, $orderId);
    if (!$order) marketplace_pakasir_webhook_json(['success'=>false,'message'=>'Pesanan marketplace tidak ditemukan.'], 404);
    $updated = marketplace_apply_payment_webhook($db, $payload);
    // Best-effort WA delivery after DB update; failures stay queued for cron retry.
    try { if (function_exists('marketplace_wa_process_queue')) marketplace_wa_process_queue($db, 10); } catch (Throwable $ignore) {}
    marketplace_pakasir_webhook_json([
        'success'=>true,
        'message'=>'Webhook marketplace diproses.',
        'order_id'=>$orderId,
        'payment_status'=>$updated['payment_status'] ?? 'pending',
        'txn_id'=>(string)($payload['txn_id'] ?? ''),
        'api_version'=>'v2',
    ]);
} catch (Throwable $e) {
    marketplace_pakasir_webhook_json(['success'=>false,'message'=>$e->getMessage()], 422);
}
