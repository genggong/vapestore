<?php
/**
 * V17.0.33 — Nolkan stok satu cabang (mis. Vapekong Slawi) tanpa menghapus data lain.
 *
 * Yang di-nol-kan:
 *   - product_branch_stocks.stock            (stok jual cabang)
 *   - marketplace_product_variant_stocks.stock (stok varian cabang)
 *
 * Yang TIDAK diubah: harga modal (purchase_price), minimum_stock, stok gudang pusat
 * (warehouse_stocks), riwayat transaksi, dan pesanan lama.
 *
 * Default DRY-RUN. Menulis hanya dengan --apply. Sebelum menulis, baris yang terdampak
 * disalin ke tabel backup <tabel>_bak_<timestamp>, dan setiap perubahan dicatat di
 * stock_movements (type=adjustment, reference_type=reset_branch_stock).
 *
 * Contoh:
 *   php tools/reset_branch_stock.php --branch-name=Slawi            # lihat dulu
 *   php tools/reset_branch_stock.php --branch-name=Slawi --apply
 *   php tools/reset_branch_stock.php --branch=2 --apply --note="Tutup cabang Slawi"
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$force = in_array('--force', $args, true);
$branchId = 0;
$branchName = '';
$note = 'Nolkan stok cabang';
foreach ($args as $a) {
    if (stripos($a, '--branch=') === 0) $branchId = (int) substr($a, 9);
    elseif (stripos($a, '--branch-name=') === 0) $branchName = trim(substr($a, 14));
    elseif (stripos($a, '--note=') === 0) $note = trim(substr($a, 7));
}

$candidates = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../legacy/app/bootstrap.php',
    getcwd() . '/app/bootstrap.php',
    getcwd() . '/legacy/app/bootstrap.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (is_file($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded) {
    fwrite(STDERR, "bootstrap.php tidak ditemukan. Jalankan dari docroot: cd ~/public_html && php tools/reset_branch_stock.php --branch-name=Slawi\n");
    exit(1);
}
$db = Database::connect();

function rb_table_exists(PDO $db, string $t): bool
{
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $s->execute([$t]);
    return (int) $s->fetchColumn() > 0;
}

echo 'DB OK: ' . $db->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo 'Mode: ' . ($apply ? 'APPLY (menulis perubahan)' : 'DRY-RUN (tidak menulis apa pun)') . "\n";

// --- Pilih cabang ---
if ($branchId > 0) {
    $st = $db->prepare("SELECT id, name, is_main, is_active FROM branches WHERE id=?");
    $st->execute([$branchId]);
} elseif ($branchName !== '') {
    $st = $db->prepare("SELECT id, name, is_main, is_active FROM branches WHERE name LIKE ? ORDER BY is_main ASC, id ASC LIMIT 1");
    $st->execute(['%' . $branchName . '%']);
} else {
    echo "Daftar cabang:\n";
    foreach ($db->query("SELECT id, name, is_main, is_active FROM branches ORDER BY id")->fetchAll() as $b) {
        echo "  #{$b['id']} {$b['name']}" . ($b['is_main'] ? ' (utama)' : '') . ($b['is_active'] ? '' : ' [nonaktif]') . "\n";
    }
    echo "\nPakai: --branch=ID atau --branch-name=Nama\n";
    exit(1);
}
$branch = $st->fetch();
if (!$branch) {
    fwrite(STDERR, "Cabang tidak ditemukan.\n");
    exit(1);
}
echo "Cabang: #{$branch['id']} {$branch['name']}" . ((int) $branch['is_main'] === 1 ? ' (UTAMA)' : '') . "\n";

if ((int) $branch['is_main'] === 1 && !$force) {
    fwrite(STDERR, "Cabang ini cabang UTAMA — stoknya dipakai penjualan & katalog. Tambahkan --force kalau benar-benar yakin.\n");
    exit(1);
}

$bid = (int) $branch['id'];
$hasVariant = rb_table_exists($db, 'marketplace_product_variant_stocks');

// --- Hitung yang akan di-nol-kan ---
$q = $db->prepare("SELECT COUNT(*) rows_, COALESCE(SUM(stock),0) total FROM product_branch_stocks WHERE branch_id=? AND stock<>0");
$q->execute([$bid]);
$p = $q->fetch();
$vRows = 0; $vTotal = 0;
if ($hasVariant) {
    $q = $db->prepare("SELECT COUNT(*) rows_, COALESCE(SUM(stock),0) total FROM marketplace_product_variant_stocks WHERE branch_id=? AND stock<>0");
    $q->execute([$bid]);
    $v = $q->fetch();
    $vRows = (int) $v['rows_']; $vTotal = (int) $v['total'];
}

echo "Produk dengan stok bukan nol : {$p['rows_']} baris (total {$p['total']} unit)\n";
if ($hasVariant) echo "Varian dengan stok bukan nol : {$vRows} baris (total {$vTotal} unit)\n";

if ((int) $p['rows_'] === 0 && $vRows === 0) {
    echo "Stok cabang ini sudah nol semua. Tidak ada yang diubah.\n";
    exit(0);
}

if (!$apply) {
    echo "\nContoh 10 baris yang akan di-nol-kan:\n";
    $s = $db->prepare("SELECT pbs.product_id, p.name, pbs.stock FROM product_branch_stocks pbs
                       LEFT JOIN products p ON p.id=pbs.product_id
                       WHERE pbs.branch_id=? AND pbs.stock<>0 ORDER BY pbs.stock DESC LIMIT 10");
    $s->execute([$bid]);
    foreach ($s->fetchAll() as $r) {
        printf("  #%-6s %-45.45s stok sekarang: %d\n", $r['product_id'], (string) $r['name'], (int) $r['stock']);
    }
    echo "\nJalankan ulang dengan --apply untuk menulis perubahan.\n";
    exit(0);
}

// --- APPLY: backup lalu nol-kan ---
$ts = date('Ymd_His');
$bak1 = "product_branch_stocks_bak_{$ts}";
$db->exec("CREATE TABLE `{$bak1}` AS SELECT * FROM product_branch_stocks WHERE branch_id=" . $bid);
echo "Backup: {$bak1} (" . $db->query("SELECT COUNT(*) FROM `{$bak1}`")->fetchColumn() . " baris)\n";
$bak2 = '';
if ($hasVariant) {
    $bak2 = "marketplace_product_variant_stocks_bak_{$ts}";
    $db->exec("CREATE TABLE `{$bak2}` AS SELECT * FROM marketplace_product_variant_stocks WHERE branch_id=" . $bid);
    echo "Backup: {$bak2} (" . $db->query("SELECT COUNT(*) FROM `{$bak2}`")->fetchColumn() . " baris)\n";
}

try {
    $db->beginTransaction();

    $rows = $db->prepare("SELECT product_id, stock FROM product_branch_stocks WHERE branch_id=? AND stock<>0");
    $rows->execute([$bid]);
    $mov = $db->prepare("INSERT INTO stock_movements (product_id, user_id, branch_id, type, qty, stock_before, stock_after, reference_type, note, created_at)
                         VALUES (?, NULL, ?, 'adjustment', ?, ?, 0, 'reset_branch_stock', ?, NOW())");
    $n = 0;
    foreach ($rows->fetchAll() as $r) {
        $before = (int) $r['stock'];
        $mov->execute([(int) $r['product_id'], $bid, -$before, $before, $note . ' (' . $branch['name'] . ')']);
        $n++;
    }
    $upd = $db->prepare("UPDATE product_branch_stocks SET stock=0, updated_at=NOW() WHERE branch_id=? AND stock<>0");
    $upd->execute([$bid]);
    echo "Produk di-nol-kan: {$upd->rowCount()} baris (movement tercatat: {$n})\n";

    if ($hasVariant) {
        $vrows = $db->prepare("SELECT s.variant_id, v.product_id, s.stock
                               FROM marketplace_product_variant_stocks s
                               LEFT JOIN marketplace_product_variants v ON v.id = s.variant_id
                               WHERE s.branch_id=? AND s.stock<>0");
        $vrows->execute([$bid]);
        $vmov = $db->prepare("INSERT INTO stock_movements (product_id, user_id, branch_id, type, qty, stock_before, stock_after, reference_type, note, created_at)
                              VALUES (?, NULL, ?, 'adjustment', ?, ?, 0, 'reset_branch_stock', ?, NOW())");
        $cariProduk = $db->prepare("SELECT 1 FROM products WHERE id=? LIMIT 1");
        $vn = 0; $vskip = 0;
        foreach ($vrows->fetchAll() as $r) {
            $before = (int) $r['stock'];
            $pid = (int) ($r['product_id'] ?? 0);
            $cariProduk->execute([$pid]);
            $adaProduk = $pid > 0 && (bool) $cariProduk->fetchColumn();
            $cariProduk->closeCursor();
            if ($adaProduk) {
                $vmov->execute([$pid, $bid, -$before, $before, $note . ' (varian #' . $r['variant_id'] . ', ' . $branch['name'] . ')']);
                $vn++;
            } else {
                // varian yatim (produk induknya sudah dihapus) — stok tetap di-nol-kan, movement dilewati
                $vskip++;
            }
        }
        $vupd = $db->prepare("UPDATE marketplace_product_variant_stocks SET stock=0, updated_at=NOW() WHERE branch_id=? AND stock<>0");
        $vupd->execute([$bid]);
        echo "Varian di-nol-kan: {$vupd->rowCount()} baris (movement tercatat: {$vn}" . ($vskip > 0 ? ", varian tanpa produk induk: {$vskip}" : '') . ")\n";
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'GAGAL, semua dibatalkan: ' . $e->getMessage() . "\n");
    exit(1);
}

// --- Verifikasi ---
$q = $db->prepare("SELECT COUNT(*) rows_, COALESCE(SUM(stock),0) total FROM product_branch_stocks WHERE branch_id=? AND stock<>0");
$q->execute([$bid]);
$after = $q->fetch();
echo "Verifikasi: sisa baris stok bukan nol = {$after['rows_']}, total unit = {$after['total']}\n";
if ($hasVariant) {
    $q = $db->prepare("SELECT COUNT(*) rows_, COALESCE(SUM(stock),0) total FROM marketplace_product_variant_stocks WHERE branch_id=? AND stock<>0");
    $q->execute([$bid]);
    $av = $q->fetch();
    echo "Verifikasi varian: sisa baris = {$av['rows_']}, total unit = {$av['total']}\n";
}
echo "Stok gudang pusat tidak diubah (tetap ada) — hanya stok cabang {$branch['name']} yang di-nol-kan.\n";
echo "SELESAI\n";
