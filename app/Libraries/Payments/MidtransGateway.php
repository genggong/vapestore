<?php

namespace App\Libraries\Payments;

final class MidtransGateway implements PaymentGatewayInterface
{
    private string $serverKey;
    private bool $sandbox;

    public function __construct()
    {
        $this->serverKey = trim((string) setting('midtrans_server_key', ''));
        $this->sandbox = setting('midtrans_sandbox', '1') === '1';
    }

    /** @return array{ready:bool,message:string,environment:string} */
    public function configurationStatus(): array
    {
        return [
            'ready' => $this->serverKey !== '',
            'message' => $this->serverKey !== '' ? 'Server Key Midtrans sudah tersimpan.' : 'Server Key Midtrans belum dikonfigurasi.',
            'environment' => $this->sandbox ? 'sandbox' : 'production',
        ];
    }

    /**
     * Tes autentikasi tanpa membuat transaksi. Tes ini hanya membuktikan Server Key
     * cocok dengan environment, bukan membuktikan produk Core API / payment method
     * sudah aktif di Production. Aktivasi Core API Production adalah entitlement terpisah.
     *
     * @return array{success:bool,message:string,http_status:int,raw:array}
     */
    public function diagnostics(): array
    {
        if ($this->serverKey === '') {
            return ['success' => false, 'message' => 'Server Key Midtrans belum disimpan.', 'http_status' => 0, 'raw' => []];
        }

        $probe = 'TOPUPIN-CONNECTION-CHECK-' . date('Ymd');
        $response = $this->request('GET', $this->baseUrl() . '/v2/' . rawurlencode($probe) . '/status');
        $http = (int) ($response['http_status'] ?? 0);
        $data = (array) ($response['json'] ?? []);
        $statusCode = (string) ($data['status_code'] ?? '');

        if ($http === 401 || $statusCode === '401') {
            return ['success' => false, 'message' => 'Server Key ditolak Midtrans. Pastikan key sesuai environment Sandbox/Production.', 'http_status' => $http, 'raw' => $data];
        }

        // Random order status biasanya 404 bila autentikasi benar. Itu valid untuk tes key,
        // tetapi tidak boleh disebut sebagai bukti bahwa Core API charge telah aktif.
        if (in_array($http, [200, 404], true) || in_array($statusCode, ['200', '404'], true)) {
            return [
                'success' => true,
                'message' => 'Server Key Midtrans valid pada environment ' . ($this->sandbox ? 'Sandbox' : 'Production') . '. Catatan: tes ini hanya memverifikasi autentikasi; akses Core API/payment method harus dites terpisah.',
                'http_status' => $http,
                'raw' => $data,
            ];
        }

        return ['success' => false, 'message' => $this->formatGatewayError($data, $http, 'Midtrans belum dapat dijangkau dari server.'), 'http_status' => $http, 'raw' => $data];
    }

