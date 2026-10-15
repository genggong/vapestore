<?php
/**
 * Seed data sintetis untuk benchmark query Akuntansi & Dashboard (lokal, DB vapekong_pos_test).
 * Meniru struktur produksi: products, varian, stok gudang/cabang, transaksi 3 bulan.
 * Menjalankan DDL + INSERT (tidak perlu di-hosting).
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = new PDO(
    'mysql:host=localhost;port=3306;dbname=vapekong_pos_test;charset=utf8mb4',
    'vk_test2', 'VkTest2!Pass2026',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// --- skema inti (subset dari yg dipakai laporan) ---
$db->exec("DROP TABLE IF EXISTS transaction_items, transactions, product_branch_stocks, marketplace_product_variant_stocks, marketplace_product_variants, products, branches");
$db->exec("CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180), purchase_price DECIMAL(15,2) DEFAULT 0,
    selling_price DECIMAL(15,2) DEFAULT 0, stock INT DEFAULT 0, minimum_stock INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1) ENGINE=InnoDB");
$db->exec("CREATE TABLE branches (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20), name VARCHAR(160), is_main TINYINT(1) DEFAULT 0) ENGINE=InnoDB");
$db->exec("CREATE TABLE marketplace_product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, name VARCHAR(120),
    purchase_price DECIMAL(15,2) DEFAULT 0, selling_price DECIMAL(15,2) DEFAULT 0, is_active TINYINT(1) DEFAULT 1,
    KEY idx_mpv_product (product_id)) ENGINE=InnoDB");
$db->exec("CREATE TABLE marketplace_product_variant_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY, variant_id INT NOT NULL, branch_id INT NOT NULL,
    stock INT DEFAULT 0, UNIQUE KEY uniq_mpvs_variant_branch (variant_id, branch_id),
    KEY idx_mpvs_branch (branch_id)) ENGINE=InnoDB");
$db->exec("CREATE TABLE product_branch_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY, branch_id INT NOT NULL, product_id INT NOT NULL,
    stock INT DEFAULT 0, UNIQUE KEY uniq_pbs_branch_product (branch_id, product_id)) ENGINE=InnoDB");
$db->exec("CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY, branch_id INT NOT NULL, status VARCHAR(20),
    grand_total DECIMAL(15,2) DEFAULT 0, paid_amount DECIMAL(15,2) DEFAULT 0, change_amount DECIMAL(15,2) DEFAULT 0,
    shipping_cost DECIMAL(15,2) DEFAULT 0, payment_method VARCHAR(30), transaction_date DATETIME,
    KEY idx_trx_status_date (status, transaction_date)) ENGINE=InnoDB");
$db->exec("CREATE TABLE transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY, transaction_id INT NOT NULL, product_id INT NOT NULL, variant_id INT NULL,
    qty INT DEFAULT 0, purchase_price DECIMAL(15,2) DEFAULT 0, unit_price DECIMAL(15,2) DEFAULT 0,
    KEY idx_ti_transaction (transaction_id)) ENGINE=InnoDB");

// --- data: 1 cabang pusat + 1 cabang ---
$db->exec("INSERT INTO branches (id, code, name, is_main) VALUES (1,'PST','Pusat',1),(2,'BDG','Bandung',0)");

// --- 5.000 produk: ~70% harga > 0, 30% variabel dgn varian (sebagian induk hb=0) ---
$now = microtime(true);
$prodStmt = $db->prepare("INSERT INTO products (id,name,purchase_price,selling_price,stock,minimum_stock,is_active) VALUES (?,?,?,?,?,?,1)");
$varStmt = $db->prepare("INSERT INTO marketplace_product_variants (id,product_id,name,purchase_price,selling_price,is_active) VALUES (?,?,?,?,?,1)");
$vstockStmt = $db->prepare("INSERT INTO marketplace_product_variant_stocks (variant_id,branch_id,stock) VALUES (?,?,?)");
$pbsStmt = $db->prepare("INSERT INTO product_branch_stocks (branch_id,product_id,stock) VALUES (?,?,?)");
$lastVar = 0;
for ($i = 1; $i <= 5000; $i++) {
    $isVariantProduct = ($i % 3 === 0);          // 1/3 produk punya varian
    if ($isVariantProduct) {
        $hb = ($i % 10 === 0) ? 0.0 : (float)rand(5000, 200000); // sebagian induk hb=0 (kasus Dripstate)
        $prodStmt->execute([$i, "Produk Var $i", $hb, (float)rand(10000, 300000), rand(0, 50), 3]);
        for ($v = 1; $v <= 3; $v++) {
            ++$lastVar;
            $vprice = (float)rand(5000, 200000);
            $varStmt->execute([$lastVar, $i, "Var-$i-$v", $vprice, $vprice * 1.5]);
            $vstockStmt->execute([$lastVar, 1, rand(0, 40)]);
            $vstockStmt->execute([$lastVar, 2, rand(0, 40)]);
        }
        $pbsStmt->execute([1, $i, rand(0, 80)]);
        $pbsStmt->execute([2, $i, rand(0, 80)]);
    } else {
        $prodStmt->execute([$i, "Produk $i", (float)rand(5000, 200000), (float)rand(10000, 300000), rand(0, 200), 3]);
        $pbsStmt->execute([1, $i, rand(0, 200)]);
        $pbsStmt->execute([2, $i, rand(0, 200)]);
    }
    if ($i % 1000 === 0) echo "products $i\n";
}
echo "variants: $lastVar\n";

// --- transaksi 3 bulan: ~200.000 baris (agresif, biar index kelihatan) ---
$trxStmt = $db->prepare("INSERT INTO transactions (branch_id,status,grand_total,paid_amount,change_amount,shipping_cost,payment_method,transaction_date) VALUES (?,?,?,?,?,?,?,?)");
$tiStmt = $db->prepare("INSERT INTO transaction_items (transaction_id,product_id,variant_id,qty,purchase_price,unit_price) VALUES (?,?,?,?,?,?)");
$start = strtotime('2026-07-01');
$end = strtotime('2026-10-15');
$tid = 1;
for ($ts = $start; $ts <= $end; $ts += 1800) { // tiap 30 menit
    $branch = ($tid % 3 === 0) ? 2 : 1;
    $status = ($tid % 20 === 0) ? 'refunded' : 'success';
    $shipping = ($tid % 7 === 0) ? 6000.0 : 0.0;
    $pm = ['cash','qris','transfer','cash'][$tid % 5];
    $grand = (float)rand(20000, 800000);
    $trxStmt->execute([$branch, $status, $grand, $grand, 0, $shipping, $pm, date('Y-m-d H:i:s', $ts)]);
    $n = rand(1, 4);
    for ($k = 0; $k < $n; $k++) {
        $pid = rand(1, 5000);
        $vid = (($pid % 3 === 0) && rand(0, 2) === 0) ? ($pid * 3 - 2 + rand(0, 2)) : null;
        $tiStmt->execute([$tid, $pid, $vid, rand(1, 3), (float)rand(5000, 200000), (float)rand(10000, 300000)]);
    }
    $tid++;
    if ($tid % 20000 === 0) echo "transactions $tid\n";
}
echo "total transactions: " . ($tid - 1) . "\n";
echo "DONE seed\n";