<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
$gatewayLabel = match ($activeGateway) {
    'tripay' => 'Tripay',
    'midtrans' => 'Midtrans',
    'pakasir' => 'Pakasir',
    default => ucfirst((string) $activeGateway),
};
$gatewayEnvironment = $activeGateway === 'tripay'
    ? $tripayEnvironment
    : ($activeGateway === 'midtrans' ? $midtransEnvironment : '');
$totalMethods = 0;
$enabledMethods = 0;
$supportedMethods = 0;
foreach ($groups as $group) {
    foreach (($group['items'] ?? []) as $key => $option) {
        $totalMethods++;
        $supported = $activeGateway !== 'midtrans'
            || \App\Libraries\Payments\PaymentGatewayCapabilities::resolveChannel('midtrans', (string) $key, (string) ($option['channel'] ?? '')) !== null;
        if ($supported) $supportedMethods++;
        if (! empty($option['enabled']) && $supported) $enabledMethods++;
    }
}
$midtransQrisTest = [
    'status' => trim((string) setting('midtrans_core_test_qris_status', '')),
    'message' => trim((string) setting('midtrans_core_test_qris_message', '')),
    'at' => trim((string) setting('midtrans_core_test_qris_at', '')),
    'code' => trim((string) setting('midtrans_core_test_qris_code', '')),
];
$midtransGopayTest = [
    'status' => trim((string) setting('midtrans_core_test_gopay_status', '')),
    'message' => trim((string) setting('midtrans_core_test_gopay_message', '')),
    'at' => trim((string) setting('midtrans_core_test_gopay_at', '')),
    'code' => trim((string) setting('midtrans_core_test_gopay_code', '')),
];
$midtransCoreVerified = $midtransQrisTest['status'] === 'ok' || $midtransGopayTest['status'] === 'ok';
?>

