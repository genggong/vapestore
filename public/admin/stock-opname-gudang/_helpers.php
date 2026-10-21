<?php
/**
 * V17.0.43 — Helper Stok Opname Gudang (berbasis warehouse_stocks, terpisah dari stok cabang).
 */

function wopname_install(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $db->exec("CREATE TABLE IF NOT EXISTS warehouse_opnames (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) NOT NULL,
        title VARCHAR(160) NOT NULL DEFAULT '',
        warehouse_id BIGINT NOT NULL DEFAULT 0,
        user_id INT NULL,
        status ENUM('draft','finalized','canceled') NOT NULL DEFAULT 'draft',
        note TEXT NULL,
        total_items INT NOT NULL DEFAULT 0,
        total_plus INT NOT NULL DEFAULT 0,
        total_minus INT NOT NULL DEFAULT 0,
        total_plus_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_minus_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finalized_at DATETIME NULL,
        finalized_by INT NULL,
        UNIQUE KEY uniq_code (code),
        KEY idx_status (status),
        KEY idx_warehouse (warehouse_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS warehouse_opname_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        opname_id BIGINT NOT NULL,
        product_id INT NOT NULL,
        product_code VARCHAR(80) NULL,
        product_name VARCHAR(200) NOT NULL DEFAULT '',
        unit VARCHAR(30) NULL,
        system_stock INT NOT NULL DEFAULT 0,
        physical_stock INT NULL,
        difference INT NOT NULL DEFAULT 0,
        purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        difference_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        note TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_opname (opname_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function wopname_warehouse(PDO $db): array
{
    $w = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY is_main DESC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $w ?: ['id' => 0, 'name' => 'Gudang', 'code' => 'GDG'];
}

/** product_id => ['stock'=>int,'purchase_price'=>float] untuk satu gudang. */
function wopname_stock_map(PDO $db, int $warehouseId): array
{
    try {
        $st = $db->prepare("SELECT product_id, stock, purchase_price FROM warehouse_stocks WHERE warehouse_id=?");
        $st->execute([$warehouseId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['product_id']] = ['stock' => (int) $row['stock'], 'purchase_price' => (float) $row['purchase_price']];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function wopname_code(PDO $db): string
{
    $prefix = 'WOP-' . date('Ymd') . '-';
    $st = $db->prepare("SELECT code FROM warehouse_opnames WHERE code LIKE ? ORDER BY code DESC LIMIT 1");
    $st->execute([$prefix . '%']);
    $last = (string) $st->fetchColumn();
    $seq = $last !== '' ? ((int) substr($last, -3)) + 1 : 1;
    return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

function wopname_get(PDO $db, int $id): ?array
{
    $st = $db->prepare("SELECT o.*, u.name user_name, f.name finalizer_name FROM warehouse_opnames o LEFT JOIN users u ON u.id=o.user_id LEFT JOIN users f ON f.id=o.finalized_by WHERE o.id=? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function wopname_items(PDO $db, int $opnameId): array
{
    $st = $db->prepare("SELECT * FROM warehouse_opname_items WHERE opname_id=? ORDER BY product_name ASC, id ASC");
    $st->execute([$opnameId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function wopname_sync_totals(PDO $db, int $opnameId): void
{
    $st = $db->prepare("SELECT COUNT(*) total,
            COALESCE(SUM(CASE WHEN physical_stock IS NOT NULL AND difference > 0 THEN difference ELSE 0 END),0) plus_qty,
            COALESCE(SUM(CASE WHEN physical_stock IS NOT NULL AND difference < 0 THEN -difference ELSE 0 END),0) minus_qty,
            COALESCE(SUM(CASE WHEN physical_stock IS NOT NULL AND difference > 0 THEN difference_value ELSE 0 END),0) plus_val,
            COALESCE(SUM(CASE WHEN physical_stock IS NOT NULL AND difference < 0 THEN -difference_value ELSE 0 END),0) minus_val
        FROM warehouse_opname_items WHERE opname_id=?");
    $st->execute([$opnameId]);
    $t = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $db->prepare("UPDATE warehouse_opnames SET total_items=?, total_plus=?, total_minus=?, total_plus_value=?, total_minus_value=? WHERE id=?")
        ->execute([(int) ($t['total'] ?? 0), (int) ($t['plus_qty'] ?? 0), (int) ($t['minus_qty'] ?? 0), (float) ($t['plus_val'] ?? 0), (float) ($t['minus_val'] ?? 0), $opnameId]);
}

/** Isi item sesi dari stok gudang (default hanya stok > 0). */
function wopname_seed_items(PDO $db, int $opnameId, bool $includeZero = false): int
{
    $wh = wopname_warehouse($db);
    $warehouseId = (int) $wh['id'];
    $map = wopname_stock_map($db, $warehouseId);
    $products = $db->query("SELECT id,name,product_code,unit,stock,purchase_price FROM products WHERE is_active=1 ORDER BY name,id")->fetchAll();
    $ins = $db->prepare("INSERT INTO warehouse_opname_items (opname_id,product_id,product_code,product_name,unit,system_stock,physical_stock,difference,purchase_price,difference_value,note,created_at)
        VALUES (?,?,?,?,?,?,NULL,0,?,0,NULL,NOW())");
    $n = 0;
    foreach ($products as $p) {
        $stock = isset($map[(int) $p['id']]) ? (int) $map[(int) $p['id']]['stock'] : (int) $p['stock'];
        if (!$includeZero && $stock <= 0) continue;
        $price = isset($map[(int) $p['id']]) ? (float) $map[(int) $p['id']]['purchase_price'] : (float) $p['purchase_price'];
        $ins->execute([$opnameId, (int) $p['id'], (string) $p['product_code'], (string) $p['name'], (string) $p['unit'], $stock, $price]);
        $n++;
    }
    wopname_sync_totals($db, $opnameId);
    return $n;
}

function wopname_reason(?int $diff): string
{
    if ($diff === null) return 'belum';
    if ($diff === 0) return 'sesuai';
    return $diff > 0 ? 'lebih' : 'kurang';
}

function wopname_badge(?string $status): string
{
    $s = (string) $status;
    $map = ['draft' => ['Draft', 'draft'], 'finalized' => ['Final', 'final'], 'canceled' => ['Batal', 'cancel']];
    [$label, $cls] = $map[$s] ?? ['-', 'draft'];
    return '<span class="opname-badge ' . $cls . '">' . $label . '</span>';
}
