<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);
$db = Database::connect();
wopname_install($db);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$opname = wopname_get($db, $id);
if (!$opname) {
    flash('error', 'Sesi opname tidak ditemukan.');
    redirect('admin/stock-opname-gudang/index.php');
}
$pageTitle = 'Opname ' . $opname['code'];

if (is_post() && ($_POST['action'] ?? '') === 'save') {
    try {
        if (!CSRF::verify()) throw new Exception('Token keamanan tidak valid.');
        if ((string) $opname['status'] !== 'draft') throw new Exception('Sesi sudah final, tidak bisa diubah.');
        $physical = is_array($_POST['physical'] ?? null) ? $_POST['physical'] : [];
        $notes = is_array($_POST['note'] ?? null) ? $_POST['note'] : [];
        $upd = $db->prepare("UPDATE warehouse_opname_items SET physical_stock=?, difference=?, difference_value=?, note=? WHERE id=? AND opname_id=?");
        $saved = 0;
        foreach ($physical as $itemId => $raw) {
            $itemId = (int) $itemId;
            $raw = trim((string) $raw);
            if ($raw === '') {
                $upd->execute([null, 0, 0, null, $itemId, $id]);
                continue;
            }
            $val = (int) preg_replace('/[^0-9\-]/', '', $raw);
            if ($val < 0) $val = 0;
            $cur = $db->prepare("SELECT system_stock, purchase_price FROM warehouse_opname_items WHERE id=? AND opname_id=?");
            $cur->execute([$itemId, $id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $diff = $val - (int) $row['system_stock'];
            $note = trim((string) ($notes[$itemId] ?? ''));
            $upd->execute([$val, $diff, $diff * (float) $row['purchase_price'], $note !== '' ? $note : null, $itemId, $id]);
            $saved++;
        }
        wopname_sync_totals($db, $id);
        Auth::log('warehouse_opname_save', 'Simpan opname gudang ' . $opname['code'] . ' (' . $saved . ' baris)');
        flash('success', 'Stok fisik disimpan (' . $saved . ' baris). Stok gudang belum berubah sampai difinalisasi.');
    } catch (Throwable $e) {
        flash('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    redirect('admin/stock-opname-gudang/edit.php?id=' . $id);
}

$opname = wopname_get($db, $id);
$items = wopname_items($db, $id);
$isDraft = (string) $opname['status'] === 'draft';

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/stock-opname.css?v=' . APP_ASSET_VERSION); ?>">
<main class="admin-main stock-opname-page">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1><?= e($opname['code']); ?> — <?= e($opname['title']); ?></h1>
            <p><?= e($opname['user_name'] ?: '-'); ?> • <?= e(date('d/m/Y H:i', strtotime((string) $opname['created_at']))); ?> • <?= wopname_badge((string) $opname['status']); ?></p>
        </div>
        <div class="topbar-actions">
            <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php'); ?>">Kembali</a>
            <?php if ($isDraft): ?>
            <button class="opname-btn soft" type="submit" form="wopnameForm"><i class="bi bi-save"></i> Simpan Draft</button>
            <button class="opname-btn primary" type="submit" form="wopnameFinalize" onclick="return confirm('Finalisasi opname ini? Stok gudang akan disesuaikan dan tidak bisa dibatalkan.');"><i class="bi bi-check2-circle"></i> Finalisasi</button>
            <?php endif; ?>
        </div>
    </header>

    <section class="opname-grid">
        <div class="opname-stat"><div class="icon"><i class="bi bi-box"></i></div><span>Item</span><h3><?= count($items); ?></h3><small>produk di sesi</small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-arrow-up-circle"></i></div><span>Lebih</span><h3><?= (int) $opname['total_plus']; ?></h3><small>Rp <?= number_format((float) $opname['total_plus_value'], 0, ',', '.'); ?></small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-arrow-down-circle"></i></div><span>Kurang</span><h3><?= (int) $opname['total_minus']; ?></h3><small>Rp <?= number_format((float) $opname['total_minus_value'], 0, ',', '.'); ?></small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-clock-history"></i></div><span>Status</span><h3 style="font-size:18px"><?= $isDraft ? 'Draft' : 'Final'; ?></h3><small><?= $opname['finalized_at'] ? date('d/m/Y H:i', strtotime((string) $opname['finalized_at'])) : 'belum final'; ?></small></div>
    </section>

    <section class="opname-card">
        <div class="opname-card-head">
            <div><h3>Isi Stok Fisik</h3><p>Kosongkan baris yang belum dihitung. Selisih dihitung otomatis: fisik − stok sistem.</p></div>
            <input type="search" id="wopnameSearch" class="opname-search" placeholder="Cari nama / kode produk...">
        </div>
        <form method="post" id="wopnameForm">
            <?= CSRF::input(); ?>
            <input type="hidden" name="id" value="<?= (int) $id; ?>">
            <input type="hidden" name="action" value="save">
            <div class="opname-table-wrap">
                <table class="opname-table" id="wopnameTable">
                    <thead><tr><th style="width:44px">#</th><th>Produk</th><th>Satuan</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Catatan</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $n => $it): $diff = $it['physical_stock'] === null ? null : (int) $it['difference']; ?>
                        <tr data-search="<?= e(strtolower($it['product_name'] . ' ' . $it['product_code'])); ?>">
                            <td><?= $n + 1; ?></td>
                            <td><b><?= e($it['product_name']); ?></b><br><small class="muted"><?= e($it['product_code']); ?></small></td>
                            <td><?= e($it['unit'] ?: 'pcs'); ?></td>
                            <td><?= (int) $it['system_stock']; ?></td>
                            <td>
                                <?php if ($isDraft): ?>
                                <input type="number" name="physical[<?= (int) $it['id']; ?>]" value="<?= $it['physical_stock'] === null ? '' : (int) $it['physical_stock']; ?>" min="0" step="1" class="opname-input" data-system="<?= (int) $it['system_stock']; ?>">
                                <?php else: ?>
                                <?= $it['physical_stock'] === null ? '-' : (int) $it['physical_stock']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($diff === null): ?><span class="opname-chip">-</span>
                                <?php elseif ($diff === 0): ?><span class="opname-chip same">0</span>
                                <?php elseif ($diff > 0): ?><span class="opname-chip plus">+<?= $diff; ?></span>
                                <?php else: ?><span class="opname-chip minus"><?= $diff; ?></span><?php endif; ?>
                            </td>
                            <td><?php if ($isDraft): ?><input type="text" name="note[<?= (int) $it['id']; ?>]" value="<?= e((string) ($it['note'] ?? '')); ?>" maxlength="120" class="opname-input note"><?php else: ?><?= e((string) ($it['note'] ?? '')); ?><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr><td colspan="7" class="opname-empty">Sesi ini tidak punya item. Buat sesi baru.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <?php if ($isDraft): ?>
        <form method="post" action="<?= base_url('admin/stock-opname-gudang/finalize.php'); ?>" id="wopnameFinalize">
            <?= CSRF::input(); ?>
            <input type="hidden" name="id" value="<?= (int) $id; ?>">
        </form>
        <?php endif; ?>
    </section>
</main>
<script>
(function () {
    var search = document.getElementById('wopnameSearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            document.querySelectorAll('#wopnameTable tbody tr[data-search]').forEach(function (tr) {
                tr.style.display = !q || tr.dataset.search.indexOf(q) >= 0 ? '' : 'none';
            });
        });
    }
    document.querySelectorAll('.opname-input[data-system]').forEach(function (input) {
        input.addEventListener('input', function () {
            var row = input.closest('tr');
            var cell = row ? row.children[5] : null;
            if (!cell) return;
            var system = parseInt(input.dataset.system, 10) || 0;
            if (input.value === '') { cell.innerHTML = '<span class="opname-chip">-</span>'; return; }
            var diff = (parseInt(input.value, 10) || 0) - system;
            if (diff === 0) cell.innerHTML = '<span class="opname-chip same">0</span>';
            else if (diff > 0) cell.innerHTML = '<span class="opname-chip plus">+' + diff + '</span>';
            else cell.innerHTML = '<span class="opname-chip minus">' + diff + '</span>';
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
