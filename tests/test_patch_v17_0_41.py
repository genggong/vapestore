"""Kontrak + runtime test v17.0.41 — batas ambil hanya untuk COD; QRIS/VA lunas tanpa expired.

Jalankan: python3 -m unittest tests.test_patch_v17_0_41 -v
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
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', code], capture_output=True, text=True,
                       env=ENV, cwd=str(ROOT))
    return r.returncode, (r.stdout or '') + (r.stderr or '')


class TestKontrak(unittest.TestCase):
    def test_deadline_hanya_cod(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('($isCod && function_exists(\'marketplace_store_pickup_deadline\'))', s)

    def test_label_lunas_kosong(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/StorePickup.php')
                self.assertIn("if ((string) ($order['payment_status'] ?? '') === 'paid') return '';", s)

    def test_tampilan(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertIn('$pickupDeadlineLabel', read(f'{w}/store/order.php'))
                self.assertIn('$pickupDeadlineLabel', read(f'{w}/admin/marketplace-orders/detail.php'))


class TestRuntime(unittest.TestCase):
    def test_cod_dapat_batas_qris_tidak(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$mk = function ($pm, $key) use ($db, $b) {
    return marketplace_create_order($db, [
        'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
        'shipping_cost' => 0, 'payment_method' => $pm, 'recipient_name' => 'ZZ Batas 41',
        'recipient_phone' => '089900004141', 'address' => 'x', 'checkout_key' => $key . bin2hex(random_bytes(4)),
    ]);
};
$clean = function ($id) use ($db) {
    try { marketplace_release_stock($db, (int) $id, 'cleanup'); } catch (Throwable $e) {}
    foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events','online_order_items'] as $t) {
        $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $id]);
    }
    $db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $id]);
};
$out = [];

// COD -> punya batas ambil
$cod = $mk('cod', 'zz41a');
$oc = marketplace_get_order($db, $cod['order_number']);
$out[] = 'cod_deadline:' . (!empty($oc['pickup_deadline_at']) ? 'ada' : 'kosong');
$out[] = 'cod_label:' . (marketplace_store_pickup_deadline_label($oc, $db) !== '' ? 'tampil' : 'kosong');

// QRIS -> tanpa batas ambil
$q = $mk('qris', 'zz41b');
$oq = marketplace_get_order($db, $q['order_number']);
$out[] = 'qris_deadline:' . (empty($oq['pickup_deadline_at']) ? 'kosong' : 'ada');
$out[] = 'qris_label:' . (marketplace_store_pickup_deadline_label($oq, $db) === '' ? 'kosong' : 'tampil');

// QRIS lunas tapi masih punya deadline lama -> label kosong & tidak di-expire
$db->prepare("UPDATE online_orders SET payment_status='paid', order_status='processing', pickup_deadline_at=DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id=?")->execute([(int) $oq['id']]);
$oq2 = marketplace_get_order($db, $q['order_number']);
$out[] = 'lunas_label:' . (marketplace_store_pickup_deadline_label($oq2, $db) === '' ? 'kosong' : 'tampil');
$after = marketplace_pickup_expire_if_needed($db, $oq2);
$out[] = 'lunas_status:' . $after['order_status'];

$clean($oc['id']);
$clean($oq['id']);
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:400])
        self.assertIn('cod_deadline:ada', out)
        self.assertIn('cod_label:tampil', out)
        self.assertIn('qris_deadline:kosong', out)
        self.assertIn('qris_label:kosong', out)
        self.assertIn('lunas_label:kosong', out)
        self.assertIn('lunas_status:processing', out)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 41))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
