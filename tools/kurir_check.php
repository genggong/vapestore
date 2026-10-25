<?php
/**
 * V17.0.46 — diagnosa/repair tarif Kurir Toko untuk kelurahan yang checkout-nya ditolak.
 *   php tools/kurir_check.php "MEJASEM BARAT"            = cek tarif (semua baris, aktif/non-aktif)
 *   php tools/kurir_check.php "MEJASEM BARAT" --apply     = set is_active=1 semua baris kelurahan itu
 */
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/../app/bootstrap.php';

$db = Database::connect();
$wanted = strtoupper(trim((string) ($argv[1] ?? '')));
$wanted = (string) preg_replace('/[^a-zA-Z0-9 ]/', '', $wanted);
$wanted = (string) trim((string) preg_replace('/\s+/', ' ', $wanted));
if ($wanted === '') { fwrite(STDERR, "Gunakan: php tools/kurir_check.php \"Nama Kelurahan\" [--apply]\n"); exit(2); }
$apply = in_array('--apply', $argv, true);

echo "Mencari tarif untuk kelurahan: $wanted\n";
echo "DJAM: " . $db->query('SELECT NOW()')->fetchColumn() . "\n";

$rows = [];
try {
    $rows = $db->query("SELECT id,branch_id,province,city,district,subdistrict,fee,eta,is_active FROM marketplace_store_courier_rates ORDER BY is_active ASC, branch_id ASC, id ASC")->fetchAll() ?: [];
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal baca tarif: ' . $e->getMessage() . "\n");
    exit(1);
}

$norm = static function ($v): string {
    $v = strtoupper(trim((string) $v));
    $v = preg_replace('/[^a-zA-Z0-9 ]/', ' ', $v) ?: '';
    return trim((string) preg_replace('/\s+/', ' ', $v));
};
$needle = $norm($wanted);

$matches = [];
foreach ($rows as $r) {
    if ($norm(trim((string) $r['subdistrict'])) === $needle) {
        $matches[] = $r;
    }
}

if (!$matches) {
    echo "TIDAK ADA baris tarif untuk '$wanted'.\n";
    echo "Langkah: tambah via Admin > Marketplace > Courier Rates (tarif per kelurahan), lalu coba checkout lagi.\n";
    exit(3);
}

foreach ($matches as $r) {
    echo sprintf("  baris #%d | cabang=%d | %s > %s > %s > %s | ongkir=%s | aktif=%s\n",
        (int) $r['id'], (int) $r['branch_id'], (string) $r['province'], (string) $r['city'],
        (string) $r['district'], (string) $r['subdistrict'], (string) $r['fee'], $r['is_active'] ? 'ya' : 'TIDAK');
}

$inactive = array_values(array_filter($matches, static fn($r) => (int) $r['is_active'] === 0));
if (!$inactive) {
    echo "Semua baris untuk '$wanted' aktif — bukan masalah status. Periksa apakah VERSION sudah 17.0.46 (pasang patch validasi).\n";
    exit(0);
}

echo "\n[" . count($inactive) . " baris non-aktif utk '$wanted']. Repair: jalankan dengan --apply untuk reactivasi.\n";
if ($apply) {
    $up = $db->prepare("UPDATE marketplace_store_courier_rates SET is_active=1, updated_at=NOW() WHERE id=?");
    $n = 0;
    foreach ($inactive as $r) { $up->execute([(int) $r['id']]); $n++; }
    echo "Done: $n baris diaktifkan.\nLalu coba checkout lagi. (Purge cache jika perlu)\n";
}
exit(0);
