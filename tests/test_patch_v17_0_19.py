"""Kontrak test v17.0.19 — palet monokrom storefront + pemulihan tampilan Buat Request Stock.

Jalankan: python3 -m unittest tests.test_patch_v17_0_19 -v
"""
import colorsys
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
HEX = re.compile(r'#([0-9a-fA-F]{6})\b')

CSS_DIRS = {
    'legacy': ROOT / 'legacy' / 'public' / 'assets' / 'css',
    'public': ROOT / 'public' / 'assets' / 'css',
    'pos': ROOT / 'pos' / 'public' / 'assets' / 'css',
    'root': ROOT / 'assets' / 'css',
}
STOREFRONT_CSS = ['storefront.css', 'marketplace-theme.css']

TREES = {
    'legacy': ROOT / 'legacy' / 'public' / 'admin',
    'public': ROOT / 'public' / 'admin',
    'pos': ROOT / 'pos' / 'public' / 'admin',
}
JS_PATHS = [
    ROOT / 'assets' / 'js' / 'stock-request.js',
    ROOT / 'legacy' / 'public' / 'assets' / 'js' / 'stock-request.js',
    ROOT / 'public' / 'assets' / 'js' / 'stock-request.js',
    ROOT / 'pos' / 'public' / 'assets' / 'js' / 'stock-request.js',
]


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


def is_blue_or_purple(hexstr):
    r, g, b = (int(hexstr[i:i + 2], 16) for i in (0, 2, 4))
    h, s, _ = colorsys.rgb_to_hsv(r / 255, g / 255, b / 255)
    return 200 <= h * 360 <= 305 and s > 0.03


class TestPaletMonokrom(unittest.TestCase):
    def test_storefront_css_tanpa_biru_ungu(self):
        for tree, d in CSS_DIRS.items():
            for name in STOREFRONT_CSS:
                p = d / name
                if not p.is_file():
                    continue
                with self.subTest(tree=tree, file=name):
                    bad = sorted({m.group(0) for m in HEX.finditer(read(p)) if is_blue_or_purple(m.group(1))})
                    self.assertEqual(bad, [], f'{tree}/{name}: masih ada warna biru/ungu {bad[:5]}')

    def test_variabel_tema_jadi_hitam(self):
        for tree, d in CSS_DIRS.items():
            p = d / 'storefront.css'
            if not p.is_file():
                continue
            with self.subTest(tree=tree):
                src = read(p)
                self.assertIn('--purple:#111111', src, f'{tree}: --purple belum hitam')
                self.assertNotIn('#e8d7ff', src, f'{tree}: --purple-line masih ungu')

    def test_preset_monochrome_ada_di_3_tree(self):
        for rel in ('legacy/app/Modules/Marketplace/Marketplace.php',
                    'app/Modules/Marketplace/Marketplace.php',
                    'pos/app/Modules/Marketplace/Marketplace.php'):
            with self.subTest(file=rel):
                src = read(ROOT / rel)
                self.assertIn("'monochrome' =>", src)
                self.assertIn('Hitam Putih', src)

    def test_warna_status_tetap_semantik(self):
        """Merah/hijau status tidak boleh ikut dinetralkan."""
        src = read(ROOT / 'legacy' / 'public' / 'assets' / 'css' / 'storefront.css')
        for keep in ('#be123c', '#047857', '#e82929'):
            with self.subTest(color=keep):
                self.assertIn(keep, src, f'{keep} hilang — warna status ikut berubah')

    def test_tool_tema_ada(self):
        src = read(ROOT / 'tools' / 'set_theme_mono.php')
        self.assertIn('marketplace_theme_preset', src)
        self.assertIn("'monochrome' =>", src)
        self.assertIn('--revert', src)


class TestTampilanRequestStock(unittest.TestCase):
    """Halaman Buat Request Stock harus punya penanda daftar produk di semua tree."""

    def test_create_punya_penanda_daftar_produk(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'create.php')
                self.assertIn('data-product-group', src, f'{tree}: grup produk hilang')
                self.assertIn('request-product-row', src, f'{tree}: baris produk hilang')
                self.assertIn('id="requestProductList"', src, f'{tree}: container daftar produk hilang')

    def test_create_pakai_catalogfast_dan_grouping(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'create.php')
                self.assertIn('CatalogFast.php', src, f'{tree}: require CatalogFast hilang')
                self.assertIn('catalog_fast_variants_by_product', src, f'{tree}: varian tidak diambil bulk')
                self.assertIn('warehouse_stock', src, f'{tree}: stok gudang tidak ditampilkan')

    def test_catalogfast_ada_di_3_tree(self):
        for rel in ('app/Modules/CatalogFast.php', 'legacy/app/Modules/CatalogFast.php', 'pos/app/Modules/CatalogFast.php'):
            with self.subTest(file=rel):
                self.assertIn('function catalog_fast_variants_by_product', read(ROOT / rel))

    def test_js_sinkron_stok_di_4_jalur(self):
        for p in JS_PATHS:
            if not p.is_file():
                continue
            with self.subTest(path=str(p.relative_to(ROOT))):
                src = read(p)
                self.assertIn('syncListStock', src, 'JS belum versi 17.0.12 (sync stok gudang)')
                self.assertIn('[data-product-group]', src, 'JS belum mendukung markup berkelompok')
                self.assertIn('requestProductList', src)

    def test_js_di_4_jalur_identik(self):
        digests = {}
        for p in JS_PATHS:
            if p.is_file():
                digests[str(p.relative_to(ROOT))] = len(read(p))
        self.assertEqual(len(set(digests.values())), 1, f'ukuran JS berbeda antar jalur: {digests}')


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 19))

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)