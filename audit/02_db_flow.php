<?php
/**
 * AUDIT 2 — Pemetaan alur database v17.0.22 di DB lokal.
 * Meniru halaman /admin/system-upgrade/index.php (yang memuat app/bootstrap.php + app/Modules/System).
 * Jalankan: php audit/02_db_flow.php
 */
declare(strict_types=1);

// Kit audit: jalankan dari mana saja. Semua target diambil dari env (lihat audit/README.md).
$REPO = getenv('VK_REPO') ?: dirname(__DIR__);
$DB_HOST = getenv('VK_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('VK_DB_NAME') ?: 'vapekong_audit';
$DB_USER = getenv('VK_DB_USER') ?: 'vkaudit';
$DB_PASS = getenv('VK_DB_PASS') ?: 'VkAudit#2026';
putenv("VAPEKONG_DB_HOST={$DB_HOST}");
putenv("VAPEKONG_DB_NAME={$DB_NAME}");
putenv("VAPEKONG_DB_USER={$DB_USER}");
putenv("VAPEKONG_DB_PASS={$DB_PASS}");

// Halaman asli: require app/bootstrap.php; di CLI kita muat bagian intinya saja
require_once $REPO . '/app/Helpers/helpers.php';
require_once $REPO . '/app/Core/Database.php';
require_once $REPO . '/app/Modules/System/SystemUpgrade.php';

function db(): PDO { return Database::connect(); }
function q(PDO $d, string $s, array $a = []): array { $st = $d->prepare($s); $st->execute($a); return $st->fetchAll(PDO::FETCH_ASSOC); }
function ada_tabel(PDO $d, string $t): bool { return (bool)q($d, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$t]); }
function kolom(PDO $d, string $t): array { return array_column(q($d, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$t]), 'COLUMN_NAME'); }
function idx(PDO $d, string $t): array { return array_column(q($d, "SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$t]), 'INDEX_NAME'); }
function garis(string $t): void { echo "\n" . str_repeat('=', 78) . "\n$t\n" . str_repeat('=', 78) . "\n"; }

/** Tipe sintetis: cukup realistis agar installer/bridge bisa jalan. */
function tipe_kolom(string $kolom): string {
    if ($kolom === 'id') return 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
    if (preg_match('/_id$|^role_id$|^branch_id$/', $kolom)) return 'BIGINT UNSIGNED NULL';
    if (preg_match('/_at$|_date$|_expired|^expired_at$|last_login_at$/', $kolom)) return 'DATETIME NULL';
    if (preg_match('/^(is_|has_)/', $kolom)) return 'TINYINT(1) NOT NULL DEFAULT 0';
    if (preg_match('/^(qty|stock|minimum_stock|points|sort_order|weight_gram|physical_stock|system_stock|difference|qty_requested|qty_fulfilled)$/', $kolom)) return 'DECIMAL(20,4) NULL';
    if (preg_match('/price|amount|total|cost|subtotal|discount|tax|paid|change|debt|credit_limit|opening_cash|closing_cash|balance/', $kolom)) return 'DECIMAL(20,4) NULL';
    if (preg_match('/^status$|^type$|^code$|^slug$|^unit$|^level$|^payment_method$/', $kolom)) return 'VARCHAR(60) NULL';
    if (preg_match('/description|note|address|payload|reason|profile$/', $kolom)) return 'TEXT NULL';
    return 'VARCHAR(191) NULL';
}

$db = db();
$expTables = vk_sys_expected_schema();
$expIdx = vk_sys_expected_indexes();

// ------------------------------------------------- 1. BASELINE (tiruan dump lama)
garis('1. BIKIN BASELINE: tabel inti dari vk_sys_expected_schema() (tiruan DB lama, tanpa tabel modul)');
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (array_column(q($db, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'"), 'TABLE_NAME') as $t) {
    $db->exec("DROP TABLE IF EXISTS `$t`");
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
foreach ($expTables as $t => $cols) {
    $defs = [];
    foreach ($cols as $c) $defs[] = "`$c` " . tipe_kolom($c);
    $defs[] = 'PRIMARY KEY (`id`)';
    $db->exec("CREATE TABLE `$t` (" . implode(', ', $defs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
echo "tabel inti dibuat : " . count($expTables) . " (kolom sesuai daftar harapan; TIPE sintetis, bukan dump asli)\n";
echo "catatan           : repo TIDAK menyimpan dump SQL (.sql = 0 file), jadi isi/tipe kolom asli\n";
echo "                    tidak bisa diverifikasi dari sini — yang diuji adalah ALUR & kelengkapan.\n";

// --------------------------------------------------------- 2. ALUR UPGRADE
garis('2. JALANKAN vk_sys_upgrade() SEPERTI TOMBOL "Jalankan Upgrade Otomatis"');
$log = vk_sys_upgrade($db);
$gagal = array_values(array_filter($log, fn($l) => stripos($l, 'GAGAL') !== false && stripos($l, 'MEDIA:') === false));
$skip = array_values(array_filter($log, fn($l) => stripos($l, 'SKIP') !== false));
$add = array_values(array_filter($log, fn($l) => preg_match('/^(ADD|ADD INDEX|OK: tabel)/', $l)));
echo "langkah total : " . count($log) . " | GAGAL: " . count($gagal) . " | SKIP: " . count($skip) . "\n";
echo "objek dibuat  : " . count($add) . "\n";
if ($gagal) { echo "\n-- GAGAL (harus nol) --\n"; foreach ($gagal as $g) echo "   $g\n"; }
if ($skip) { echo "\n-- SKIP --\n"; foreach ($skip as $s) echo "   $s\n"; }

// ------------------------------------------- 3. KELENGKAPAN SKEMA AKHIR
garis('3. SKEMA AKHIR vs DAFTAR HARAPAN (tabel / kolom / index)');
$tabelHilang = array_values(array_filter(array_keys($expTables), fn($t) => !ada_tabel($db, $t)));
$kolomKurang = [];
foreach ($expTables as $t => $cols) {
    if (!ada_tabel($db, $t)) continue;
    $k = array_values(array_diff($cols, kolom($db, $t)));
    if ($k) $kolomKurang[$t] = $k;
}
$idxKurang = [];
foreach ($expIdx as $t => $list) {
    if (!ada_tabel($db, $t)) { $idxKurang[$t] = ['(TABEL TIDAK ADA)']; continue; }
    $a = idx($db, $t);
    foreach ($list as $i) if (!in_array($i, $a, true)) $idxKurang[$t][] = $i;
}
echo "tabel harapan ada    : " . (count($expTables) - count($tabelHilang)) . "/" . count($expTables) . ($tabelHilang ? "  HILANG: " . implode(', ', $tabelHilang) : "") . "\n";
echo "kolom harapan kurang : " . count($kolomKurang) . " tabel" . ($kolomKurang ? " -> " . json_encode($kolomKurang) : "") . "\n";
echo "index harapan kurang : " . array_sum(array_map('count', $idxKurang)) . ($idxKurang ? " -> " . json_encode($idxKurang) : "") . "\n";
$semuaTabel = array_column(q($db, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'"), 'TABLE_NAME');
echo "total tabel di DB    : " . count($semuaTabel) . "\n";
echo "tabel dari installer : " . count(array_diff($semuaTabel, array_keys($expTables))) . " -> " . implode(', ', array_diff($semuaTabel, array_keys($expTables))) . "\n";

// ------------------------------------------------------------- 4. IDEMPOTEN
garis('4. IDEMPOTENSI: tekan tombol itu dua kali');
$log2 = vk_sys_upgrade($db);
$gagal2 = array_values(array_filter($log2, fn($l) => stripos($l, 'GAGAL') !== false && stripos($l, 'MEDIA:') === false));
$add2 = array_values(array_filter($log2, fn($l) => preg_match('/^(ADD|ADD INDEX)/', $l)));
echo "GAGAL        : " . count($gagal2) . ($gagal2 ? " -> " . implode(' | ', $gagal2) : "") . "\n";
echo "perubahan baru: " . count($add2) . " (harus 0)" . ($add2 ? " -> " . implode(', ', $add2) : "") . "\n";

// --------------------------------------------- 5. SIMULASI DB TERTINGGAL
garis('5. SIMULASI DB TERTINGGAL (hosting lama): buang objek baru lalu upgrade lagi');
$target = [
    'transactions.idx_transactions_status_date' => true,
    'products.weight_gram' => true,
    'products.brand_id' => true,
    'purchase_items.variant_id' => true,
    'purchase_items.variant_name' => true,
    'transaction_items.variant_id' => true,
    'online_order_items.variant_sku' => true,
    'stock_opname_items.variant_id' => true,
];
$db->exec('ALTER TABLE transactions DROP INDEX idx_transactions_status_date');
$db->exec('ALTER TABLE products DROP COLUMN weight_gram, DROP COLUMN brand_id');
$db->exec('ALTER TABLE purchase_items DROP COLUMN variant_id, DROP COLUMN variant_name');
$db->exec('ALTER TABLE transaction_items DROP COLUMN variant_id');
$db->exec('ALTER TABLE online_order_items DROP COLUMN variant_sku');
$db->exec('ALTER TABLE stock_opname_items DROP COLUMN variant_id');
echo "dibuang: " . implode(', ', array_keys($target)) . "\n";
$log3 = vk_sys_upgrade($db);
foreach ($log3 as $l) if (preg_match('/^(ADD|ADD INDEX)/', $l)) echo "   dipulihkan -> $l\n";
$pulih = 0;
foreach ($target as $obj => $_) {
    [$t, $c] = explode('.', $obj);
    if (preg_match('/^idx_/', $c)) { if (in_array($c, idx($db, $t), true)) $pulih++; }
    else { if (in_array($c, kolom($db, $t), true)) $pulih++; }
}
$gagal3 = array_values(array_filter($log3, fn($l) => stripos($l, 'GAGAL') !== false && stripos($l, 'MEDIA:') === false));
echo "objek pulih: $pulih/" . count($target) . " | GAGAL: " . count($gagal3) . ($gagal3 ? " -> " . implode(' | ', $gagal3) : "") . "\n";

// ------------------------------- 6. INDEX PENENTU PERFORMA v17.0.16
garis('6. INDEX PENENTU PERFORMA (inti perbaikan v17.0.16)');
$c = q($db, "SELECT COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND INDEX_NAME='idx_transactions_status_date' ORDER BY SEQ_IN_INDEX");
echo "idx_transactions_status_date kolom: " . implode(',', array_column($c, 'COLUMN_NAME')) . " (harus status,transaction_date)\n";
$cols = [];
foreach (q($db, "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='transactions' AND COLUMN_NAME='transaction_date'))") as $r) {
    $cols[] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'] . ' (' . $r['DATA_TYPE'] . ')';
}
echo "tipe kolom kunci     : " . implode(', ', $cols) . " (harus datetime/date/timestamp agar range index dipakai)\n";

// --------------------------------------------------------------- KESIMPULAN
echo "\n" . str_repeat('-', 78) . "\n";
$ok = count($gagal) === 0 && count($gagal2) === 0 && count($gagal3) === 0 && !$tabelHilang && !$kolomKurang && !$idxKurang && count($add2) === 0 && $pulih === count($target);
echo $ok ? "KESIMPULAN: ALUR DB v17.0.22 KONSISTEN & IDEMPOTEN di baseline sintetis.\n"
         : "KESIMPULAN: ADA TEMUAN DI ATAS — selesaikan dulu sebelum deploy.\n";
exit($ok ? 0 : 1);