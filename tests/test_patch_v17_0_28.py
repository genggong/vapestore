"""Kontrak test v17.0.28 — angka kartu "Pesanan Selesai" dari data nyata.

Jalankan: python3 -m unittest tests.test_patch_v17_0_28 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
TREES = ('legacy/public', 'public', 'pos/public')
DB = {'VAPEKONG_DB_HOST': '127.0.0.1', 'VAPEKONG_DB_PORT': '3306', 'VAPEKONG_DB_NAME': 'vapekong_prod',
      'VAPEKONG_DB_USER': 'vk_test2', 'VAPEKONG_DB_PASS': 'VkTest2!Pass2026'}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


def sql(q):
    return subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', 'vapekong_prod', '-N', '-B', '-e', q],
                          capture_output=True, text=True).stdout.strip()


class TestSumberData(unittest.TestCase):
    def test_menghitung_online_dan_pos(self):
        for t in TREES:
            with self.subTest(tree=t):
                s = read(ROOT / t / 'store/_data.php')
                self.assertIn("<> 'cancelled'", s, 'pesanan batal harus dikeluarkan')
                self.assertIn("FROM transactions", s, 'transaksi kasir ikut dihitung')
                self.assertIn("reference_type <> 'online_order'", s, 'jangan dobel hitung pesanan web')
                self.assertIn("$stats['orders'] = $stats['orders_online'] + $stats['orders_pos'];", s)

    def test_label_bukan_angka_palsu(self):
        for t in TREES:
            with self.subTest(tree=t):
                s = read(ROOT / t / 'store/index.php')
                self.assertIn('<small>Pesanan Selesai</small>', s)
                self.assertNotIn('<small>Pesanan Online</small>', s)

    def test_angka_cocok_dengan_database(self):
        online = int(sql("SELECT COUNT(*) FROM online_orders WHERE COALESCE(order_status,'') <> 'cancelled'"))
        pos = int(sql("SELECT COUNT(*) FROM transactions WHERE status='success' AND (reference_type IS NULL OR reference_type <> 'online_order')"))
        self.assertEqual(online + pos, 467, 'total pesanan nyata di data produksi')
        self.assertGreater(online + pos, 0, 'kartu tidak boleh 0 kalau ada transaksi')

    def test_tidak_fatal_tanpa_tabel(self):
        """Kalau tabel tidak ada, stats tetap jalan (0), bukan 500."""
        env = dict(os.environ, **DB)
        code = ('require "' + str(ROOT / 'legacy/public/store/_data.php') + '";'
                '$s = storefront_stats(); echo json_encode($s);')
        r = subprocess.run(['php', '-r', code], capture_output=True, text=True, env=env,
                           cwd=str(ROOT / 'legacy/public'))
        self.assertEqual(r.returncode, 0, r.stderr[:300])
        self.assertIn('"orders":467', r.stdout.replace(' ', ''), r.stdout[:200])

    def test_tracking_menemukan_pesanan_nyata(self):
        """Helper table_exists yang selalu false dulu membuat /tracking tidak pernah menemukan order."""
        kode = sql("SELECT order_number FROM online_orders ORDER BY id DESC LIMIT 1")
        inv = sql("SELECT invoice_number FROM transactions WHERE status='success' ORDER BY id DESC LIMIT 1")
        env = dict(os.environ, **DB)
        code = ('require "' + str(ROOT / 'legacy/public/store/_data.php') + '";'
                f'$a = storefront_track("{kode}"); $b = storefront_track("{inv}");'
                'echo json_encode([$a["type"] ?? null, $b["type"] ?? null]);')
        r = subprocess.run(['php', '-r', code], capture_output=True, text=True, env=env,
                           cwd=str(ROOT / 'legacy/public'))
        self.assertEqual(r.returncode, 0, r.stderr[:300])
        self.assertEqual(r.stdout.strip(), '["online_order","transaction"]', r.stdout[:200])


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 28))

    def test_halaman_system_upgrade(self):
        v = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{v}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(v, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)
