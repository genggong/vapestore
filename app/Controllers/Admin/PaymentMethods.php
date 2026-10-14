<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Payments\PaymentGatewayCapabilities;
use App\Libraries\Payments\TripayGateway;
use App\Libraries\Payments\MidtransGateway;
use App\Models\SettingModel;

class PaymentMethods extends BaseController
{
    public function index(): string
    {
        $tripayChannels = PaymentGatewayCapabilities::tripayChannels();
        $groups = [];
        foreach ($tripayChannels as $channel) {
            $group = trim((string) ($channel['group'] ?? 'Lainnya')) ?: 'Lainnya';
            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        $midtransMap = PaymentGatewayCapabilities::midtransMethodMap();
        $midtransGroups = [];
        $catalog = payment_method_catalog();
        foreach ($midtransMap as $methodKey => $channel) {
            $label = (string) ($catalog[$methodKey]['group_label'] ?? 'Lainnya');
            $midtransGroups[$label] = ($midtransGroups[$label] ?? 0) + 1;
        }
        ksort($midtransGroups, SORT_NATURAL | SORT_FLAG_CASE);
        $midtransGateway = new MidtransGateway();
        $midtransConfig = $midtransGateway->configurationStatus();

        return view('admin/payment_methods/index', [
            'pageTitle' => 'Metode Pembayaran',
            'groups' => payment_method_groups(false),
            'activeGateway' => strtolower(trim((string) setting('payment_gateway_active', 'demo'))),
            'tripayChannels' => $tripayChannels,
            'tripayChannelMap' => PaymentGatewayCapabilities::tripayChannelMap(),
            'tripayCacheAt' => trim((string) setting('tripay_channel_cache_at', '')),
            'tripayEnvironment' => setting('tripay_sandbox', '1') === '1' ? 'Sandbox' : 'Production',
            'tripayChannelGroups' => $groups,
            'midtransMethodMap' => $midtransMap,
            'midtransChannelGroups' => $midtransGroups,
            'midtransEnvironment' => ucfirst((string) ($midtransConfig['environment'] ?? 'sandbox')),
            'midtransReady' => (bool) ($midtransConfig['ready'] ?? false),
            'midtransSetupAt' => trim((string) setting('midtrans_method_setup_at', '')),
        ]);
    }

    public function update()
    {
        foreach (payment_method_catalog() as $key => $definition) {
            $label = trim((string) $this->request->getPost('label_' . $key));
            $description = trim((string) $this->request->getPost('description_' . $key));
            $fee = max(0, (float) $this->request->getPost('fee_' . $key));
            $feePercent = max(0, min(100, (float) $this->request->getPost('fee_percent_' . $key)));
            $feeMin = max(0, (float) $this->request->getPost('fee_min_' . $key));
            $feeMax = max(0, (float) $this->request->getPost('fee_max_' . $key));
            if ($feeMax > 0 && $feeMin > $feeMax) {
                [$feeMin, $feeMax] = [$feeMax, $feeMin];
            }
            $channel = trim((string) $this->request->getPost('channel_' . $key));
            $activeGateway = strtolower(trim((string) setting('payment_gateway_active', 'demo')));
            if ($activeGateway === 'tripay') {
                $channel = strtoupper($channel);
            } elseif ($activeGateway === 'midtrans') {
                $channel = PaymentGatewayCapabilities::resolveChannel('midtrans', (string) $key, $channel) ?? '';
            }

            // Ketika Tripay aktif, biaya merchant adalah data milik gateway dan harus
            // berasal dari hasil sinkron. Jangan izinkan nilai POST lama/manual
            // menimpa biaya resmi yang dipakai pada laporan profit.
            if ($activeGateway === 'tripay') {
                $merchantCost = max(0, (float) setting('payment_method_' . $key . '_merchant_cost', '0'));
                $merchantCostPercent = max(0, min(100, (float) setting('payment_method_' . $key . '_merchant_cost_percent', '0')));
            } else {
                $merchantCost = max(0, (float) $this->request->getPost('merchant_cost_' . $key));
                $merchantCostPercent = max(0, min(100, (float) $this->request->getPost('merchant_cost_percent_' . $key)));
            }

            $this->saveSetting('payment_method_' . $key . '_enabled', $this->request->getPost('enabled_' . $key) ? '1' : '0');
            $this->saveSetting('payment_method_' . $key . '_label', $label !== '' ? $label : (string) $definition['label']);
            $this->saveSetting('payment_method_' . $key . '_description', $description !== '' ? $description : (string) $definition['description']);
            $this->saveSetting('payment_method_' . $key . '_fee', (string) $fee);
            $this->saveSetting('payment_method_' . $key . '_fee_percent', (string) $feePercent);
            $this->saveSetting('payment_method_' . $key . '_fee_min', (string) $feeMin);
            $this->saveSetting('payment_method_' . $key . '_fee_max', (string) $feeMax);
            $this->saveSetting('payment_method_' . $key . '_merchant_cost', (string) $merchantCost);
            $this->saveSetting('payment_method_' . $key . '_merchant_cost_percent', (string) $merchantCostPercent);
            $this->saveSetting('payment_method_' . $key . '_channel', $channel);
        }

        if (payment_method_options(true) === []) {
            return redirect()->back()->with('toast_warning', 'Pengaturan tersimpan, tetapi tidak ada metode yang aktif dan kompatibel dengan gateway saat ini. Untuk Tripay lakukan sinkron channel; untuk Midtrans gunakan metode Core API yang didukung.');
        }

        return redirect()->back()->with('toast_success', $activeGateway === 'midtrans'
            ? 'Metode pembayaran Midtrans berhasil diperbarui. Mapping Core API tetap otomatis; aktifkan hanya metode yang sudah aktif di Dashboard Midtrans.'
            : 'Metode pembayaran berhasil diperbarui. Fee pelanggan toko tersimpan; biaya Tripay tetap mengikuti hasil sinkron resmi.');
    }

    public function syncTripay()
    {
        try {
            $stats = $this->syncTripaySettings(false);
            return redirect()->back()->with(
                'toast_success',
                'Sinkron Tripay berhasil: ' . $stats['channels'] . ' channel aktif terbaca, ' . $stats['mapped'] . ' metode publik dipetakan. Biaya merchant/customer dan batas nominal diperbarui dari API Tripay.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Sinkron channel Tripay gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('toast_error', $e->getMessage() !== '' ? $e->getMessage() : 'Channel Tripay belum dapat disinkronkan.');
        }
    }

    /**
     * Satu tombol untuk merchant yang baru pindah ke Tripay: jadikan Tripay gateway
     * aktif, ambil channel resmi, petakan kode yang tersedia, lalu aktifkan metode
     * yang berhasil dipetakan. Metode yang tidak tersedia tidak dipaksa aktif.
     */
    public function autoSetupTripay()
    {
        try {
            $this->saveSetting('payment_gateway_active', 'tripay', 'integrations');
            $stats = $this->syncTripaySettings(true);
            return redirect()->back()->with(
                'toast_success',
                'Tripay diaktifkan dan disiapkan otomatis: ' . $stats['channels'] . ' channel merchant terbaca, ' . $stats['mapped'] . ' metode dipetakan, ' . $stats['enabled'] . ' metode kompatibel diaktifkan. Silakan cek fee toko sebelum menerima transaksi.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Auto setup Tripay gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('toast_error', 'Auto setup Tripay gagal: ' . ($e->getMessage() ?: 'periksa kredensial Tripay.'));
        }
    }

    public function testMidtrans()
    {
        try {
            $gateway = new MidtransGateway();
            $result = $gateway->diagnostics();
            if (! ($result['success'] ?? false)) {
                return redirect()->back()->with('toast_error', (string) ($result['message'] ?? 'Tes Midtrans gagal.'));
            }
            return redirect()->back()->with('toast_success', (string) ($result['message'] ?? 'Koneksi Midtrans berhasil.'));
        } catch (\Throwable $e) {
            log_message('error', 'Tes Midtrans dari metode pembayaran gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('toast_error', 'Tes Midtrans gagal: ' . ($e->getMessage() ?: 'periksa Server Key dan environment.'));
        }
    }

    public function testMidtransCore()
    {
        $channel = strtolower(trim((string) $this->request->getPost('channel')));
        if (! in_array($channel, ['qris', 'gopay'], true)) {
            return redirect()->back()->with('toast_error', 'Channel tes Core API Midtrans tidak valid.');
        }

        try {
            $gateway = new MidtransGateway();
            $result = $gateway->coreApiDiagnostics($channel);
            $prefix = 'midtrans_core_test_' . $channel . '_';
            $this->saveSetting($prefix . 'status', ($result['success'] ?? false) ? 'ok' : 'error');
            $this->saveSetting($prefix . 'message', mb_substr((string) ($result['message'] ?? ''), 0, 1000));
            $this->saveSetting($prefix . 'at', date('Y-m-d H:i:s'));
            $this->saveSetting($prefix . 'code', (string) ($result['status_code'] ?? ''));

            if (! ($result['success'] ?? false)) {
                return redirect()->back()->with('toast_error', (string) ($result['message'] ?? 'Tes Core API Midtrans gagal.'));
            }
            return redirect()->back()->with('toast_success', (string) ($result['message'] ?? 'Tes Core API Midtrans berhasil.'));
        } catch (\Throwable $e) {
            log_message('error', 'Tes Core API Midtrans {channel} gagal: {message}', ['channel' => $channel, 'message' => $e->getMessage()]);
            return redirect()->back()->with('toast_error', 'Tes Core API Midtrans gagal: ' . ($e->getMessage() ?: 'periksa konfigurasi.'));
        }
    }

    /**
     * Midtrans Core API tidak menyediakan endpoint katalog channel+fee merchant seperti Tripay.
     * Setup ini memverifikasi Server Key, memasang mapping resmi yang didukung adapter website,
     * dan mengaktifkan set awal yang konservatif. Metode tambahan tetap di-ON dari halaman ini
     * setelah merchant memastikan aktivasi payment method di Dashboard Midtrans.
     */
    public function autoSetupMidtrans()
    {
        try {
            $gateway = new MidtransGateway();
            $config = $gateway->configurationStatus();
            if (! ($config['ready'] ?? false)) {
                throw new \RuntimeException((string) ($config['message'] ?? 'Konfigurasi Midtrans belum lengkap.'));
            }
            $diagnostic = $gateway->diagnostics();
            if (! ($diagnostic['success'] ?? false)) {
                throw new \RuntimeException((string) ($diagnostic['message'] ?? 'Server Key Midtrans tidak dapat diverifikasi.'));
            }

            $this->saveSetting('payment_gateway_active', 'midtrans', 'integrations');
            $defaults = array_flip(PaymentGatewayCapabilities::midtransDefaultEnabledMethods());
            $mapped = 0;
            $enabled = 0;
            $disabledUnsupported = 0;

            foreach (payment_method_catalog() as $key => $definition) {
                $channel = PaymentGatewayCapabilities::resolveChannel('midtrans', (string) $key, '');
                if ($channel === null) {
                    $this->saveSetting('payment_method_' . $key . '_channel', '');
                    $this->saveSetting('payment_method_' . $key . '_enabled', '0');
                    $disabledUnsupported++;
                    continue;
                }

                $this->saveSetting('payment_method_' . $key . '_channel', $channel);
                $mapped++;
                $isDefault = isset($defaults[$key]);
                $this->saveSetting('payment_method_' . $key . '_enabled', $isDefault ? '1' : '0');
                if ($isDefault) $enabled++;
            }

            $this->saveSetting('midtrans_method_setup_at', date('Y-m-d H:i:s'));
            $this->saveSetting('midtrans_method_setup_environment', strtolower((string) ($config['environment'] ?? 'sandbox')));

            return redirect()->back()->with(
                'toast_success',
                'Midtrans berhasil disiapkan: ' . $mapped . ' metode Core API dipetakan dan ' . $enabled . ' metode standar diaktifkan. ' .
                $disabledUnsupported . ' metode yang belum didukung adapter dimatikan. Aktifkan metode tambahan hanya setelah aktif di Dashboard Midtrans.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Auto setup Midtrans gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('toast_error', 'Setup Midtrans gagal: ' . ($e->getMessage() ?: 'periksa konfigurasi Midtrans.'));
        }
    }

    /** @return array{channels:int,mapped:int,enabled:int} */
    private function syncTripaySettings(bool $autoEnable): array
    {
        $gateway = new TripayGateway();
        $credentials = $gateway->configurationStatus();
        if (! ($credentials['ready'] ?? false)) {
            throw new \RuntimeException((string) ($credentials['message'] ?? 'Konfigurasi Tripay belum lengkap.'));
        }

        $result = $gateway->paymentChannels();
        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException('Sinkron channel Tripay gagal: ' . (string) ($result['message'] ?? 'Periksa API Key Tripay.'));
        }

        $channels = array_values((array) ($result['channels'] ?? []));
        if ($channels === []) {
            throw new \RuntimeException('Tripay merespons sukses tetapi tidak ada channel aktif pada merchant. Aktifkan channel di dashboard Tripay terlebih dahulu.');
        }

        $this->saveSetting('tripay_channel_cache_json', json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $this->saveSetting('tripay_channel_cache_at', date('Y-m-d H:i:s'));
        $this->saveSetting('tripay_channel_cache_environment', $gateway->isSandbox() ? 'sandbox' : 'production');

        $map = [];
        foreach ($channels as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code !== '') $map[$code] = $row;
        }

        $mapped = 0;
        $enabled = 0;
        foreach (payment_method_catalog() as $key => $definition) {
            $current = strtoupper(trim((string) setting('payment_method_' . $key . '_channel', '')));
            $code = '';
            if ($current !== '' && isset($map[$current])) {
                $code = $current;
            } else {
                $code = PaymentGatewayCapabilities::tripayBestCode($key, $map) ?? '';
            }
            if ($code === '' || ! isset($map[$code])) continue;

            $row = $map[$code];
            $this->saveSetting('payment_method_' . $key . '_channel', $code);
            $this->saveSetting('payment_method_' . $key . '_merchant_cost', (string) max(0, (float) ($row['fee_merchant']['flat'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_merchant_cost_percent', (string) max(0, (float) ($row['fee_merchant']['percent'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_customer_fee_flat', (string) max(0, (float) ($row['fee_customer']['flat'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_customer_fee_percent', (string) max(0, (float) ($row['fee_customer']['percent'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_minimum_fee', (string) max(0, (float) ($row['minimum_fee'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_maximum_fee', (string) max(0, (float) ($row['maximum_fee'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_minimum_amount', (string) max(0, (float) ($row['minimum_amount'] ?? 0)));
            $this->saveSetting('payment_method_' . $key . '_tripay_maximum_amount', (string) max(0, (float) ($row['maximum_amount'] ?? 0)));
            $mapped++;

            if ($autoEnable) {
                $this->saveSetting('payment_method_' . $key . '_enabled', '1');
                $enabled++;
            }
        }

        return ['channels' => count($channels), 'mapped' => $mapped, 'enabled' => $enabled];
    }

    private function saveSetting(string $key, string $value, string $group = 'payments'): void
    {
        $model = new SettingModel();
        $row = $model->where('setting_key', $key)->first();
        $data = ['setting_value' => $value, 'setting_group' => $group];
        if ($row) {
            $model->update((int) $row['id'], $data);
        } else {
            $model->insert(['setting_key' => $key] + $data);
        }
    }
}
