"""Kontrak test v17.0.30 — sort Terlaris + stok habis di bawah, notifikasi WA pesanan ke cabang.

Jalankan: python3 -m unittest tests.test_patch_v17_0_30 -v
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


def sql(q):
    r = subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', 'vapekong_prod', '-N', '-B', '-e', q],
                       capture_output=True, text=True)
    return r.stdout.strip()


def php(code):
    bridge = str(ROOT / 'legacy/public/store/_data.php')
    code = code.replace('__BRIDGE__', bridge)
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', code], capture_output=True, text=True, env=ENV,
                       cwd=str(ROOT))
    assert r.returncode == 0, (r.stderr or r.stdout)[:500]
    return r.stdout.strip()


class TestSort(unittest.TestCase):
    def test_opsi_terlaris_ada(self):
        for t, base in WEB.items():
            with self.subTest(tree=t):
                self.assertIn('value="best"', read(f'{base}/store/products.php'))
                self.assertIn('Terlaris', read(f'{base}/store/products.php'))

    def test_semua_sort_stok_habis_terakhir(self):
        for t, base in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{base}/store/_data.php')
                blok = s[s.index('$outOfStockLast ='):s.index('$outOfStockLast =') + 1200]
                kasus = re.findall(r"case '([a-z_]+)': \$order = (.+?); break;", blok)
                self.assertGreaterEqual(len(kasus), 6, 'semua opsi sort harus eksplisit')
                for nama, expr in kasus:
                    self.assertIn('$outOfStockLast', expr, f'case {nama} tidak menaruh stok habis di bawah')
                self.assertIn('storefront_sold_expr($db)', dict(kasus)['best'])

    def test_ekspresi_terjual_pos_dan_web(self):
        for t, base in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{base}/store/_data.php')
                self.assertIn('function storefront_sold_expr', s)
                self.assertIn('FROM transaction_items ti', s)
                self.assertIn('FROM online_order_items oi', s)
                self.assertIn("o.order_status NOT IN ('cancelled','expired')", s)


class TestNotifikasiWa(unittest.TestCase):
    def test_fungsi_lengkap_di_3_tree(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                for fn in ('marketplace_order_notify_install', 'marketplace_order_notify_target',
                           'marketplace_order_notify_message', 'marketplace_order_notify_queue',
                           'marketplace_order_send_wa', 'marketplace_order_notify_dispatch'):
                    self.assertIn('function ' + fn + '(', s)

    def test_hook_di_create_order(self):
        for t, m in MOD.items():
            with self.subTest(tree=t):
                s = read(f'{m}/Modules/Marketplace/Marketplace.php')
                self.assertIn('marketplace_order_notify_queue($db, (int) $orderId)', s)
                self.assertIn('register_shutdown_function', s)

    def test_target_cabang_dan_gateway(self):
        s = read('legacy/app/Modules/Marketplace/Marketplace.php')
        self.assertIn('SELECT phone FROM branches WHERE id=?', s)
        self.assertIn('marketplace_wa_send_text(', s)
        self.assertIn('wa_send_fonnte(', s, 'harus ada fallback Fonnte')

    def test_pengaturan_tampil_di_halaman(self):
        for t, base in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{base}/admin/marketplace-settings/index.php')
                self.assertIn('name="marketplace_order_wa_enabled"', s)
                self.assertIn('name="marketplace_order_wa_target"', s)


class TestRuntime(unittest.TestCase):
    def test_antrean_dan_isi_pesan(self):
        out = php(r'''
require "__BRIDGE__";
$db = storefront_db();
$id = (int) $db->query("SELECT id FROM online_orders ORDER BY id DESC LIMIT 1")->fetchColumn();
$db->exec("DELETE FROM marketplace_order_notifications WHERE order_id=$id");
$ok = marketplace_order_notify_queue($db, $id);
$row = $db->query("SELECT target,status,message FROM marketplace_order_notifications WHERE order_id=$id ORDER BY id DESC LIMIT 1")->fetch();
$info = ['queued'=>$ok,'target'=>$row['target'] ?? '','status'=>$row['status'] ?? '','has_no'=>$row && str_contains((string)$row['message'], 'PESANAN ONLINE BARU')];
$db->exec("DELETE FROM marketplace_order_notifications WHERE order_id=$id");
echo json_encode($info);
''')
        self.assertIn('"queued":true', out.replace(' ', ''))
        self.assertIn('"has_no":true', out.replace(' ', ''))
        self.assertIn('"status":"pending"', out.replace(' ', ''))

    def test_dispatch_aman_saat_gateway_mati(self):
        out = php(r'''
require "__BRIDGE__";
$db = storefront_db();
$id = (int) $db->query("SELECT id FROM online_orders ORDER BY id DESC LIMIT 1")->fetchColumn();
$db->exec("DELETE FROM marketplace_order_notifications WHERE order_id=$id");
$old = [];
foreach (['marketplace_wa_enabled','fonnte_token'] as $k) {
    $q = $db->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $q->execute([$k]); $old[$k] = (string) ($q->fetchColumn() ?: '');
}
$db->exec("UPDATE settings SET setting_value='off' WHERE setting_key='marketplace_wa_enabled'");
$db->exec("UPDATE settings SET setting_value='' WHERE setting_key='fonnte_token'");
try {
    marketplace_order_notify_queue($db, $id);
    $stats = marketplace_order_notify_dispatch($db, 3);
    $st = (string) $db->query("SELECT status FROM marketplace_order_notifications WHERE order_id=$id ORDER BY id DESC LIMIT 1")->fetchColumn();
} finally {
    $u = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
    $u->execute([$old['marketplace_wa_enabled'], 'marketplace_wa_enabled']);
    $u->execute([$old['fonnte_token'], 'fonnte_token']);
    $db->exec("DELETE FROM marketplace_order_notifications WHERE order_id=$id");
}
echo json_encode(['stats'=>$stats,'status'=>$st]);
''')
        self.assertIn('"status":"failed"', out.replace(' ', ''))
        self.assertNotIn('"sent":1', out.replace(' ', ''), 'tidak boleh mengirim WA saat gateway mati')

    def test_sort_terlaris_mengikuti_penjualan(self):
        top = sql("""SELECT p.name FROM products p
            LEFT JOIN (SELECT ti.product_id,SUM(qty) q FROM transaction_items ti JOIN transactions t ON t.id=ti.transaction_id AND t.status='success' GROUP BY ti.product_id) a ON a.product_id=p.id
            LEFT JOIN (SELECT oi.product_id,SUM(qty) q FROM online_order_items oi JOIN online_orders o ON o.id=oi.order_id AND o.order_status NOT IN ('cancelled','expired') GROUP BY oi.product_id) w ON w.product_id=p.id
            WHERE p.is_active=1 AND COALESCE(a.q,0)+COALESCE(w.q,0) > 0
            ORDER BY COALESCE(a.q,0)+COALESCE(w.q,0) DESC, p.name LIMIT 1""")
        out = php(r'''
require "__BRIDGE__";
$rows = storefront_products(['limit'=>3,'sort'=>'best']);
echo json_encode(array_map(fn($r)=>$r['name'], $rows), JSON_UNESCAPED_UNICODE);
''')
        self.assertIn(top.split(' - ')[0][:18], out, f'produk terlaris {top!r} harus di urutan pertama: {out[:200]}')


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 30))

    def test_halaman_system_upgrade(self):
        v = read('VERSION').strip()
        self.assertIn(f'v{v}', read('legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(v, read(rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)