<div class="payment-admin-page">
<section class="admin-card payment-admin-hero payment-admin-hero--pro">
    <div class="payment-hero-main">
        <div class="payment-hero-icon"><?= ui_icon('wallet') ?></div>
        <div class="payment-hero-copy">
            <span class="payment-eyebrow">Konfigurasi checkout</span>
            <h2>Metode pembayaran pelanggan</h2>
            <p>Kelola channel, status, dan biaya pelanggan dalam satu tempat. Mapping gateway dibuat otomatis bila adapter mendukung.</p>
            <div class="payment-hero-badges">
                <span class="payment-chip payment-chip--primary"><i></i><?= esc($gatewayLabel) ?></span>
                <?php if ($gatewayEnvironment !== ''): ?><span class="payment-chip"><?= esc($gatewayEnvironment) ?></span><?php endif ?>
                <span class="payment-chip" data-payment-enabled-summary><?= number_format($enabledMethods) ?> aktif</span>
                <span class="payment-chip"><?= number_format($supportedMethods) ?>/<?= number_format($totalMethods) ?> didukung</span>
            </div>
        </div>
        <a class="btn btn-ghost payment-hero-settings" href="<?= site_url('admin/integrasi') ?>"><?= ui_icon('settings') ?> <span>Integrasi API</span></a>
    </div>

    <?php if ($activeGateway === 'tripay'): ?>
        <div class="payment-gateway-panel payment-gateway-panel--tripay">
            <div class="payment-gateway-panel__head">
                <div>
                    <span class="payment-gateway-kicker">Gateway aktif</span>
                    <h3>Tripay <small><?= esc($tripayEnvironment) ?></small></h3>
                    <p><?= count($tripayChannels) ?> channel aktif tersimpan<?= $tripayCacheAt !== '' ? ' · sinkron terakhir ' . esc($tripayCacheAt) : '' ?>.</p>
                </div>
                <span class="payment-health-badge payment-health-badge--ok"><i></i> Siap digunakan</span>
            </div>
            <div class="payment-gateway-actions">
                <form action="<?= site_url('admin/metode-pembayaran/tripay/sync') ?>" method="post" data-loading-text="Mengambil channel dan fee Tripay...">
                    <?= csrf_field() ?>
                    <button class="btn btn-gold" type="submit"><?= ui_icon('refresh') ?> Sinkron Channel & Fee</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/tripay/auto-setup') ?>" method="post" data-loading-text="Menyiapkan Tripay otomatis..." onsubmit="return confirm('Jadikan Tripay sebagai gateway aktif, sinkron channel, dan aktifkan metode yang berhasil dipetakan?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><?= ui_icon('check') ?> Setup Otomatis Tripay</button>
                </form>
            </div>
            <?php if (! empty($tripayChannelGroups)): ?>
                <div class="payment-stat-strip">
                    <?php foreach ($tripayChannelGroups as $label => $count): ?><article><span><?= esc($label) ?></span><strong><?= number_format((int) $count) ?></strong><small>channel</small></article><?php endforeach ?>
                </div>
            <?php endif ?>
            <details class="payment-guide-pro">
                <summary><?= ui_icon('info') ?><span><strong>Cara kerja fee Tripay</strong><small>Lihat ringkasan pemisahan biaya agar tidak double-fee.</small></span><?= ui_icon('arrow-right') ?></summary>
                <div><p><strong>Tambahan Toko</strong> adalah biaya milik Anda dan menambah nominal checkout. <strong>Biaya Tripay Merchant</strong> dipotong dari penerimaan merchant dan dikunci dari hasil sinkron. <strong>Fee Pelanggan Tripay</strong> adalah biaya yang dibebankan Tripay langsung kepada pelanggan. Nilai transaksi aktual tetap mengikuti respons Tripay.</p></div>
            </details>
        </div>
    <?php elseif ($activeGateway === 'midtrans'): ?>
        <div class="payment-gateway-panel payment-gateway-panel--midtrans">
            <div class="payment-gateway-panel__head">
                <div>
                    <span class="payment-gateway-kicker">Gateway aktif</span>
                    <h3>Midtrans <small><?= esc($midtransEnvironment) ?></small></h3>
                    <p><?= count($midtransMethodMap) ?> metode Core API dikenali<?= $midtransSetupAt !== '' ? ' · setup terakhir ' . esc($midtransSetupAt) : '' ?>.</p>
                </div>
                <span class="payment-health-badge <?= $midtransCoreVerified ? 'payment-health-badge--ok' : 'payment-health-badge--warn' ?>"><i></i> <?= $midtransCoreVerified ? 'Core API terverifikasi' : 'Belum tes Core API' ?></span>
            </div>
            <div class="payment-gateway-actions">
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/test') ?>" method="post" data-loading-text="Memeriksa Server Key Midtrans...">
                    <?= csrf_field() ?>
                    <button class="btn btn-gold" type="submit"><?= ui_icon('refresh') ?> Tes Server Key</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/core-test') ?>" method="post" data-loading-text="Menguji QRIS Core API..." onsubmit="return confirm('Tes akan membuat transaksi QRIS Rp10.000 lalu langsung membatalkannya. Tidak ada dana yang ditarik. Lanjutkan?')">
                    <?= csrf_field() ?><input type="hidden" name="channel" value="qris">
                    <button class="btn btn-ghost" type="submit"><?= ui_icon('search') ?> Tes Core QRIS</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/core-test') ?>" method="post" data-loading-text="Menguji GoPay Core API..." onsubmit="return confirm('Tes akan membuat transaksi GoPay Rp10.000 lalu langsung membatalkannya. Tidak ada dana yang ditarik. Lanjutkan?')">
                    <?= csrf_field() ?><input type="hidden" name="channel" value="gopay">
                    <button class="btn btn-ghost" type="submit"><?= ui_icon('search') ?> Tes Core GoPay</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/auto-setup') ?>" method="post" data-loading-text="Menyiapkan metode Midtrans..." onsubmit="return confirm('Setup otomatis akan menjadikan Midtrans gateway aktif, menerapkan mapping Core API, mengaktifkan metode standar, dan mematikan metode yang belum didukung adapter. Lanjutkan?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><?= ui_icon('check') ?> Setup Otomatis Midtrans</button>
                </form>
            </div>
            <div class="midtrans-core-diagnostic-grid">
                <?php foreach (['QRIS' => $midtransQrisTest, 'GoPay' => $midtransGopayTest] as $diagLabel => $diag): ?>
                    <article class="midtrans-core-diagnostic <?= $diag['status'] === 'ok' ? 'is-ok' : ($diag['status'] === 'error' ? 'is-error' : '') ?>">
                        <div><strong><?= esc($diagLabel) ?></strong><span><?= $diag['status'] === 'ok' ? 'Terverifikasi' : ($diag['status'] === 'error' ? 'Gagal' : 'Belum dites') ?></span></div>
                        <?php if ($diag['code'] !== ''): ?><small>Midtrans code <?= esc($diag['code']) ?></small><?php endif ?>
                        <?php if ($diag['message'] !== ''): ?><p><?= esc($diag['message']) ?></p><?php else: ?><p>Tes Core API belum dijalankan untuk channel ini.</p><?php endif ?>
                        <?php if ($diag['at'] !== ''): ?><time><?= esc($diag['at']) ?></time><?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
            <?php if (! empty($midtransChannelGroups)): ?>
                <div class="payment-stat-strip">
                    <?php foreach ($midtransChannelGroups as $label => $count): ?><article><span><?= esc($label) ?></span><strong><?= number_format((int) $count) ?></strong><small>metode</small></article><?php endforeach ?>
                </div>
            <?php endif ?>
            <details class="payment-guide-pro">
                <summary><?= ui_icon('info') ?><span><strong>Cara kerja Midtrans</strong><small>Mapping Core API, fee, dan dukungan refund.</small></span><?= ui_icon('arrow-right') ?></summary>
                <div><p>Website memetakan metode publik ke payment type resmi Core API. Aktivasi channel tetap mengikuti Dashboard Midtrans. Biaya merchant pada tabel adalah pencatatan internal; hanya <strong>Tambahan Toko</strong> yang menambah gross amount pelanggan. QRIS/GoPay/OVO/ShopeePay dapat memakai jalur auto-refund adapter, sedangkan VA/retail ditandai untuk refund manual/review.</p></div>
            </details>
        </div>
    <?php else: ?>
        <div class="payment-empty-gateway">
            <span><?= ui_icon('info') ?></span>
            <div><strong>Gateway pembayaran belum siap dikelola di halaman ini</strong><p>Pilih Tripay atau Midtrans sebagai gateway aktif melalui Integrasi API.</p></div>
            <a class="btn btn-primary btn-sm" href="<?= site_url('admin/integrasi') ?>">Buka Integrasi</a>
        </div>
    <?php endif ?>

    <?php if ($activeGateway !== 'midtrans'): ?>
        <div class="payment-alternative-gateway">
            <div class="payment-alternative-gateway__icon"><?= ui_icon('wallet') ?></div>
            <div><strong>Midtrans tersedia sebagai alternatif</strong><p>Mapping QRIS, GoPay, OVO, ShopeePay, VA bank, Mandiri Bill, Alfamart, dan Indomaret sudah disiapkan oleh adapter.</p></div>
            <div class="payment-alternative-gateway__actions">
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/test') ?>" method="post" data-loading-text="Memeriksa Server Key Midtrans...">
                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= ui_icon('refresh') ?> Tes Server Key</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/core-test') ?>" method="post" data-loading-text="Menguji QRIS Core API..." onsubmit="return confirm('Tes QRIS akan membuat transaksi Rp10.000 lalu langsung membatalkannya. Lanjutkan?')">
                    <?= csrf_field() ?><input type="hidden" name="channel" value="qris"><button class="btn btn-ghost btn-sm" type="submit"><?= ui_icon('search') ?> Tes QRIS</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/core-test') ?>" method="post" data-loading-text="Menguji GoPay Core API..." onsubmit="return confirm('Tes GoPay akan membuat transaksi Rp10.000 lalu langsung membatalkannya. Lanjutkan?')">
                    <?= csrf_field() ?><input type="hidden" name="channel" value="gopay"><button class="btn btn-ghost btn-sm" type="submit"><?= ui_icon('search') ?> Tes GoPay</button>
                </form>
                <form action="<?= site_url('admin/metode-pembayaran/midtrans/auto-setup') ?>" method="post" data-loading-text="Menyiapkan metode Midtrans..." onsubmit="return confirm('Pindah gateway aktif ke Midtrans dan terapkan mapping metode pembayaran standar?')">
                    <?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= ui_icon('check') ?> Gunakan Midtrans</button>
                </form>
            </div>
        </div>
    <?php endif ?>
