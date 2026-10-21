<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);
$pageTitle = 'Buat Opname Gudang';
$db = Database::connect();
wopname_install($db);
$wh = wopname_warehouse($db);

if (is_post()) {
    try {
        if (!CSRF::verify()) throw new Exception('Token keamanan tidak valid.');
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') $title = 'Opname Gudang ' . date('d/m/Y H:i');
        $includeZero = !empty($_POST['include_zero']);
        $note = trim((string) ($_POST['note'] ?? ''));

        $db->beginTransaction();
        $code = wopname_code($db);
        $db->prepare("INSERT INTO warehouse_opnames (code,title,warehouse_id,user_id,status,note,created_at) VALUES (?,?,?,?,'draft',?,NOW())")
            ->execute([$code, $title, (int) $wh['id'], (int) Auth::id(), $note !== '' ? $note : null]);
        $id = (int) $db->lastInsertId();
        $count = wopname_seed_items($db, $id, $includeZero);
        $db->commit();
        Auth::log('warehouse_opname_create', 'Buat opname gudang ' . $code . ' (' . $count . ' item)');
        flash('success', 'Sesi ' . $code . ' dibuat dengan ' . $count . ' produk. Isi stok fisik lalu finalisasi.');
        redirect('admin/stock-opname-gudang/edit.php?id=' . $id);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Gagal membuat opname: ' . $e->getMessage());
        redirect('admin/stock-opname-gudang/create.php');
    }
}

$stockCount = (int) $db->query("SELECT COUNT(*) FROM warehouse_stocks WHERE warehouse_id=" . (int) $wh['id'] . " AND stock>0")->fetchColumn();
$allCount = (int) $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/stock-opname.css?v=' . APP_ASSET_VERSION); ?>">
<main class="admin-main stock-opname-page">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><h1>Buat Opname Gudang</h1><p>Produk stok 0 otomatis dilewati. Stok sistem diambil dari <?= e($wh['name']); ?>.</p></div>
    </header>

    <section class="opname-card">
        <form method="post" class="opname-form">
            <?= CSRF::input(); ?>
            <div class="opname-field"><label>Judul Sesi</label><input type="text" name="title" maxlength="150" placeholder="Contoh: Opname Gudang Oktober 2026"></div>
            <div class="opname-field"><label>Catatan</label><textarea name="note" rows="2" maxlength="500" placeholder="Opsional"></textarea></div>
            <div class="opname-field">
                <label>Produk yang dimasukkan</label>
                <label class="opname-check"><input type="checkbox" name="include_zero" value="1"> Sertakan produk stok 0 (<?= number_format($allCount, 0, ',', '.'); ?> produk aktif)</label>
                <small>Tanpa centang: hanya <?= number_format($stockCount, 0, ',', '.'); ?> produk dengan stok &gt; 0 di gudang — sama seperti template Excel.</small>
            </div>
            <div class="opname-actions">
                <button class="opname-btn primary" type="submit"><i class="bi bi-plus-circle"></i> Buat Sesi</button>
                <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php'); ?>">Batal</a>
            </div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
