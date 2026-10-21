"""Kontrak + runtime test v17.0.38 — Ambil di Toko lewat batas = batal otomatis + stok kembali.

Jalankan: python3 -m unittest tests.test_patch_v17_0_38 -v
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
    def test_helper_ada(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_pickup_expire_if_needed', s)
                self.assertIn('Batas pengambilan di toko terlewat', s)
                self.assertIn('SELECT (pickup_deadline_at IS NOT NULL AND pickup_deadline_at < NOW())', s)

    def test_halaman_memanggil(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/order.php')
                self.assertIn('marketplace_pickup_expire_if_needed($db, $order)', s)
                self.assertIn('marketplace_store_pickup_deadline_passed($order, $db)', s)

    def test_tool_cron(self):
        p = ROOT / 'tools/expire_pickup_orders.php'
        self.assertTrue(p.is_file())
        s = p.read_text()
        self.assertIn("in_array('--apply', $args, true)", s)
        self.assertIn('DRY-RUN', s)

    def test_tidak_menyentuh_yang_sudah_dibayar(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                blok = re.search(r'function marketplace_pickup_expire_if_needed.*?\n\}', s, re.S).group(0)
                self.assertIn("in_array((string) ($order['payment_status'] ?? ''), ['pending', 'unpaid', 'error'], true)", blok)


class TestRuntime(unittest.TestCase):
    def test_expire_mengembalikan_stok(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$stok = function () use ($db, $b) {
    return (int) $db->query("SELECT stock FROM product_branch_stocks WHERE product_id=4228 AND branch_id={$b}")->fetchColumn();
};
$master = function () use ($db) {
    return (int) $db->query("SELECT stock FROM products WHERE id=4228")->fetchColumn();
};
$s0 = $stok(); $m0 = $master();
$r = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
    'shipping_cost' => 0, 'payment_method' => 'cod', 'recipient_name' => 'ZZ Expire 38',
    'recipient_phone' => '089900003939', 'address' => 'x', 'checkout_key' => 'zz38t' . bin2hex(random_bytes(4)),
]);
$o = marketplace_get_order($db, $r['order_number']);
$s1 = $stok();
$db->prepare("UPDATE online_orders SET pickup_deadline_at=DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE id=?")->execute([(int) $o['id']]);
$o = marketplace_get_order($db, $r['order_number']);
$o2 = marketplace_pickup_expire_if_needed($db, $o);
$s2 = $stok(); $m2 = $master();
$out = [];
$out[] = 'turun:' . ($s1 === $s0 - 1 ? 'ya' : 'tidak');
$out[] = 'kembali:' . ($s2 === $s0 ? 'ya' : 'tidak(' . $s2 . '/' . $s0 . ')');
$out[] = 'master:' . ($m2 === $m0 ? 'ya' : 'tidak(' . $m2 . '/' . $m0 . ')');
$out[] = 'status:' . $o2['order_status'] . '/' . $o2['payment_status'];
$out[] = 'dilepas:' . (!empty($o2['stock_released_at']) ? 'ya' : 'tidak');
$ev = $db->prepare("SELECT title FROM online_order_events WHERE order_id=? ORDER BY id DESC LIMIT 3");
$ev->execute([(int) $o['id']]);
$titles = $ev->fetchAll(PDO::FETCH_COLUMN);
$out[] = 'event:' . (in_array('Batas ambil terlewat', $titles, true) ? 'ada' : 'tidak');

// pesanan kedua: belum lewat batas -> tidak disentuh
$r2 = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
    'shipping_cost' => 0, 'payment_method' => 'cod', 'recipient_name' => 'ZZ Expire 38b',
    'recipient_phone' => '089900003940', 'address' => 'x', 'checkout_key' => 'zz38u' . bin2hex(random_bytes(4)),
]);
$o3 = marketplace_get_order($db, $r2['order_number']);
$o4 = marketplace_pickup_expire_if_needed($db, $o3);
$out[] = 'belum_lewat:' . ($o4['order_status'] === $o3['order_status'] ? 'aman' : 'salah');

foreach ([$o['id'], $o3['id']] as $oid) {
    try { marketplace_release_stock($db, (int) $oid, 'cleanup test'); } catch (Throwable $e) {}
    foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events','online_order_items'] as $t) {
        $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $oid]);
    }
    $db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $oid]);
}
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:500])
        self.assertIn('turun:ya', out)
        self.assertIn('kembali:ya', out)
        self.assertIn('master:ya', out)
        self.assertIn('status:expired/expired', out)
        self.assertIn('dilepas:ya', out)
        self.assertIn('event:ada', out)
        self.assertIn('belum_lewat:aman', out)

    def test_dibayar_tidak_dibatalkan(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$o = $db->query("SELECT * FROM online_orders LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$o['courier_code'] = 'pickup';
$o['payment_status'] = 'paid';
$o['order_status'] = 'processing';
$o['pickup_deadline_at'] = date('Y-m-d H:i:s', time() - 3600);
$res = marketplace_pickup_expire_if_needed($db, $o);
echo $res['order_status'];
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:300])
        self.assertEqual(out.strip(), 'processing')


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 38))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
