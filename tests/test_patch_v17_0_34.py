"""Kontrak + runtime test v17.0.34 — COD (Bayar di Tempat) + WA kurir toko.

Jalankan: python3 -m unittest tests.test_patch_v17_0_34 -v
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


class TestKontrakCod(unittest.TestCase):
    def test_helper_cod(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_cod_allowed', s)
                self.assertIn("in_array(strtolower(trim($courierCode)), ['pickup', 'store'], true)", s)
                self.assertIn("'unpaid'=>'Bayar di Tempat'", s)

    def test_checkout_punya_kartu_cod(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/checkout.php')
                self.assertIn('payment-method-cod', s)
                self.assertIn('data-cod-enabled', s)
                self.assertIn('Bayar di Tempat (COD)', s)

    def test_js_toggle_cod(self):
        for j in ('assets/js', 'legacy/public/assets/js', 'public/assets/js', 'pos/public/assets/js'):
            with self.subTest(dir=j):
                s = read(f'{j}/marketplace-checkout.js')
                self.assertIn('function syncCodOption()', s)
                self.assertIn("courier === 'store'", s)

    def test_halaman_order_dan_admin(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertIn('Bayar di Tempat (COD)', read(f'{w}/store/order.php'))
                self.assertIn('cod_paid', read(f'{w}/admin/marketplace-orders/action.php'))
                self.assertIn('Tandai Lunas (COD)', read(f'{w}/admin/marketplace-orders/detail.php'))
                self.assertIn('marketplace_cod_enabled', read(f'{w}/admin/marketplace-settings/index.php'))

    def test_kurir_boleh_cod_belum_lunas(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/StoreCourier.php')
                self.assertIn('$isCodOrder', s)
                self.assertIn('marketplace_notify_store_courier', s)


class TestRuntime(unittest.TestCase):
    def test_cod_pickup_dan_wa_kurir(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$out = [];
$out[] = 'store:' . (marketplace_cod_allowed('store') ? 'ya' : 'tidak');
$out[] = 'pickup:' . (marketplace_cod_allowed('pickup') ? 'ya' : 'tidak');
$out[] = 'jne:' . (marketplace_cod_allowed('jne') ? 'ya' : 'tidak');
$branch = $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$res = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]],
    'courier_code' => 'pickup',
    'pickup_branch_id' => (int) $branch,
    'shipping_cost' => 0,
    'payment_method' => 'cod',
    'recipient_name' => 'ZZ Uji COD',
    'recipient_phone' => '089900001234',
    'address' => 'Ambil di toko',
    'checkout_key' => 'zz34' . bin2hex(random_bytes(6)),
]);
$o = $db->prepare("SELECT id,payment_status,order_status,payment_expired_at,payment_txn_id FROM online_orders WHERE order_number=?");
$o->execute([$res['order_number']]);
$row = $o->fetch(PDO::FETCH_ASSOC);
$out[] = 'status:' . $row['payment_status'] . '/' . $row['order_status'];
$out[] = 'expired:' . (empty($row['payment_expired_at']) ? 'kosong' : 'ada');
$out[] = 'txn:' . (empty($row['payment_txn_id']) ? 'kosong' : 'ada');
$ev = $db->prepare("SELECT status FROM online_order_events WHERE order_id=? ORDER BY id");
$ev->execute([(int) $row['id']]);
$out[] = 'event:' . implode(',', array_column($ev->fetchAll(), 'status'));

$db->prepare("UPDATE online_orders SET courier_code='store',fulfillment_status='ready_ship' WHERE id=?")->execute([(int) $row['id']]);
$courier = $db->query("SELECT id,branch_id FROM marketplace_couriers WHERE status='active' LIMIT 1")->fetch();
$db->prepare("UPDATE online_orders SET branch_id=? WHERE id=?")->execute([(int) $courier['branch_id'], (int) $row['id']]);
$assign = marketplace_store_courier_assign($db, (int) $row['id'], (int) $courier['id'], 1);
$out[] = 'assign:' . ($assign['status'] ?? '?');
$notev = $db->prepare("SELECT status FROM online_order_events WHERE order_id=? AND status LIKE 'courier_notif%' ORDER BY id DESC LIMIT 1");
$notev->execute([(int) $row['id']]);
$out[] = 'wa:' . (string) $notev->fetchColumn();

// bersihkan
foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events'] as $t) {
    $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $row['id']]);
}
$db->prepare("DELETE FROM online_order_items WHERE order_id=?")->execute([(int) $row['id']]);
$db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $row['id']]);
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:400])
        self.assertIn('store:ya|pickup:ya|jne:tidak', out)
        self.assertIn('status:unpaid/confirmed|expired:kosong|txn:kosong', out)
        self.assertIn('event:cod_confirmed', out)
        self.assertIn('assign:assigned', out)
        self.assertTrue('wa:courier_notified' in out or 'wa:courier_notify_failed' in out, out)

    def test_tolak_cod_untuk_ekspedisi(self):
        rc, out = php(r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
echo marketplace_cod_allowed('jne') ? 'DITERIMA' : 'DITOLAK';
''')
        self.assertEqual(rc, 0, out[:300])
        self.assertEqual(out.strip(), 'DITOLAK')


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 34))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
