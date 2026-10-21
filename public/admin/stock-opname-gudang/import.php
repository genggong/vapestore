<?php
/**
 * V17.0.43 — Upload Excel Stok Opname GUDANG (hasil template opname gudang).
 */
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/Modules/DataExchange/Xlsx.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);
$pageTitle = 'Upload Excel Opname Gudang';
$db = Database::connect();
wopname_install($db);
$wh = wopname_warehouse($db);

function wopname_header(string $v): string
{
    $v = trim(strtolower($v));
    $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
    $v = str_replace([' ', '-', '.', '/', '\\'], '_', $v);
    return trim((string) preg_replace('/[^a-z0-9_]/', '', $v), '_');
}

if (is_post()) {
    try {
        if (!CSRF::verify()) throw new Exception('Token keamanan tidak valid.');
        $file = $_FILES['opname_file'] ?? [];
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Pilih file Excel .xlsx terlebih dahulu.');
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new Exception('Format file harus XLSX.');
        }
        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new Exception('Ukuran file maksimal 10 MB.');
        }

        $matrix = KpXlsx::readFirstSheet($file['tmp_name'], 5005);
        if (!$matrix) throw new Exception('File Excel kosong atau tidak terbaca.');

        $headerIndex = null;
        $headers = [];
        foreach (array_slice($matrix, 0, 12, true) as $i => $candidate) {
            $norm = array_map('wopname_header', (array) $candidate);
            if (in_array('name', $norm, true) && in_array('physical_stock', $norm, true)) {
                $headerIndex = (int) $i;
                $headers = $norm;
                break;
            }
        }
        if ($headerIndex === null) throw new Exception('Header name & physical_stock tidak ditemukan. Pakai template opname gudang terbaru.');
        $map = array_flip($headers);

        $rows = [];
        foreach (array_slice($matrix, $headerIndex + 1) as $row) {
            $name = trim((string) ($row[$map['name']] ?? ''));
            if ($name === '') continue;
            $raw = trim((string) ($row[$map['physical_stock']] ?? ''));
            if ($raw === '') continue; // belum dihitung, dilewati
            $physical = (int) preg_replace('/[^0-9\-]/', '', $raw);
            if ($physical < 0) throw new Exception('Stok fisik tidak boleh minus: ' . $name);
            $rows[] = [
                'name' => $name,
                'product_id' => isset($map['product_id']) ? (int) ($row[$map['product_id']] ?? 0) : 0,
                'code' => isset($map['product_code']) ? trim((string) ($row[$map['product_code']] ?? '')) : '',
                'physical' => $physical,
                'note' => isset($map['note']) ? trim((string) ($row[$map['note']] ?? '')) : '',
            ];
        }
        if (!$rows) throw new Exception('Tidak ada baris dengan stok fisik terisi.');

        $findById = $db->prepare("SELECT id,name,product_code,unit,stock,purchase_price FROM products WHERE id=? AND is_active=1 LIMIT 1");
        $findByCode = $db->prepare("SELECT id,name,product_code,unit,stock,purchase_price FROM products WHERE product_code=? AND is_active=1 LIMIT 1");
        $findByName = $db->prepare("SELECT id,name,product_code,unit,stock,purchase_price FROM products WHERE name=? AND is_active=1 LIMIT 1");
        $stockMap = wopname_stock_map($db, (int) $wh['id']);

        $db->beginTransaction();
        $title = trim((string) ($_POST['title'] ?? '')) ?: ('Opname Gudang Excel ' . date('d/m/Y H:i'));
        $code = wopname_code($db);
        $db->prepare("INSERT INTO warehouse_opnames (code,title,warehouse_id,user_id,status,note,created_at) VALUES (?,?,?,?,'draft',?,NOW())")
            ->execute([$code, $title, (int) $wh['id'], (int) Auth::id(), 'Import Excel ' . $file['name']]);
        $opnameId = (int) $db->lastInsertId();

        $ins = $db->prepare("INSERT INTO warehouse_opname_items (opname_id,product_id,product_code,product_name,unit,system_stock,physical_stock,difference,purchase_price,difference_value,note,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $matched = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $p = null;
            if ($r['product_id'] > 0) { $findById->execute([$r['product_id']]); $p = $findById->fetch(PDO::FETCH_ASSOC) ?: null; }
            if (!$p && $r['code'] !== '') { $findByCode->execute([$r['code']]); $p = $findByCode->fetch(PDO::FETCH_ASSOC) ?: null; }
            if (!$p) { $findByName->execute([$r['name']]); $p = $findByName->fetch(PDO::FETCH_ASSOC) ?: null; }
            if (!$p) { $skipped++; continue; }

            $pid = (int) $p['id'];
            $system = isset($stockMap[$pid]) ? (int) $stockMap[$pid]['stock'] : (int) $p['stock'];
            $price = isset($stockMap[$pid]) ? (float) $stockMap[$pid]['purchase_price'] : (float) $p['purchase_price'];
            $diff = (int) $r['physical'] - $system;
            $ins->execute([$opnameId, $pid, (string) $p['product_code'], (string) $p['name'], (string) $p['unit'], $system, (int) $r['physical'], $diff, $price, $diff * $price, $r['note'] !== '' ? $r['note'] : null]);
            $matched++;
        }
        if ($matched < 1) throw new Exception('Tidak ada produk Excel yang cocok dengan database.');
        wopname_sync_totals($db, $opnameId);
        $db->commit();

        Auth::log('warehouse_opname_import', 'Import opname gudang ' . $code . ': ' . $matched . ' baris');
        flash('success', 'Import selesai: ' . $matched . ' baris masuk draft ' . $code . ($skipped > 0 ? ', ' . $skipped . ' baris tidak cocok' : '') . '. Periksa lalu finalisasi.');
        redirect('admin/stock-opname-gudang/edit.php?id=' . $opnameId);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Import gagal: ' . $e->getMessage());
        redirect('admin/stock-opname-gudang/import.php');
    }
}

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/stock-opname.css?v=' . APP_ASSET_VERSION); ?>">
<main class="admin-main stock-opname-page">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><h1>Upload Excel Opname Gudang</h1><p>Unggah file template yang sudah diisi kolom stok fisik. Sesi dibuat sebagai draft.</p></div>
        <div class="topbar-actions"><a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/template.php'); ?>"><i class="bi bi-download"></i> Unduh Template</a></div>
    </header>

    <section class="opname-card">
        <form method="post" enctype="multipart/form-data" class="opname-form">
            <?= CSRF::input(); ?>
            <div class="opname-field"><label>File Excel (.xlsx)</label><input type="file" name="opname_file" accept=".xlsx" required></div>
            <div class="opname-field"><label>Judul Sesi</label><input type="text" name="title" maxlength="150" placeholder="Opsional"></div>
            <div class="opname-actions">
                <button class="opname-btn primary" type="submit"><i class="bi bi-upload"></i> Impor</button>
                <a class="opname-btn soft" href="<?= base_url('admin/stock-opname-gudang/index.php'); ?>">Kembali</a>
            </div>
        </form>
        <p class="muted" style="margin-top:12px">Baris tanpa stok fisik dilewati. Produk yang tidak cocok dilewati dan dilaporkan. Stok gudang baru berubah setelah finalisasi.</p>
    </section>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
