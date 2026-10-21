"""Kontrak + runtime test v17.0.35 — Ambil di Toko maksimal hari ini.

Jalankan: python3 -m unittest tests.test_patch_v17_0_35 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}
MOD = {'legacy': 'legacy/app', 'public': 'app', 'pos': 'pos/app'}
ENV = dict(os.environ, VAPEKONG_DB_HOST='127.0.0.1', VAPEKONG_DB_PORT='3306',
           VAPEKONG_DB_NAME='vapekong_prod', VAPEKONG_DB_USER='vk_test2', VAPEKONG_DB_PASS='VkTest2!Pass2026')


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


def php(code):
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', code], capture_output=True, text=True, env=ENV,
                       cwd=str(ROOT))
    return r.returncode, (r.stdout or '') + (r.stderr or '')


def sql(q):
    r = subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', 'vapekong_prod', '-N', '-B', '-e', q],
                       capture_output=True, text=True)
    return r.stdout.strip()


class TestKontrak(unittest.TestCase):
    def test_kolom_dan_helper(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/StorePickup.php')
                self.assertIn("'pickup_deadline_at' => \"DATETIME NULL\"", s)
                self.assertIn('function marketplace_store_pickup_deadline(', s)
                self.assertIn("'marketplace_store_pickup_max_days' => '0'", s)

    def test_create_order_mengisi_deadline(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                self.assertIn('pickup_deadline_at=COALESCE(?,pickup_deadline_at)',
                              read(f'{m}/Modules/Marketplace/Marketplace.php'))

    def test_tampilan(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertIn('Batas Ambil', read(f'{w}/store/order.php'))
                self.assertIn('Batas ambil', read(f'{w}/admin/marketplace-orders/detail.php'))
                self.assertIn('maksimal hari ini', read(f'{w}/store/checkout.php'))
                self.assertIn('marketplace_store_pickup_max_days', read(f'{w}/admin/marketplace-settings/index.php'))


class TestRuntime(unittest.TestCase):
    def test_deadline_hari_ini(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
marketplace_store_pickup_install($db);
$out = [];
$out[] = 'close:' . marketplace_store_pickup_close_time();
$out[] = 'maxdays:' . marketplace_store_pickup_max_days();
$dl = marketplace_store_pickup_deadline();
$out[] = 'deadline_hari:' . (date('Y-m-d', strtotime($dl)) === date('Y-m-d') ? 'hariini' : 'bukan');
$out[] = 'deadline_jam:' . date('H:i', strtotime($dl));
$out[] = 'label:' . marketplace_store_pickup_deadline_label(['pickup_deadline_at' => $dl, 'order_status' => 'confirmed']);
$out[] = 'lewat:' . (marketplace_store_pickup_deadline_passed(['pickup_deadline_at' => date('Y-m-d H:i:s', time() - 3600), 'order_status' => 'confirmed']) ? 'ya' : 'tidak');
$out[] = 'belum:' . (marketplace_store_pickup_deadline_passed(['pickup_deadline_at' => date('Y-m-d H:i:s', time() + 3600), 'order_status' => 'confirmed']) ? 'ya' : 'tidak');
$out[] = 'selesai:' . (marketplace_store_pickup_deadline_passed(['pickup_deadline_at' => date('Y-m-d H:i:s', time() - 3600), 'order_status' => 'completed']) ? 'ya' : 'tidak');

// order pickup nyata
$b = $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$r = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]],
    'courier_code' => 'pickup', 'pickup_branch_id' => (int) $b, 'shipping_cost' => 0,
    'payment_method' => 'cod', 'recipient_name' => 'ZZ Batas Ambil', 'recipient_phone' => '089900001234',
    'address' => 'Ambil di toko', 'checkout_key' => 'zz35' . bin2hex(random_bytes(6)),
]);
$o = $db->prepare("SELECT id,pickup_deadline_at FROM online_orders WHERE order_number=?");
$o->execute([$r['order_number']]);
$row = $o->fetch(PDO::FETCH_ASSOC);
$out[] = 'order_deadline:' . ($row['pickup_deadline_at'] ?: 'kosong');
$out[] = 'order_bedahari:' . (date('Y-m-d', strtotime((string) $row['pickup_deadline_at'])) === date('Y-m-d') ? 'ya' : 'tidak');

try { marketplace_release_stock($db, (int) $row['id'], 'cleanup test'); } catch (Throwable $e) {}
foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events'] as $t) {
    $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $row['id']]);
}
$db->prepare("DELETE FROM online_order_items WHERE order_id=?")->execute([(int) $row['id']]);
$db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $row['id']]);
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:400])
        self.assertIn('maxdays:0', out)
        self.assertIn('deadline_hari:hariini', out)
        self.assertIn('label:Hari ini, maks', out)
        self.assertIn('lewat:ya|belum:tidak|selesai:tidak', out)
        self.assertIn('order_bedahari:ya', out)

    def test_max_days_bisa_diubah(self):
        sql("INSERT INTO settings(setting_key,setting_value) VALUES('marketplace_store_pickup_max_days','1') "
            "ON DUPLICATE KEY UPDATE setting_value='1'")
        try:
            rc, out = php(r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
marketplace_store_pickup_install(Database::connect());
$dl = marketplace_store_pickup_deadline();
echo (date('Y-m-d', strtotime($dl)) === date('Y-m-d', strtotime('+1 day')) ? 'besok' : 'bukan');
''')
            self.assertEqual(rc, 0, out[:300])
            self.assertEqual(out.strip(), 'besok')
        finally:
            sql("UPDATE settings SET setting_value='0' WHERE setting_key='marketplace_store_pickup_max_days'")


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 35))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
