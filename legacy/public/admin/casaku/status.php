<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/Marketplace/Marketplace.php';
require_once __DIR__ . '/../whatsapp/_helpers.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

function casaku_pos_json(array $data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function casaku_pos_apply_member_referral(PDO $db, array $trx): void
{
    if (($trx['status'] ?? '') !== 'success') return;
    if (!preg_match('/\[MEMBER_POINTS\](.*?)\[\/MEMBER_POINTS\]/s', (string)($trx['note'] ?? ''), $m)) return;
    $meta = json_decode($m[1], true);
    if (!is_array($meta)) return;
    $customerId = (int)($meta['customer_id'] ?? $trx['customer_id'] ?? 0);
    $referrerId = (int)($meta['referrer_customer_id'] ?? 0);
    $phone = trim((string)($meta['referrer_phone'] ?? ''));
    $points = max(0, (int)($meta['referral_points'] ?? 0));
    $transactionId = (int)($trx['id'] ?? 0);
    if ($customerId <= 0 || $referrerId <= 0 || $phone === '' || $transactionId <= 0) return;
    $db->exec("CREATE TABLE IF NOT EXISTS customer_referrals (id BIGINT AUTO_INCREMENT PRIMARY KEY,referred_customer_id BIGINT NOT NULL,referrer_customer_id BIGINT NOT NULL,referral_phone VARCHAR(40) NOT NULL,first_transaction_id BIGINT NULL,referral_points INT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY customer_referrals_referred_unique(referred_customer_id),INDEX idx_customer_referrals_referrer(referrer_customer_id),INDEX idx_customer_referrals_transaction(first_transaction_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $db->prepare('SELECT id FROM customer_referrals WHERE referred_customer_id=? LIMIT 1'); $st->execute([$customerId]);
    if ($st->fetchColumn()) return;
    $db->prepare('INSERT INTO customer_referrals (referred_customer_id,referrer_customer_id,referral_phone,first_transaction_id,referral_points,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$customerId,$referrerId,$phone,$transactionId,$points]);
    if ($points <= 0) return;
    $db->exec("CREATE TABLE IF NOT EXISTS customer_points (id BIGINT AUTO_INCREMENT PRIMARY KEY,customer_id BIGINT NOT NULL,transaction_id BIGINT NULL,type ENUM('in','out') NOT NULL DEFAULT 'in',points INT NOT NULL DEFAULT 0,description TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_customer_points_customer(customer_id),INDEX idx_customer_points_transaction(transaction_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $db->prepare("SELECT COUNT(*) FROM customer_points WHERE customer_id=? AND transaction_id=? AND type='in' AND description LIKE 'Poin referral:%'"); $st->execute([$referrerId,$transactionId]);
    if ((int)$st->fetchColumn() > 0) return;
    $db->prepare("INSERT INTO customer_points(customer_id,transaction_id,type,points,description,created_at) VALUES (?,?,'in',?,?,NOW())")->execute([$referrerId,$transactionId,$points,'Poin referral: '.($trx['invoice_number'] ?? '-')]);
    $db->prepare('UPDATE customers SET points=points+?,updated_at=NOW() WHERE id=?')->execute([$points,$referrerId]);
    // Notifikasi WA otomatis ke referrer (sekali, saat referral baru dibuat). Gagal WA tidak menggagalkan transaksi.
    try {
        if (function_exists('wa_send_fonnte')) {
            $rf = $db->prepare('SELECT name, points FROM customers WHERE id=? LIMIT 1'); $rf->execute([$referrerId]); $ref = $rf->fetch();
            $refName = trim((string)($ref['name'] ?? ''));
            $refTotal = max(0, (int)($ref['points'] ?? 0));
            $msg = 'Halo makasih ya ' . ($refName !== '' ? $refName : 'Member') . ', udah ajak temen kamu belanja di Vapekong! Poin kamu bertambah ' . $points . ' poin. Sekarang total poin kamu ' . $refTotal . '. Yuk ajakin temen kamu yang lain!';
            wa_send_fonnte($db, $phone, $msg, $transactionId);
        }
    } catch (Throwable $e) {
        // jangan gagalkan status pembayaran karena notif WA gagal
    }
}

$id = (int)($_GET['id'] ?? 0);
$db = Database::connect();
$st = $db->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1'); $st->execute([$id]); $trx = $st->fetch();
if (!$trx) casaku_pos_json(['success'=>false,'message'=>'Transaksi tidak ditemukan.'], 404);
$meta = [];
if (preg_match('/\[CASAKU_PAYMENT\](.*?)\[\/CASAKU_PAYMENT\]/s', (string)($trx['note'] ?? ''), $m)) {
    $decoded = json_decode($m[1], true);
    if (is_array($decoded)) $meta = $decoded;
}
if (($trx['payment_method'] ?? '') !== 'qris_casaku' || empty($meta['txn_id'])) casaku_pos_json(['success'=>false,'message'=>'Metadata CasaKu tidak ditemukan.'], 400);
$current = strtolower((string)($trx['status'] ?? 'pending'));
if ($current === 'success') casaku_pos_json(['success'=>true,'status'=>'success','message'=>'Pembayaran QRIS CasaKu sudah sukses.']);
try {
    $cfg = marketplace_casaku_config($db);
    $json = marketplace_http_json($cfg['base_url'].'/api/generate/check-status', 'POST', ['Accept: application/json','Content-Type: application/json','x-license-key: '.$cfg['license_key']], ['transactionId'=>(string)$meta['txn_id']]);
    $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
    $remote = strtolower((string)($data['status'] ?? ''));
    $status = in_array($remote, ['paid','success','completed','settlement','settled'], true) ? 'success' : (in_array($remote, ['expired','failed','cancelled','canceled','deny','denied'], true) ? 'failed' : 'pending');
    $amount = (int)($data['amount'] ?? $data['totalAmount'] ?? 0);
    $expected = (int)round((float)($trx['grand_total'] ?? 0));
    if ($amount > 0 && $amount !== $expected) throw new RuntimeException('Nominal status CasaKu tidak sesuai.');
    if ($status === 'success') {
        $db->prepare("UPDATE transactions SET status='success', paid_amount=grand_total, change_amount=0, updated_at=NOW() WHERE id=? AND status<>'success'")->execute([$id]);
        $st = $db->prepare('SELECT * FROM transactions WHERE id=? LIMIT 1'); $st->execute([$id]);
        $fresh = $st->fetch(); if ($fresh) casaku_pos_apply_member_referral($db, $fresh);
    } elseif ($status === 'failed') {
        $db->prepare("UPDATE transactions SET status='failed', updated_at=NOW() WHERE id=? AND status<>'success'")->execute([$id]);
    }
    casaku_pos_json(['success'=>true,'status'=>$status,'remote_status'=>$remote,'message'=>$status==='success'?'Pembayaran QRIS CasaKu berhasil dikonfirmasi.':($status==='failed'?'Pembayaran QRIS CasaKu gagal atau expired.':'Menunggu pembayaran QRIS CasaKu.')]);
} catch (Throwable $e) { casaku_pos_json(['success'=>false,'message'=>$e->getMessage()], 502); }
