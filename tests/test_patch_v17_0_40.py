"""Kontrak + runtime test v17.0.40 — QRIS/VA auto-batal 30 menit, stok kembali.

Jalankan: python3 -m unittest tests.test_patch_v17_0_40 -v
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
    def test_timeout_setting(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_payment_timeout_minutes', s)
                self.assertIn("setting('marketplace_payment_timeout_minutes', '30')", s)

    def test_expiry_dari_mysql(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                blok = re.search(r'function marketplace_expire_if_needed.*?\n\}', s, re.S).group(0)
                self.assertIn('NOW()', blok)
                self.assertNotIn('time()', blok)
                self.assertIn("['pending', 'error']", blok)

    def test_payment_expired_dibatasi(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('payment_expired_at=LEAST(', s)

    def test_countdown_ui(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/order.php')
                self.assertIn('data-pay-left', s)
                self.assertIn('otomatis dibatalkan', s)
                self.assertIn('TIMESTAMPDIFF(SECOND, NOW()', s)

    def test_tool_cron(self):
        p = ROOT / 'tools/expire_orders.php'
        self.assertTrue(p.is_file())
        s = p.read_text()
        self.assertIn("in_array('--apply', $args, true)", s)
        self.assertIn('DRY-RUN', s)


class TestRuntime(unittest.TestCase):
    def test_30_menit_batal_dan_stok_kembali(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$stok = function () use ($db, $b) {
    return (int) $db->query("SELECT stock FROM product_branch_stocks WHERE product_id=4228 AND branch_id={$b}")->fetchColumn();
};
$mk = function ($pm, $key) use ($db, $b) {
    return marketplace_create_order($db, [
        'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
        'shipping_cost' => 0, 'payment_method' => $pm, 'recipient_name' => 'ZZ Timer 40',
        'recipient_phone' => '089900004040', 'address' => 'x', 'checkout_key' => $key . bin2hex(random_bytes(4)),
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
$s0 = $stok();
$r = $mk('qris', 'zz40a');
$o = marketplace_get_order($db, $r['order_number']);
$s1 = $stok();
$sisa = (int) $db->query("SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),payment_expired_at)) FROM online_orders WHERE id={$o['id']}")->fetchColumn();
$out[] = 'dibuat:' . $o['payment_status'] . ':stok' . ($s1 === $s0 - 1 ? 'turun' : 'salah');
$out[] = 'sisa_menit:' . round($sisa / 60);
$belum = marketplace_expire_if_needed($db, $o);
$out[] = 'sebelum_lewat:' . $belum['payment_status'];

$db->prepare("UPDATE online_orders SET payment_expired_at=DATE_SUB(NOW(), INTERVAL 31 MINUTE) WHERE id=?")->execute([(int) $o['id']]);
$o = marketplace_get_order($db, $r['order_number']);
$lewat = marketplace_expire_if_needed($db, $o);
$out[] = 'sesudah_lewat:' . $lewat['payment_status'] . '/' . $lewat['order_status'];
$out[] = 'stok_kembali:' . ($stok() === $s0 ? 'ya' : 'tidak');

// fallback tanpa payment_expired_at (created_at + 30 menit)
$r2 = $mk('bri_va', 'zz40b');
$o2 = marketplace_get_order($db, $r2['order_number']);
$db->prepare("UPDATE online_orders SET payment_expired_at=NULL, created_at=DATE_SUB(NOW(), INTERVAL 31 MINUTE) WHERE id=?")->execute([(int) $o2['id']]);
$o2 = marketplace_get_order($db, $r2['order_number']);
$f = marketplace_expire_if_needed($db, $o2);
$out[] = 'tanpa_expiry:' . $f['payment_status'] . ':stok' . ($stok() === $s0 ? 'kembali' : 'tetap');

$clean($o['id']);
$clean($o2['id']);
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:500])
        self.assertIn('dibuat:pending:stokturun', out)
        self.assertIn('sisa_menit:30', out)
        self.assertIn('sebelum_lewat:pending', out)
        self.assertIn('sesudah_lewat:expired/expired', out)
        self.assertIn('stok_kembali:ya', out)
        self.assertIn('tanpa_expiry:expired:stokkembali', out)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 40))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
