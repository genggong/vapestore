<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin', 'owner', 'admin']);
$db = Database::connect();
wopname_install($db);

$id = (int) ($_POST['id'] ?? 0);
$opname = wopname_get($db, $id);
if (!$opname) {
    flash('error', 'Sesi opname tidak ditemukan.');
    legacy_redirect('admin/stock-opname-gudang/index.php');
}
if (!is_post() || !CSRF::verify()) {
    flash('error', 'Token keamanan tidak valid.');
    legacy_redirect('admin/stock-opname-gudang/edit.php?id=' . $id);
}
if ((string) $opname['status'] !== 'draft') {
    flash('error', 'Sesi ini sudah final.');
    legacy_redirect('admin/stock-opname-gudang/edit.php?id=' . $id);
}

$warehouseId = (int) $opname['warehouse_id'];
$items = wopname_items($db, $id);
$now = date('Y-m-d H:i:s');

try {
    $db->beginTransaction();

    $lock = $db->prepare("UPDATE warehouse_opnames SET status='finalized', finalized_at=?, finalized_by=? WHERE id=? AND status='draft'");
    $lock->execute([$now, (int) Auth::id(), $id]);
    if ($lock->rowCount() === 0) {
        throw new RuntimeException('Sesi sudah difinalisasi pengguna lain.');
    }

    $findStock = $db->prepare("SELECT stock FROM warehouse_stocks WHERE warehouse_id=? AND product_id=? FOR UPDATE");
    $updStock = $db->prepare("UPDATE warehouse_stocks SET stock=?, updated_at=NOW() WHERE warehouse_id=? AND product_id=?");
    $insStock = $db->prepare("INSERT INTO warehouse_stocks (warehouse_id,product_id,stock,minimum_stock,purchase_price,created_at) VALUES (?,?,?,0,?,NOW())");
    $movement = $db->prepare("INSERT INTO stock_movements (product_id,user_id,branch_id,type,qty,stock_before,stock_after,reference_type,reference_id,note,created_at)
        VALUES (?,?,NULL,'adjustment',?,?,?,'warehouse_opname',?,?,NOW())");

    $applied = 0;
    foreach ($items as $it) {
        if ($it['physical_stock'] === null) continue;
        $pid = (int) $it['product_id'];
        $physical = (int) $it['physical_stock'];

        $findStock->execute([$warehouseId, $pid]);
        $before = $findStock->fetchColumn();
        if ($before === false) {
            $insStock->execute([$warehouseId, $pid, $physical, (float) $it['purchase_price']]);
            $before = 0;
        } else {
            $updStock->execute([$physical, $warehouseId, $pid]);
            $before = (int) $before;
        }

        $diff = $physical - (int) $before;
        if ($diff !== 0) {
            $movement->execute([
                $pid, (int) Auth::id(), $diff, (int) $before, $physical, $id,
                'Opname gudang ' . $opname['code'] . ($diff > 0 ? ' (lebih)' : ' (kurang)'),
            ]);
        }
        $applied++;
    }

    wopname_sync_totals($db, $id);
    $db->commit();
    Auth::log('warehouse_opname_finalize', 'Finalisasi opname gudang ' . $opname['code'] . ' (' . $applied . ' item)');
    flash('success', 'Opname ' . $opname['code'] . ' difinalisasi. Stok gudang disesuaikan untuk ' . $applied . ' produk.');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    flash('error', 'Finalisasi gagal: ' . $e->getMessage());
}

legacy_redirect('admin/stock-opname-gudang/edit.php?id=' . $id);
