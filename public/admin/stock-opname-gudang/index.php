<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);
$pageTitle = 'Stok Opname Gudang';
$db = Database::connect();
wopname_install($db);
$wh = wopname_warehouse($db);

$status = (string) ($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($status, ['draft', 'finalized', 'canceled'], true)) {
    $where = 'WHERE o.status=?';
    $params[] = $status;
}
$st = $db->prepare("SELECT o.*, u.name user_name FROM warehouse_opnames o LEFT JOIN users u ON u.id=o.user_id {$where} ORDER BY o.id DESC LIMIT 100");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$summary = [
    'sessions' => (int) $db->query("SELECT COUNT(*) FROM warehouse_opnames")->fetchColumn(),
    'draft' => (int) $db->query("SELECT COUNT(*) FROM warehouse_opnames WHERE status='draft'")->fetchColumn(),
    'finalized' => (int) $db->query("SELECT COUNT(*) FROM warehouse_opnames WHERE status='finalized'")->fetchColumn(),
    'selisih' => (int) $db->query("SELECT COALESCE(SUM(total_plus+total_minus),0) FROM warehouse_opnames WHERE status='finalized'")->fetchColumn(),
    'nilai' => (float) $db->query("SELECT COALESCE(SUM(total_plus_value+total_minus_value),0) FROM warehouse_opnames WHERE status='finalized'")->fetchColumn(),
    'produk_gudang' => (int) $db->query("SELECT COUNT(*) FROM warehouse_stocks WHERE warehouse_id=" . (int) $wh['id'] . " AND stock>0")->fetchColumn(),
];

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/stock-opname.css?v=' . APP_ASSET_VERSION); ?>">
<main class="admin-main stock-opname-page">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1>Stok Opname Gudang</h1>
            <p>Hitung fisik stok gudang (<?= e($wh['name']); ?>) — terpisah dari stok cabang. Stok gudang hanya berubah setelah sesi difinalisasi.</p>
        </div>
        <div class="topbar-actions">
            <a href="<?= base_url('admin/stock-opname-gudang/template.php'); ?>" class="opname-btn soft"><i class="bi bi-file-earmark-excel"></i> Template Excel</a>
            <a href="<?= base_url('admin/stock-opname-gudang/import.php'); ?>" class="opname-btn soft"><i class="bi bi-upload"></i> Upload Excel</a>
            <a href="<?= base_url('admin/stock-opname-gudang/create.php'); ?>" class="opname-btn primary"><i class="bi bi-plus-circle"></i> Buat Opname</a>
        </div>
    </header>

    <section class="opname-grid">
        <div class="opname-stat"><div class="icon"><i class="bi bi-box-seam"></i></div><span>Produk Stok &gt; 0</span><h3><?= number_format($summary['produk_gudang'], 0, ',', '.'); ?></h3><small>di <?= e($wh['name']); ?></small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-clipboard-data"></i></div><span>Total Sesi</span><h3><?= (int) $summary['sessions']; ?></h3><small><?= (int) $summary['draft']; ?> draft</small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-check-circle"></i></div><span>Final</span><h3><?= (int) $summary['finalized']; ?></h3><small>sudah menyesuaikan stok</small></div>
        <div class="opname-stat"><div class="icon"><i class="bi bi-arrow-left-right"></i></div><span>Total Selisih</span><h3><?= number_format($summary['selisih'], 0, ',', '.'); ?></h3><small>Rp <?= number_format($summary['nilai'], 0, ',', '.'); ?></small></div>
    </section>

    <section class="opname-card">
        <div class="opname-card-head">
            <div><h3>Riwayat Opname Gudang</h3><p>Draft belum mengubah stok. Finalisasi menulis penyesuaian ke stok gudang + kartu stok.</p></div>
            <div class="opname-actions">
                <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php'); ?>">Semua</a>
                <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php?status=draft'); ?>">Draft</a>
                <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php?status=finalized'); ?>">Final</a>
            </div>
        </div>
        <div class="opname-table-wrap">
            <table class="opname-table">
                <thead><tr><th>Kode</th><th>Judul</th><th>Status</th><th>Item</th><th>+ / -</th><th>Nilai Selisih</th><th>Dibuat</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="opname-empty">Belum ada sesi opname gudang. Klik <b>Buat Opname</b> atau unggah Excel.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): $diffVal = (float) $r['total_plus_value'] + (float) $r['total_minus_value']; ?>
                    <tr>
                        <td><b><?= e($r['code']); ?></b></td>
                        <td><?= e($r['title']); ?></td>
                        <td><?= wopname_badge((string) $r['status']); ?></td>
                        <td><?= (int) $r['total_items']; ?></td>
                        <td><span class="opname-chip plus">+<?= (int) $r['total_plus']; ?></span> <span class="opname-chip minus">-<?= (int) $r['total_minus']; ?></span></td>
                        <td>Rp <?= number_format($diffVal, 0, ',', '.'); ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $r['created_at']))); ?></td>
                        <td class="opname-row-actions"><a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/edit.php?id=' . (int) $r['id']); ?>"><i class="bi bi-pencil-square"></i> <?= $r['status'] === 'draft' ? 'Isi' : 'Lihat'; ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