    /**
     * Tes Core API sesungguhnya dengan membuat transaksi kecil PENDING lalu langsung
     * membatalkannya. Ini tidak memotong dana karena pelanggan tidak pernah menerima
     * instruksi pembayaran. Gunakan hanya dari panel admin.
     *
     * @return array{success:bool,message:string,http_status:int,status_code:string,order_id:string,transaction_id:string,cancelled:bool,raw:array}
     */
    public function coreApiDiagnostics(string $channel): array
    {
        if ($this->serverKey === '') {
            return [
                'success' => false, 'message' => 'Server Key Midtrans belum disimpan.', 'http_status' => 0,
                'status_code' => '', 'order_id' => '', 'transaction_id' => '', 'cancelled' => false, 'raw' => [],
            ];
        }

        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['qris', 'gopay'], true)) {
            return [
                'success' => false, 'message' => 'Channel diagnosis Midtrans tidak didukung.', 'http_status' => 0,
                'status_code' => '', 'order_id' => '', 'transaction_id' => '', 'cancelled' => false, 'raw' => [],
            ];
        }

        try {
            $suffix = strtoupper(bin2hex(random_bytes(3)));
        } catch (\Throwable) {
            $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 6));
        }
        $orderId = 'TOPUPIN-CORETEST-' . date('YmdHis') . '-' . $suffix;
        $amount = 10000;
        $payload = [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'item_details' => [[
                'id' => 'MIDTEST', 'price' => $amount, 'quantity' => 1, 'name' => 'Midtrans Core API Diagnostic',
            ]],
            'customer_details' => [
                'first_name' => 'TopUpin Diagnostic',
                'email' => 'diagnostic@example.com',
                'phone' => '081234567890',
            ],
            'custom_expiry' => ['expiry_duration' => 15, 'unit' => 'minute'],
        ];

        $channelError = $this->applyChannel($payload, $channel, $payload['customer_details']);
        if ($channelError !== null) {
            return [
                'success' => false, 'message' => $channelError, 'http_status' => 0, 'status_code' => '',
                'order_id' => $orderId, 'transaction_id' => '', 'cancelled' => false, 'raw' => [],
            ];
        }

        $response = $this->request('POST', $this->baseUrl() . '/v2/charge', ['json' => $payload]);
        $http = (int) ($response['http_status'] ?? 0);
        $data = (array) ($response['json'] ?? []);
        $statusCode = (string) ($data['status_code'] ?? '');
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));

        if (! in_array($statusCode, ['200', '201'], true)) {
            return [
                'success' => false,
                'message' => $this->formatGatewayError($data, $http, 'Core API Midtrans menolak transaksi uji.'),
                'http_status' => $http,
                'status_code' => $statusCode,
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'cancelled' => false,
                'raw' => $data,
            ];
        }

        $identifier = $transactionId !== '' ? $transactionId : $orderId;
        $cancel = $this->request('POST', $this->baseUrl() . '/v2/' . rawurlencode($identifier) . '/cancel');
        $cancelData = (array) ($cancel['json'] ?? []);
        $cancelled = (string) ($cancelData['status_code'] ?? '') === '200'
            || strtolower((string) ($cancelData['transaction_status'] ?? '')) === 'cancel';

        $message = strtoupper($channel) . ' Core API berhasil membuat transaksi uji pada ' . ($this->sandbox ? 'Sandbox' : 'Production') . '.';
        $message .= $cancelled ? ' Transaksi uji langsung dibatalkan.' : ' Transaksi berhasil dibuat, tetapi pembatalan otomatis perlu diperiksa di dashboard.';

        return [
            'success' => true,
            'message' => $message,
            'http_status' => $http,
            'status_code' => $statusCode,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'cancelled' => $cancelled,
            'raw' => ['charge' => $data, 'cancel' => $cancelData],
        ];
    }

    public function createPayment(string $invoice, float $amount, string $channel, array $customer = [], array $item = []): array
    {
        if ($this->serverKey === '') {
            return $this->error('Konfigurasi Midtrans belum lengkap. Simpan Server Key terlebih dahulu.');
        }

        $amountInt = (int) round($amount);
        if ($amountInt < 1) {
            return $this->error('Nominal pembayaran Midtrans tidak valid.');
        }

        $customerDetails = [
            'first_name' => trim((string) ($customer['name'] ?? 'Pelanggan')) ?: 'Pelanggan',
        ];
        $email = trim((string) ($customer['email'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? $customer['contact'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $customerDetails['email'] = $email;
        }
        if ($phone !== '') {
            $customerDetails['phone'] = $phone;
        }

        $payload = [
            'transaction_details' => ['order_id' => $invoice, 'gross_amount' => $amountInt],
            'customer_details' => $customerDetails,
            'item_details' => [[
                'id' => mb_substr((string) ($item['sku'] ?? $invoice), 0, 50),
                'price' => $amountInt,
                'quantity' => 1,
                'name' => mb_substr(trim((string) ($item['name'] ?? 'Produk Digital')) ?: 'Produk Digital', 0, 50),
            ]],
        ];

        $channel = strtolower(trim($channel));
        $channelError = $this->applyChannel($payload, $channel, $customerDetails);
        if ($channelError !== null) {
            return $this->error($channelError);
        }

        // OVO memiliki expiry tetap sekitar 65 detik dan tidak menerima custom expiry.
        if ($channel !== 'ovo') {
            $payload['custom_expiry'] = [
                'expiry_duration' => max(5, min(1440, (int) setting('payment_expiry_minutes', '30'))),
                'unit' => 'minute',
            ];
        }

        $response = $this->request('POST', $this->baseUrl() . '/v2/charge', ['json' => $payload]);
        $data = (array) ($response['json'] ?? []);
        $http = (int) ($response['http_status'] ?? 0);
        if (! in_array((string) ($data['status_code'] ?? ''), ['200', '201'], true)) {
            $raw = $data;
            $raw['_http_status'] = $http;
            return $this->error($this->formatGatewayError($data, $http, 'Gagal membuat instruksi pembayaran Midtrans.'), $raw);
        }

        $qrUrl = null;
        $redirectUrl = null;
        foreach (($data['actions'] ?? []) as $action) {
            if (! is_array($action) || empty($action['url'])) continue;
            $name = strtolower(trim((string) ($action['name'] ?? '')));
            if ($qrUrl === null && in_array($name, ['generate-qr-code', 'generate-qr-code-v2'], true)) {
                $qrUrl = (string) $action['url'];
            }
            if ($redirectUrl === null && in_array($name, ['deeplink-redirect', 'redirect', 'get-status'], true) && $name !== 'get-status') {
                $redirectUrl = (string) $action['url'];
            }
        }

        $paymentNumber = null;
        $instructions = [];
        if (! empty($data['va_numbers'][0]['va_number'])) {
            $paymentNumber = (string) $data['va_numbers'][0]['va_number'];
        } elseif (! empty($data['permata_va_number'])) {
            $paymentNumber = (string) $data['permata_va_number'];
        } elseif (! empty($data['payment_code'])) {
            $paymentNumber = (string) $data['payment_code'];
        } elseif (! empty($data['bill_key'])) {
            $paymentNumber = (string) $data['bill_key'];
            if (! empty($data['biller_code'])) {
                $instructions[] = ['label' => 'Kode perusahaan', 'value' => (string) $data['biller_code']];
            }
        }

        return [
            'success' => true,
            'reference' => (string) ($data['transaction_id'] ?? $invoice),
            'channel' => $channel,
            'payment_number' => $paymentNumber,
            'payment_url' => $redirectUrl,
            'qr_string' => isset($data['qr_string']) ? (string) $data['qr_string'] : null,
            'qr_image_url' => $qrUrl,
            'expires_at' => $this->expiryFromResponse($data, $channel),
            'status' => $this->mapStatus((string) ($data['transaction_status'] ?? 'pending'), (string) ($data['fraud_status'] ?? '')),
            'fee' => 0,
            'merchant_fee' => 0,
            'total_amount' => (float) ($data['gross_amount'] ?? $amount),
            'instructions' => $instructions,
            'message' => 'Instruksi pembayaran Midtrans berhasil dibuat.',
            'raw' => $data,
        ];
    }

    public function checkPayment(array $order): array
    {
        if ($this->serverKey === '') {
            return ['success' => false, 'status' => 'pending', 'message' => 'Konfigurasi Midtrans belum lengkap.', 'raw' => []];
        }
        // transaction_id paling aman untuk DANA/Static QRIS; fallback ke order_id.
        $identifier = trim((string) ($order['payment_reference'] ?? ''))
            ?: trim((string) ($order['payment_request_id'] ?? ''))
            ?: (string) ($order['invoice'] ?? '');
        $response = $this->request('GET', $this->baseUrl() . '/v2/' . rawurlencode($identifier) . '/status');
        $data = (array) ($response['json'] ?? []);
        if (! isset($data['transaction_status'])) {
            return ['success' => false, 'status' => 'pending', 'message' => (string) ($data['status_message'] ?? $response['message'] ?? 'Status pembayaran Midtrans belum dapat diperiksa.'), 'raw' => $data];
        }
        return [
            'success' => true,
            'status' => $this->mapStatus((string) $data['transaction_status'], (string) ($data['fraud_status'] ?? '')),
            'amount' => (float) ($data['gross_amount'] ?? 0),
            'message' => 'Status pembayaran Midtrans diperbarui.',
            'raw' => $data,
        ];
    }

    /**
     * Refund/cancel pembayaran Midtrans secara idempotent.
     * Refund API hanya tersedia untuk metode tertentu dan umumnya membutuhkan status settlement.
     * Capture/authorize harus dibatalkan (Cancel API), bukan dipanggil Refund API.
     *
     * @return array{success:bool,action:string,message:string,reference:?string,raw:array}
     */
    public function refundPayment(array $order, ?float $amount = null, string $reason = 'Produk gagal diproses', ?string $refundKey = null): array
    {
        if ($this->serverKey === '') {
            return ['success' => false, 'action' => 'none', 'message' => 'Server Key Midtrans belum dikonfigurasi.', 'reference' => null, 'raw' => []];
        }

        $transactionId = trim((string) ($order['payment_reference'] ?? ''));
        $orderId = trim((string) ($order['payment_request_id'] ?? $order['invoice'] ?? ''));
        $statusIdentifier = $transactionId !== '' ? $transactionId : $orderId;
        if ($statusIdentifier === '') {
            return ['success' => false, 'action' => 'none', 'message' => 'Referensi transaksi Midtrans tidak tersedia.', 'reference' => null, 'raw' => []];
        }

        $statusResponse = $this->request('GET', $this->baseUrl() . '/v2/' . rawurlencode($statusIdentifier) . '/status');
        $statusData = (array) ($statusResponse['json'] ?? []);
        $transactionStatus = strtolower(trim((string) ($statusData['transaction_status'] ?? '')));
        if ($transactionStatus === '') {
            return ['success' => false, 'action' => 'status', 'message' => (string) ($statusData['status_message'] ?? 'Status transaksi Midtrans tidak dapat diverifikasi sebelum refund.'), 'reference' => $transactionId ?: null, 'raw' => $statusData];
        }

        $transactionId = trim((string) ($statusData['transaction_id'] ?? $transactionId));
        $paymentType = strtolower(trim((string) ($statusData['payment_type'] ?? '')));
        $grossAmount = max(0, (float) ($statusData['gross_amount'] ?? $order['total_amount'] ?? 0));
        $refundAmount = max(0, min($grossAmount, $amount !== null ? $amount : $grossAmount));
        if ($refundAmount <= 0) {
            return ['success' => false, 'action' => 'none', 'message' => 'Nominal refund Midtrans tidak valid.', 'reference' => $transactionId ?: null, 'raw' => $statusData];
        }

        if ($transactionStatus === 'refund') {
            return ['success' => true, 'action' => 'refund', 'message' => 'Transaksi Midtrans sudah berstatus refund.', 'reference' => $transactionId ?: null, 'raw' => $statusData];
        }
        if ($transactionStatus === 'partial_refund') {
            $already = max(0, (float) ($statusData['refund_amount'] ?? 0));
            if ($already + 0.01 >= $refundAmount) {
                return ['success' => true, 'action' => 'refund', 'message' => 'Nominal refund Midtrans sudah terpenuhi.', 'reference' => $transactionId ?: null, 'raw' => $statusData];
            }
            return ['success' => false, 'action' => 'refund', 'message' => 'Transaksi sudah partial refund. Pemeriksaan manual diperlukan sebelum refund tambahan.', 'reference' => $transactionId ?: null, 'raw' => $statusData];
        }

        if (in_array($transactionStatus, ['capture', 'authorize'], true)) {
            $identifier = $transactionId !== '' ? $transactionId : $orderId;
            $cancel = $this->request('POST', $this->baseUrl() . '/v2/' . rawurlencode($identifier) . '/cancel');
            $data = (array) ($cancel['json'] ?? []);
            if (in_array((string) ($data['status_code'] ?? ''), ['200', '201'], true) || strtolower((string) ($data['transaction_status'] ?? '')) === 'cancel') {
                return ['success' => true, 'action' => 'cancel', 'message' => 'Transaksi Midtrans berhasil dibatalkan sebelum settlement.', 'reference' => $transactionId ?: null, 'raw' => $data];
            }
            return ['success' => false, 'action' => 'cancel', 'message' => (string) ($data['status_message'] ?? 'Cancel Midtrans gagal.'), 'reference' => $transactionId ?: null, 'raw' => $data];
        }

        if ($transactionStatus !== 'settlement') {
            return [
                'success' => false,
                'action' => 'status',
                'message' => 'Refund otomatis ditahan karena status Midtrans saat ini ' . strtoupper($transactionStatus) . ', bukan SETTLEMENT/CAPTURE.',
                'reference' => $transactionId ?: null,
                'raw' => $statusData,
            ];
        }

        $refundable = ['credit_card', 'gopay', 'shopeepay', 'qris', 'ovo', 'dana', 'kredivo', 'akulaku'];
        if (! in_array($paymentType, $refundable, true)) {
            return [
                'success' => false,
                'action' => 'unsupported',
                'message' => 'Metode Midtrans ' . ($paymentType !== '' ? strtoupper($paymentType) : 'ini') . ' tidak mendukung Refund API otomatis. Gunakan proses refund manual/transfer ke pelanggan.',
                'reference' => $transactionId ?: null,
                'raw' => $statusData,
            ];
        }

        // Gunakan transaction_id bila tersedia. Ini wajib untuk beberapa kanal seperti
        // GoPay Static QRIS dan juga paling aman untuk operasi DANA.
        $identifier = $transactionId !== '' ? $transactionId : $orderId;
        $refundKey = trim((string) $refundKey);
        if ($refundKey === '') {
            $refundKey = 'RF-' . (int) ($order['id'] ?? 0) . '-' . substr(sha1((string) ($order['invoice'] ?? $identifier)), 0, 12);
        }
        $payload = [
            'refund_key' => mb_substr($refundKey, 0, 64),
            'amount' => (int) round($refundAmount),
            'reason' => mb_substr(trim($reason) ?: 'Produk gagal diproses', 0, 255),
        ];
        $refund = $this->request('POST', $this->baseUrl() . '/v2/' . rawurlencode($identifier) . '/refund', ['json' => $payload]);
        $data = (array) ($refund['json'] ?? []);
        $refundStatus = strtolower((string) ($data['transaction_status'] ?? ''));
        if (in_array((string) ($data['status_code'] ?? ''), ['200', '201'], true) || in_array($refundStatus, ['refund', 'partial_refund'], true)) {
            return ['success' => true, 'action' => 'refund', 'message' => (string) ($data['status_message'] ?? 'Refund Midtrans diterima.'), 'reference' => $transactionId ?: null, 'raw' => $data];
        }
        return ['success' => false, 'action' => 'refund', 'message' => (string) ($data['status_message'] ?? $refund['message'] ?? 'Refund Midtrans gagal.'), 'reference' => $transactionId ?: null, 'raw' => $data];
    }

    public function parseWebhook(array $headers, string $body): array
    {
        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return ['valid' => false, 'invoice' => null, 'status' => null, 'reference' => null, 'message' => 'Payload tidak valid.', 'raw' => []];
        }
        $expected = hash('sha512', (string) ($payload['order_id'] ?? '') . (string) ($payload['status_code'] ?? '') . (string) ($payload['gross_amount'] ?? '') . $this->serverKey);
        $valid = $this->serverKey !== '' && ! empty($payload['signature_key']) && hash_equals($expected, (string) $payload['signature_key']);
        return [
            'valid' => $valid,
            'invoice' => ! empty($payload['order_id']) ? (string) $payload['order_id'] : null,
            'status' => $this->mapStatus((string) ($payload['transaction_status'] ?? 'pending'), (string) ($payload['fraud_status'] ?? '')),
            'reference' => ! empty($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            'amount' => (float) ($payload['gross_amount'] ?? 0),
            'message' => $valid ? 'Webhook Midtrans diterima.' : 'Signature webhook Midtrans tidak valid.',
            'raw' => $payload,
        ];
    }

    /** @return ?string pesan error */
    private function applyChannel(array &$payload, string $channel, array $customer): ?string
    {
        $parts = explode(':', strtolower(trim($channel)), 2);
        $type = $parts[0] ?: 'qris';
        $option = $parts[1] ?? '';
        if ($type === 'qris') {
            $payload['payment_type'] = 'qris';
            $payload['qris'] = ['acquirer' => 'gopay'];
            return null;
        }
        if ($type === 'gopay') {
            $payload['payment_type'] = 'gopay';
            $payload['gopay'] = ['enable_callback' => false];
            return null;
        }
        if ($type === 'ovo') {
            $phone = trim((string) ($customer['phone'] ?? ''));
            if ($phone === '') {
                return 'Pembayaran OVO Midtrans membutuhkan nomor HP pelanggan. Isi WhatsApp/nomor HP pada checkout atau pilih metode lain.';
            }
            $payload['payment_type'] = 'ovo';
            $payload['ovo'] = ['payer_phone_number' => $phone];
            return null;
        }
        if ($type === 'shopeepay') {
            $payload['payment_type'] = 'shopeepay';
            $payload['shopeepay'] = ['callback_url' => base_url('/')];
            return null;
        }
        if ($type === 'bank_transfer') {
            $bank = $option ?: 'bni';
            if (! in_array($bank, ['bri', 'bca', 'bni', 'permata', 'cimb'], true)) {
                return 'Bank Virtual Account Midtrans tidak didukung oleh konfigurasi website.';
            }
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = ['bank' => $bank];
            return null;
        }
        if ($type === 'echannel') {
            $payload['payment_type'] = 'echannel';
            $payload['echannel'] = ['bill_info1' => 'Payment For', 'bill_info2' => (string) $payload['transaction_details']['order_id']];
            return null;
        }
        if ($type === 'cstore') {
            $store = $option ?: 'indomaret';
            if (! in_array($store, ['alfamart', 'indomaret'], true)) {
                return 'Gerai retail Midtrans tidak didukung oleh konfigurasi website.';
            }
            $payload['payment_type'] = 'cstore';
            $payload['cstore'] = ['store' => $store, 'message' => 'Pembayaran pesanan digital'];
            return null;
        }
        return 'Channel Midtrans tidak dikenali. Sinkronkan/atur ulang metode pembayaran sebelum menerima order.';
    }

    private function mapStatus(string $status, string $fraudStatus = ''): string
    {
        $status = strtolower($status);
        if ($status === 'capture' && ! in_array(strtolower($fraudStatus), ['challenge', 'deny'], true)) {
            return 'paid';
        }
        return match ($status) {
            'settlement', 'refund', 'partial_refund' => 'paid',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };
    }

    private function expiryFromResponse(array $data, string $channel = ''): ?string
    {
        if (! empty($data['expiry_time'])) {
            return (string) $data['expiry_time'];
        }
        if ($channel === 'ovo') {
            return date('Y-m-d H:i:s', time() + 65);
        }
        return date('Y-m-d H:i:s', time() + max(5, min(1440, (int) setting('payment_expiry_minutes', '30'))) * 60);
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
    }

    /** @return array{json:array,message:string,http_status:int} */
    private function request(string $method, string $url, array $options = []): array
    {
        try {
            $options['headers'] = array_merge($options['headers'] ?? [], [
                'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
            $options['timeout'] = $options['timeout'] ?? 20;
            $options['connect_timeout'] = $options['connect_timeout'] ?? 8;
            $options['http_errors'] = false;
            $client = service('curlrequest');
            $response = $client->request($method, $url, $options);
            $json = json_decode((string) $response->getBody(), true);
            return ['json' => is_array($json) ? $json : [], 'message' => '', 'http_status' => (int) $response->getStatusCode()];
        } catch (\Throwable $e) {
            log_message('error', 'Midtrans request gagal: {message}', ['message' => $e->getMessage()]);
            return ['json' => [], 'message' => 'Koneksi ke Midtrans gagal.', 'http_status' => 0];
        }
    }

    private function formatGatewayError(array $data, int $httpStatus = 0, string $fallback = 'Request Midtrans gagal.'): string
    {
        $code = trim((string) ($data['status_code'] ?? ($httpStatus > 0 ? $httpStatus : '')));
        $message = $data['status_message'] ?? '';
        if (is_array($message)) {
            $message = implode('; ', array_values(array_filter(array_map('strval', $message))));
        }
        $message = trim((string) $message);

        $validation = $data['validation_messages'] ?? [];
        if (is_array($validation) && $validation !== []) {
            $extra = implode('; ', array_values(array_filter(array_map('strval', $validation))));
            if ($extra !== '') $message = trim($message . ($message !== '' ? ' — ' : '') . $extra);
        }

        if ($code === '402') {
            $friendly = 'Merchant Midtrans belum memiliki akses untuk payment type ini / Core API Production belum diaktifkan.';
            if ($message !== '') $friendly .= ' Respons Midtrans: ' . $message;
            return '[Midtrans 402] ' . $friendly;
        }
        if ($code === '401') {
            return '[Midtrans 401] Server Key salah atau tidak sesuai environment Sandbox/Production.' . ($message !== '' ? ' ' . $message : '');
        }
        if ($code === '400') {
            return '[Midtrans 400] Request transaksi tidak valid.' . ($message !== '' ? ' ' . $message : '');
        }
        if ($code === '406') {
            return '[Midtrans 406] Order ID sudah pernah digunakan. Coba buat invoice baru.' . ($message !== '' ? ' ' . $message : '');
        }
        if ($code === '410') {
            return '[Midtrans 410] Merchant/payment account tidak dapat digunakan untuk transaksi ini.' . ($message !== '' ? ' ' . $message : '');
        }
        if ($code === '429') {
            return '[Midtrans 429] Terlalu banyak request ke Midtrans. Coba beberapa saat lagi.';
        }

        $base = $message !== '' ? $message : $fallback;
        return $code !== '' ? '[Midtrans ' . $code . '] ' . $base : $base;
    }

    private function error(string $message, array $raw = []): array
    {
        return [
            'success' => false,
            'reference' => null,
            'channel' => null,
            'payment_number' => null,
            'payment_url' => null,
            'qr_string' => null,
            'qr_image_url' => null,
            'expires_at' => null,
            'status' => 'failed',
            'fee' => 0,
            'merchant_fee' => 0,
            'total_amount' => 0,
            'instructions' => [],
            'message' => $message,
            'raw' => $raw,
        ];
    }
}
