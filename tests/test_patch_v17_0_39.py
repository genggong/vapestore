"""Kontrak + runtime test v17.0.39 — tukar poin maksimal 100 poin (Rp10.000).

Jalankan: python3 -m unittest tests.test_patch_v17_0_39 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}
MOD = {'legacy': 'legacy/app', 'public': 'app', 'pos': 'pos/app'}
JS = ('assets/js', 'legacy/public/assets/js', 'public/assets/js', 'pos/public/assets/js')
ENV = dict(os.environ, VAPEKONG_DB_HOST='127.0.0.1', VAPEKONG_DB_PORT='3306',
           VAPEKONG_DB_NAME='vapekong_prod', VAPEKONG_DB_USER='vk_test2', VAPEKONG_DB_PASS='VkTest2!Pass2026')


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


def php(code):
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', code], capture_output=True, text=True,
                       env=ENV, cwd=str(ROOT))
    return r.returncode, (r.stdout or '') + (r.stderr or '')


class TestKontrak(unittest.TestCase):
    def test_clamp_server(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/MarketplaceV15.php')
                self.assertIn("setting('member_point_max_redeem','100')", s)
                self.assertIn(',$cap);', s)
                self.assertIn("'max'=>$cap", s)

    def test_ddl_di_luar_transaksi(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/MarketplaceV15.php')
                self.assertIn('function marketplace_v15_points_ensure', s)
                redeem = re.search(r'function marketplace_v15_redeem_points.*?\n\}', s, re.S).group(0)
                self.assertNotIn('CREATE TABLE', redeem)
                self.assertIn('marketplace_v15_points_ensure($db)', read(f'{m}/Modules/Marketplace/Marketplace.php'))

    def test_checkout_dan_js(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/checkout.php')
                self.assertIn('data-point-max', s)
                self.assertIn('$pointMaxRedeem', s)
                self.assertIn('maksimal', s)
        for j in JS:
            with self.subTest(file=j):
                s = read(f'{j}/marketplace-checkout.js')
                self.assertIn('pointMax', s)
                self.assertIn('pointAvailable, pointMax', s)


class TestRuntime(unittest.TestCase):
    def test_maks_100_poin(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$db->prepare("INSERT INTO customers(name,phone,points,status,created_at) VALUES('ZZ Poin 39','089900003939',500,'active',NOW())")->execute();
$cid = (int) $db->lastInsertId();
$out = [];
foreach ([500, 120, 100] as $req) {
    $p = marketplace_v15_points_preview($db, $cid, $req, 1000000);
    $out[] = "req{$req}={$p['points']}:{$p['discount']}";
}
$p = marketplace_v15_points_preview($db, $cid, 500, 15000);
$out[] = 'basis15rb=' . $p['points'];

// pesanan nyata (dulu gagal: "There is no active transaction")
$_SESSION['marketplace_customer_id'] = $cid;
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$r = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
    'shipping_cost' => 0, 'payment_method' => 'cod', 'recipient_name' => 'ZZ Poin 39',
    'recipient_phone' => '089900003939', 'address' => 'x',
    'checkout_key' => 'zz39x' . bin2hex(random_bytes(4)), 'points_to_use' => 500,
]);
$st = $db->prepare("SELECT id,points_redeemed,points_discount,grand_total FROM online_orders WHERE order_number=?");
$st->execute([$r['order_number']]);
$o = $st->fetch(PDO::FETCH_ASSOC);
$sisa = (int) $db->query("SELECT points FROM customers WHERE id={$cid}")->fetchColumn();
$out[] = 'order=' . (int) $o['points_redeemed'] . ':' . (int) $o['points_discount'] . ':sisa' . $sisa;

try { marketplace_release_stock($db, (int) $o['id'], 'cleanup'); } catch (Throwable $e) {}
foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events','online_order_items'] as $t) {
    $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $o['id']]);
}
$db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $o['id']]);
$db->prepare("DELETE FROM customer_points WHERE customer_id=?")->execute([$cid]);
$db->prepare("DELETE FROM customers WHERE id=?")->execute([$cid]);
echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:400])
        self.assertIn('req500=100:10000', out)
        self.assertIn('req120=100:10000', out)
        self.assertIn('req100=100:10000', out)
        self.assertIn('basis15rb=100', out)
        self.assertIn('order=100:10000:sisa400', out)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 39))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
