"""Kontrak + runtime test v17.0.37.

- Kartu COD di samping QRIS (tidak disembunyikan, hanya disabled untuk ekspedisi).
- Notifikasi "Siapkan uang tunai" di halaman pesanan COD.
- Pembatalan mandiri oleh pembeli maksimal 10 menit (semua metode bayar), berlaku juga untuk guest (token).

Jalankan: python3 -m unittest tests.test_patch_v17_0_37 -v
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
    def test_cod_di_sebelah_qris(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/checkout.php')
                i_qris = s.find('value="qris"')
                i_cod = s.find('data-cod-card')
                i_bri = s.find('value="bri_va"')
                self.assertTrue(0 < i_qris < i_cod < i_bri, 'kartu COD harus di antara QRIS dan BRI')
                self.assertIn('data-cod-hint', s)
                self.assertNotIn('data-cod-card hidden', s)

    def test_js_tidak_menyembunyikan(self):
        for j in JS:
            with self.subTest(file=j):
                s = read(f'{j}/marketplace-checkout.js')
                self.assertIn('kartu COD selalu tampil', s)
                self.assertIn("codCard.hidden = false", s)

    def test_helper_pembatalan(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_cancel_window_minutes', s)
                self.assertIn('function marketplace_order_cancel_remaining', s)
                self.assertIn('function marketplace_customer_cancel_order', s)
                self.assertIn('TIMESTAMPDIFF(SECOND, created_at, NOW())', s)

    def test_ui_pesanan(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/order.php')
                self.assertIn('cod-prepare-banner', s)
                self.assertIn('Siapkan uang tunai', s)
                self.assertIn('data-cancel-seconds', s)
                self.assertIn('cancel-order.php', s)
                self.assertTrue((ROOT / w / 'store/cancel-order.php').is_file())

    def test_css(self):
        for c in ('assets/css', 'legacy/public/assets/css', 'public/assets/css', 'pos/public/assets/css'):
            with self.subTest(dir=c):
                s = read(f'{c}/storefront.css')
                self.assertIn('.cod-prepare-banner', s)
                self.assertIn('.cancel-window-box', s)


class TestRuntime(unittest.TestCase):
    def test_batal_dan_batas_10_menit(self):
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$mk = function ($pm) use ($db, $b) {
    return marketplace_create_order($db, [
        'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
        'shipping_cost' => 0, 'payment_method' => $pm, 'recipient_name' => 'ZZ Kontrak 37',
        'recipient_phone' => '089900003737', 'address' => 'Ambil di toko',
        'checkout_key' => 'zz37c' . bin2hex(random_bytes(4)),
    ]);
};
$cleanup = function ($id) use ($db) {
    try { marketplace_release_stock($db, (int) $id, 'cleanup test'); } catch (Throwable $e) {}
    foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events','online_order_items'] as $t) {
        $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $id]);
    }
    $db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $id]);
};
$out = [];

// COD: jendela aktif + bisa batal (semua metode)
$c = $mk('cod');
$oc = marketplace_get_order($db, $c['order_number']);
$out[] = 'cod_window:' . (marketplace_order_cancel_remaining($oc, $db) > 0 ? 'aktif' : 'tutup');
$out[] = 'cod_total:' . ($oc['grand_total'] > 0 ? 'ada' : 'kosong');
$rc = marketplace_customer_cancel_order($db, $c['order_number'], $oc['public_token']);
$oc2 = marketplace_get_order($db, $c['order_number']);
$out[] = 'cod_batal:' . ($rc['ok'] ? 'ok' : 'gagal') . '/' . $oc2['order_status'] . '/stok:' . (!empty($oc2['stock_released_at']) ? 'ya' : 'tidak');
$cleanup($oc['id']);

// lewat 10 menit -> ditolak
$q = $mk('qris');
$oq = marketplace_get_order($db, $q['order_number']);
$db->prepare("UPDATE online_orders SET created_at=DATE_SUB(NOW(), INTERVAL 11 MINUTE) WHERE id=?")->execute([(int) $oq['id']]);
$oq = marketplace_get_order($db, $q['order_number']);
$rq = marketplace_customer_cancel_order($db, $q['order_number'], $oq['public_token']);
$oq2 = marketplace_get_order($db, $q['order_number']);
$out[] = 'lewat10:' . ($rq['ok'] ? 'ok' : 'ditolak') . '/' . $oq2['order_status'] . '/pesan:' . (strpos($rq['message'], '10 menit') !== false ? 'ada' : 'tidak');
$cleanup($oq['id']);

// 9 menit -> masih boleh
$q2 = $mk('bri_va');
$oq3 = marketplace_get_order($db, $q2['order_number']);
$db->prepare("UPDATE online_orders SET created_at=DATE_SUB(NOW(), INTERVAL 9 MINUTE) WHERE id=?")->execute([(int) $oq3['id']]);
$oq3 = marketplace_get_order($db, $q2['order_number']);
$left = marketplace_order_cancel_remaining($oq3, $db);
$out[] = 'sembilan_menit:' . ($left > 0 && $left <= 120 ? 'boleh' : 'salah') . '/' . $left;
$cleanup($oq3['id']);

echo implode('|', $out);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:500])
        self.assertIn('cod_window:aktif', out)
        self.assertIn('cod_total:ada', out)
        self.assertIn('cod_batal:ok/cancelled/stok:ya', out)
        self.assertIn('lewat10:ditolak/pending_payment/pesan:ada', out)
        self.assertIn('sembilan_menit:boleh/', out)

    def test_guest_tanpa_login_bisa_batal(self):
        """Endpoint publik: token pesanan cukup, tanpa sesi member."""
        kode = r'''
require "/home/ubuntu/vapestore/legacy/public/store/_data.php";
$db = storefront_db();
$b = (int) $db->query("SELECT id FROM branches WHERE is_active=1 ORDER BY is_main DESC LIMIT 1")->fetchColumn();
$r = marketplace_create_order($db, [
    'items' => [['id' => 4228, 'qty' => 1]], 'courier_code' => 'pickup', 'pickup_branch_id' => $b,
    'shipping_cost' => 0, 'payment_method' => 'qris', 'recipient_name' => 'ZZ Guest 37',
    'recipient_phone' => '089900003838', 'address' => 'Ambil di toko',
    'checkout_key' => 'zz37g' . bin2hex(random_bytes(4)),
]);
$o = marketplace_get_order($db, $r['order_number']);
unset($_SESSION['marketplace_customer_id']);
$res = marketplace_customer_cancel_order($db, $o['order_number'], $o['public_token']);
$after = marketplace_get_order($db, $o['order_number']);
echo ($res['ok'] && $after['order_status'] === 'cancelled') ? 'guest_bisa' : 'guest_gagal';
try { marketplace_release_stock($db, (int) $o['id'], 'cleanup test'); } catch (Throwable $e) {}
foreach (['marketplace_courier_events','marketplace_courier_assignments','marketplace_order_notifications','online_order_events','online_order_items'] as $t) {
    $db->prepare("DELETE FROM {$t} WHERE order_id=?")->execute([(int) $o['id']]);
}
$db->prepare("DELETE FROM online_orders WHERE id=?")->execute([(int) $o['id']]);
'''
        rc, out = php(kode)
        self.assertEqual(rc, 0, out[:400])
        self.assertIn('guest_bisa', out)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 37))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