</section>

<form action="<?= site_url('admin/metode-pembayaran') ?>" method="post" class="admin-stack payment-method-form" data-loading-text="Menyimpan metode pembayaran...">
    <?= csrf_field() ?>

    <div class="payment-section-heading">
        <div><span class="payment-eyebrow">Channel pelanggan</span><h2>Atur metode pembayaran</h2><p>Aktifkan hanya metode yang tersedia pada akun gateway Anda. Di HP, setiap baris otomatis berubah menjadi kartu agar mudah diedit.</p></div>
        <span class="payment-section-heading__count"><?= number_format($enabledMethods) ?> <small>aktif</small></span>
    </div>

    <?php foreach ($groups as $groupKey => $group): ?>
        <?php
        $groupTotal = count($group['items']);
        $groupEnabled = 0;
        foreach ($group['items'] as $rowKey => $rowOption) {
            $rowSupported = $activeGateway !== 'midtrans'
                || \App\Libraries\Payments\PaymentGatewayCapabilities::resolveChannel('midtrans', (string) $rowKey, (string) ($rowOption['channel'] ?? '')) !== null;
            if (! empty($rowOption['enabled']) && $rowSupported) $groupEnabled++;
        }
        $groupDescription = $groupKey === 'qris' ? 'Pembayaran scan QR yang cepat dan universal.' : ($groupKey === 'e_wallet' ? 'Dompet digital dan aplikasi pembayaran.' : ($groupKey === 'virtual_account' ? 'Virtual Account bank untuk transfer otomatis.' : 'Pembayaran melalui gerai retail.'));
        ?>
        <details class="admin-card payment-table-card payment-group-card admin-disclosure" data-payment-group-card open>
            <summary>
                <div class="payment-group-summary">
                    <span class="payment-group-icon"><?= ui_icon($groupKey === 'qris' ? 'grid' : ($groupKey === 'e_wallet' ? 'wallet' : 'receipt')) ?></span>
                    <div><h2><?= esc($group['label']) ?></h2><p><?= esc($groupDescription) ?></p></div>
                </div>
                <div class="payment-group-summary__right"><span class="payment-group-count" data-group-count><b><?= number_format($groupEnabled) ?></b>/<?= number_format($groupTotal) ?> aktif</span><span class="payment-group-chevron"><?= ui_icon('arrow-right') ?></span></div>
            </summary>
            <div class="table-wrap payment-table-wrap">
                <table class="data-table payment-method-table payment-method-table--advanced payment-method-table--responsive" data-admin-datatable data-page-size="50">
                    <thead><tr>
                        <th>Aktif</th><th>Metode Publik</th><th>Channel <?= $activeGateway === 'tripay' ? 'Tripay' : ($activeGateway === 'midtrans' ? 'Midtrans' : 'Gateway') ?></th>
                        <th>Tambahan Toko (Rp)</th><th>Tambahan Toko (%)</th><th>Min Tambahan</th><th>Maks Tambahan</th>
                        <th>Biaya Gateway Merchant</th><th>Merchant %</th><th>Info <?= $activeGateway === 'tripay' ? 'Tripay' : ($activeGateway === 'midtrans' ? 'Midtrans' : 'Gateway') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($group['items'] as $key => $option): ?>
                        <?php
                        $channelCode = strtoupper((string) ($option['channel'] ?? ''));
                        $tripayRow = $tripayChannelMap[$channelCode] ?? null;
                        $midtransResolved = $activeGateway === 'midtrans'
                            ? \App\Libraries\Payments\PaymentGatewayCapabilities::resolveChannel('midtrans', (string) $key, (string) ($option['channel'] ?? ''))
                            : null;
                        $methodSelectable = $activeGateway !== 'midtrans' || $midtransResolved !== null;
                        ?>
                        <tr class="payment-method-row <?= $methodSelectable ? '' : 'is-unsupported' ?>">
                            <td data-label="Status" class="payment-method-cell payment-method-cell--toggle">
                                <label class="payment-method-toggle"><input type="checkbox" name="enabled_<?= esc($key) ?>" value="1" data-payment-enabled <?= $option['enabled'] && $methodSelectable ? 'checked' : '' ?> <?= ! $methodSelectable ? 'disabled' : '' ?>><span></span><b aria-hidden="true"></b></label>
                                <?php if (! $methodSelectable): ?><small class="payment-method-warning">Tidak tersedia untuk adapter Midtrans.</small><?php endif ?>
                            </td>
                            <td data-label="Metode" class="payment-method-cell payment-method-cell--brand">
                                <div class="payment-table-brand"><span><?= esc($option['brand']) ?></span><div><strong><?= esc($option['label']) ?></strong><small><?= esc($option['description']) ?></small></div></div>
                                <input type="hidden" name="label_<?= esc($key) ?>" value="<?= esc($option['label']) ?>">
                                <input type="hidden" name="description_<?= esc($key) ?>" value="<?= esc($option['description']) ?>">
                            </td>
                            <td data-label="Channel <?= esc($gatewayLabel) ?>" class="payment-method-cell payment-method-cell--channel">
                                <?php if ($activeGateway === 'tripay' && $tripayChannels !== []): ?>
                                    <select class="form-control form-control--compact" name="channel_<?= esc($key) ?>">
                                        <option value="">-- Tidak dipakai --</option>
                                        <?php foreach ($tripayChannels as $channel): ?>
                                            <?php $code = strtoupper((string) ($channel['code'] ?? '')); ?>
                                            <option value="<?= esc($code) ?>" <?= $channelCode === $code ? 'selected' : '' ?>><?= esc($code . ' · ' . ($channel['name'] ?? $code) . ' · ' . ($channel['group'] ?? 'Lainnya')) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                <?php elseif ($activeGateway === 'midtrans'): ?>
                                    <input type="hidden" name="channel_<?= esc($key) ?>" value="<?= esc((string) $midtransResolved) ?>">
                                    <input class="form-control form-control--compact" value="<?= esc($midtransResolved ?: 'Tidak didukung Core API') ?>" readonly>
                                    <small><?= $midtransResolved ? 'Mapping otomatis Core API.' : 'Disembunyikan dari checkout saat Midtrans aktif.' ?></small>
                                <?php else: ?>
                                    <input class="form-control form-control--compact" name="channel_<?= esc($key) ?>" value="<?= esc($option['channel']) ?>" placeholder="Kode channel">
                                <?php endif ?>
                            </td>
                            <td data-label="Tambahan Toko (Rp)" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" step="1" name="fee_<?= esc($key) ?>" value="<?= esc((string) $option['fee']) ?>"><small>Menambah tagihan pelanggan</small></td>
                            <td data-label="Tambahan Toko (%)" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" max="100" step="0.01" name="fee_percent_<?= esc($key) ?>" value="<?= esc((string) ($option['fee_percent'] ?? 0)) ?>"></td>
                            <td data-label="Min Tambahan" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" step="1" name="fee_min_<?= esc($key) ?>" value="<?= esc((string) ($option['fee_min'] ?? 0)) ?>"></td>
                            <td data-label="Maks Tambahan" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" step="1" name="fee_max_<?= esc($key) ?>" value="<?= esc((string) ($option['fee_max'] ?? 0)) ?>"></td>
                            <td data-label="Biaya Merchant" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" step="1" name="merchant_cost_<?= esc($key) ?>" value="<?= esc((string) ($option['merchant_cost'] ?? 0)) ?>" <?= $activeGateway === 'tripay' ? 'readonly' : '' ?>><small><?= $activeGateway === 'tripay' ? 'Otomatis dari Tripay' : 'Catatan internal' ?></small></td>
                            <td data-label="Merchant %" class="payment-method-cell"><input class="form-control form-control--compact" type="number" min="0" max="100" step="0.01" name="merchant_cost_percent_<?= esc($key) ?>" value="<?= esc((string) ($option['merchant_cost_percent'] ?? 0)) ?>" <?= $activeGateway === 'tripay' ? 'readonly' : '' ?>></td>
                            <td data-label="Info Gateway" class="payment-method-cell payment-method-cell--info">
                                <?php if ($activeGateway === 'tripay' && $tripayRow): ?>
                                    <span class="payment-info-badge"><?= esc(strtoupper((string) ($tripayRow['type'] ?? 'direct'))) ?></span>
                                    <small>Fee pelanggan: <?= rupiah((float) ($option['tripay_customer_fee_flat'] ?? 0)) ?><?= (float) ($option['tripay_customer_fee_percent'] ?? 0) > 0 ? ' + ' . esc((string) $option['tripay_customer_fee_percent']) . '%' : '' ?></small>
                                    <small>Fee: <?= rupiah((float) ($option['tripay_minimum_fee'] ?? 0)) ?> – <?= (float) ($option['tripay_maximum_fee'] ?? 0) > 0 ? rupiah((float) $option['tripay_maximum_fee']) : 'tanpa batas' ?></small>
                                    <small>Nominal: <?= rupiah((float) ($option['tripay_minimum_amount'] ?? 0)) ?> – <?= (float) ($option['tripay_maximum_amount'] ?? 0) > 0 ? rupiah((float) $option['tripay_maximum_amount']) : 'tanpa batas' ?></small>
                                <?php elseif ($activeGateway === 'tripay'): ?>
                                    <small>Belum dipetakan / channel tidak aktif.</small>
                                <?php elseif ($activeGateway === 'midtrans'): ?>
                                    <?php if ($midtransResolved): ?>
                                        <span class="payment-info-badge">CORE API</span>
                                        <small><?= esc($midtransResolved) ?></small>
                                        <small><?= \App\Libraries\Payments\PaymentGatewayCapabilities::midtransAutoRefundSupported((string) $key) ? 'Auto-refund didukung adapter' : 'Refund gagal layanan: manual/review' ?></small>
                                        <small>Pastikan aktif di Dashboard Midtrans.</small>
                                    <?php else: ?>
                                        <small>Tidak didukung adapter Midtrans saat ini.</small>
                                    <?php endif ?>
                                <?php else: ?><small>-</small><?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endforeach ?>

    <section class="admin-card payment-calculation-card">
        <div class="payment-calculation-card__icon"><?= ui_icon('info') ?></div>
        <div>
            <span class="payment-eyebrow">Contoh perhitungan</span>
            <h2><?= $activeGateway === 'midtrans' ? 'Gross amount Midtrans' : 'Biaya checkout Tripay' ?></h2>
            <?php if ($activeGateway === 'midtrans'): ?>
                <p>Harga produk Rp100.000 + Tambahan Toko Rp500 + 1% = Rp101.500. Bila Min Tambahan Rp2.000, total menjadi Rp102.000. Biaya merchant Midtrans di tabel hanya untuk pencatatan internal.</p>
            <?php else: ?>
                <p>Harga produk Rp100.000 + Tambahan Toko Rp500 + 1% = Rp101.500. Bila Min Tambahan Rp2.000, request Tripay menjadi Rp102.000. Fee resmi Tripay kemudian mengikuti respons gateway dan tidak didobel oleh website.</p>
            <?php endif ?>
        </div>
    </section>

    <div class="payment-savebar" data-payment-savebar>
        <div><strong>Simpan konfigurasi metode</strong><small>Perubahan ON/OFF, channel, dan fee berlaku untuk checkout berikutnya.</small></div>
        <button class="btn btn-primary btn-lg" type="submit"><?= ui_icon('check') ?> Simpan Metode Pembayaran</button>
    </div>
