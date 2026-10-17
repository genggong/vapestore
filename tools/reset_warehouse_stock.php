<?php
/**
 * V17.0.23 — Reset stok GUDANG menjadi 0 (mis. setelah pembelian uji coba).
 *
 * Jalankan dari docroot:
 *   php tools/reset_warehouse_stock.php                 -> DRY RUN (hanya menampilkan yang akan diubah)
 *   php tools/reset_warehouse_stock.php --apply         -> benar-benar mengosongkan stok gudang
 *   php tools/reset_warehouse_stock.php --apply --note="reset setelah tes pembelian"
 *   php tools/reset_warehouse_stock.php --apply --include-branch   -> ikut mengosongkan stok cabang + stok master
 *
 * Pengaman:
 *   - tanpa --apply tidak ada satu baris pun yang berubah;
 *   - sebelum menulis, nilai lama disalin ke tabel backup `warehouse_stocks_backup_<ts>` /
 *     `marketplace_product_variant_stocks_backup_<ts>` (CREATE TABLE ... AS SELECT);
 *   - setiap perubahan dicatat sebagai pergerakan (`warehouse_stock_movements` / `stock_movements`);
 *   - harga modal (purchase_price) TIDAK diubah, hanya stok.
 *
 * Catatan: laporan nilai modal memakai stok CABANG, jadi nilai di Dashboard/Akuntansi tidak
 * berubah oleh tool ini kecuali dijalankan dengan --include-branch.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$argv = $argv ?? [];
$apply = in_array('--apply', $argv, true);
$includeBranch = in_array('--include-branch', $argv, true);
$note = 'Reset stok gudang (pembelian uji coba)';
foreach ($argv as $a) {
    if (str_starts_with($a, '--note=')) $note = trim(substr($a, 7));
}

$bootstrap = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($bootstrap as $b) { if (is_file($b)) { require_once $b; $loaded = true; break; } }
if (!$loaded || !class_exists('Database')) { exit("bootstrap aplikasi tidak ditemukan\n"); }
ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = Database::connect();
echo 'DB OK: ' . (string)$db->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo $apply ? "MODE: APPLY (data akan diubah)\n" : "MODE: DRY RUN (tidak ada yang diubah)\n";
$ts = date('Ymd_His');

$exists = static function (PDO $db, string $table): bool {
    try {
        $s = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
};

$stat = static function (PDO $db, string $table): array {
    try {
        $row = $db->query("SELECT COUNT(*) AS baris, COALESCE(SUM(stock),0) AS stok, COALESCE(SUM(CASE WHEN stock>0 THEN 1 ELSE 0 END),0) AS berisi FROM `{$table}`")->fetch();
        return ['baris' => (int)($row['baris'] ?? 0), 'stok' => (int)($row['stok'] ?? 0), 'berisi' => (int)($row['berisi'] ?? 0)];
    } catch (Throwable $e) { return ['baris' => 0, 'stok' => 0, 'berisi' => 0]; }
};

echo "\n--- SEBELUM ---\n";
$targets = ['warehouse_stocks', 'warehouse_variant_stocks'];
if ($includeBranch) $targets = array_merge($targets, ['product_branch_stocks', 'marketplace_product_variant_stocks']);
foreach ($targets as $t) {
    if (!$exists($db, $t)) { echo str_pad($t, 36) . ": (tabel tidak ada)\n"; continue; }
    $s = $stat($db, $t);
    printf("%-36s: %d baris, %d baris berisi, total stok %d\n", $t, $s['baris'], $s['berisi'], $s['stok']);
}
if (!$includeBranch) {
    echo "(stok cabang & stok master tidak disentuh — laporan nilai modal memakai stok cabang)\n";
}

if (!$apply) {
    echo "\nDRY RUN selesai. Untuk menjalankan sungguhan:\n";
    echo "  php tools/reset_warehouse_stock.php --apply" . ($includeBranch ? " --include-branch" : "") . "\n";
    exit(0);
}

// ---------- BACKUP ----------
echo "\n--- BACKUP ---\n";
$backups = [];
foreach ($targets as $t) {
    if (!$exists($db, $t)) continue;
    $name = $t . '_backup_' . $ts;
    try {
        $db->exec("CREATE TABLE `{$name}` AS SELECT * FROM `{$t}`");
        $backups[$t] = $name;
        echo "  {$t} -> {$name}\n";
    } catch (Throwable $e) {
        echo "  GAGAL backup {$t}: " . $e->getMessage() . "\n";
        exit(1);
    }
}
if ($includeBranch && $exists($db, 'products')) {
    try {
        $db->exec("CREATE TABLE `products_stock_backup_{$ts}` AS SELECT id, stock FROM products");
        echo "  products(stock) -> products_stock_backup_{$ts}\n";
    } catch (Throwable $e) {
        echo "  GAGAL backup products: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ---------- TERAPKAN ----------
echo "\n--- MENERAPKAN ---\n";
$db->beginTransaction();
try {
    $userId = class_exists('Auth') ? Auth::id() : null;

    // 1. pergerakan gudang dulu (sebelum stok diubah)
    if ($exists($db, 'warehouse_stock_movements') && $exists($db, 'warehouse_stocks')) {
        $rows = $db->query("SELECT warehouse_id, product_id, stock, purchase_price FROM warehouse_stocks WHERE stock <> 0")->fetchAll() ?: [];
        $ins = $db->prepare("INSERT INTO warehouse_stock_movements (warehouse_id,product_id,user_id,type,qty,stock_before,stock_after,unit_cost,reference_type,reference_id,note,created_at)
                             VALUES (?,?,?,'reset_stock',?,?,0,?,'reset_stock',NULL,?,NOW())");
        foreach ($rows as $r) {
            $ins->execute([(int)$r['warehouse_id'], (int)$r['product_id'], $userId, (int)$r['stock'], (int)$r['stock'], (float)$r['purchase_price'], $note]);
        }
        echo '  pergerakan gudang dicatat: ' . count($rows) . "\n";
    }
    if ($exists($db, 'warehouse_variant_stocks') && $exists($db, 'warehouse_stock_movements')) {
        $rows = $db->query("SELECT warehouse_id, product_id, stock, purchase_price FROM warehouse_variant_stocks WHERE stock <> 0")->fetchAll() ?: [];
        $ins = $db->prepare("INSERT INTO warehouse_stock_movements (warehouse_id,product_id,user_id,type,qty,stock_before,stock_after,unit_cost,reference_type,reference_id,note,created_at)
                             VALUES (?,?,?,'reset_stock',?,?,0,?,'reset_stock',NULL,?,NOW())");
        foreach ($rows as $r) {
            $ins->execute([(int)$r['warehouse_id'], (int)$r['product_id'], $userId, (int)$r['stock'], (int)$r['stock'], (float)$r['purchase_price'], $note . ' (varian)']);
        }
        echo '  pergerakan varian gudang dicatat: ' . count($rows) . "\n";
    }

    // 2. stok cabang (opsional)
    if ($includeBranch) {
        if ($exists($db, 'stock_movements') && $exists($db, 'product_branch_stocks')) {
            $rows = $db->query("SELECT branch_id, product_id, stock FROM product_branch_stocks WHERE stock <> 0")->fetchAll() ?: [];
            $ins = $db->prepare("INSERT INTO stock_movements (product_id,user_id,branch_id,type,qty,stock_before,stock_after,reference_type,reference_id,note,created_at)
                                 VALUES (?,?,?,'reset_stock',?,?,0,'reset_stock',NULL,?,NOW())");
            foreach ($rows as $r) {
                $ins->execute([(int)$r['product_id'], $userId, (int)$r['branch_id'], (int)$r['stock'], (int)$r['stock'], $note]);
            }
            echo '  pergerakan cabang dicatat: ' . count($rows) . "\n";
        }
    }

    // 3. nol-kan stok
    $affected = [];
    foreach ($targets as $t) {
        if (!$exists($db, $t)) continue;
        $db->exec("UPDATE `{$t}` SET stock=0, updated_at=NOW() WHERE stock <> 0");
        $affected[$t] = 1;
        echo "  {$t}: stok dinolkan\n";
    }
    if ($includeBranch && $exists($db, 'products')) {
        $db->exec("UPDATE products SET stock=0, updated_at=NOW() WHERE stock <> 0");
        echo "  products.stock: dinolkan\n";
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo 'GAGAL: ' . $e->getMessage() . "\n";
    echo "Tidak ada perubahan yang tersimpan (rollback). Backup tetap ada.\n";
    exit(1);
}

echo "\n--- SESUDAH ---\n";
foreach ($targets as $t) {
    if (!$exists($db, $t)) continue;
    $s = $stat($db, $t);
    printf("%-36s: %d baris, %d baris berisi, total stok %d\n", $t, $s['baris'], $s['berisi'], $s['stok']);
}
if ($includeBranch && $exists($db, 'products')) {
    $tot = (int)$db->query('SELECT COALESCE(SUM(stock),0) FROM products')->fetchColumn();
    echo str_pad('products.stock', 36) . ": total stok {$tot}\n";
}

echo "\nBACKUP tersimpan: " . implode(', ', $backups) . "\n";
echo "Rollback contoh (gudang saja):\n";
echo "  UPDATE warehouse_stocks w JOIN {$backups['warehouse_stocks']} b ON b.id=w.id SET w.stock=b.stock;\n";
echo "SELESAI — hapus file ini bila tidak dipakai lagi: rm -f tools/reset_warehouse_stock.php\n";