</form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.payment-method-form');
    if (!form) return;
    const summary = document.querySelector('[data-payment-enabled-summary]');
    const savebar = form.querySelector('[data-payment-savebar]');
    const saveTitle = savebar?.querySelector('strong');
    const updateCounts = () => {
        const all = [...form.querySelectorAll('[data-payment-enabled]')];
        const enabled = all.filter(input => !input.disabled && input.checked).length;
        if (summary) summary.textContent = `${enabled} aktif`;
        form.querySelectorAll('[data-payment-group-card]').forEach(card => {
            const inputs = [...card.querySelectorAll('[data-payment-enabled]')];
            const active = inputs.filter(input => !input.disabled && input.checked).length;
            const counter = card.querySelector('[data-group-count]');
            if (counter) counter.innerHTML = `<b>${active}</b>/${inputs.length} aktif`;
        });
    };
    form.addEventListener('change', (event) => {
        if (event.target.matches('input, select')) {
            form.classList.add('is-dirty');
            if (saveTitle) saveTitle.textContent = 'Ada perubahan belum disimpan';
        }
        if (event.target.matches('[data-payment-enabled]')) updateCounts();
    });
    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="number"], input[type="text"]')) {
            form.classList.add('is-dirty');
            if (saveTitle) saveTitle.textContent = 'Ada perubahan belum disimpan';
        }
    });
    updateCounts();
});
</script>
<?= $this->endSection() ?>
