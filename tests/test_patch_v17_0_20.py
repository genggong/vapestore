"""Kontrak test v17.0.20 — cache-bust CSS storefront (agar palet baru sampai ke semua pengunjung).

Jalankan: python3 -m unittest tests.test_patch_v17_0_20 -v
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
HEADERS = {
    'legacy': ROOT / 'legacy' / 'public' / 'store' / '_header.php',
    'public': ROOT / 'public' / 'store' / '_header.php',
    'pos': ROOT / 'pos' / 'public' / 'store' / '_header.php',
}
MIN_VERSION = (17, 0, 20)


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


def ver_tuple(text):
    return tuple(int(x) for x in re.findall(r'\d+', text)[:3])


class TestCacheBustStorefront(unittest.TestCase):
    def test_css_storefront_pakai_versi_rilis(self):
        for tree, p in HEADERS.items():
            src = read(p)
            with self.subTest(tree=tree):
                for css in ('storefront.css', 'marketplace-theme.css'):
                    m = re.search(re.escape(css) + r'\?v=([0-9.]+)', src)
                    self.assertIsNotNone(m, f'{tree}/{css}: query versi tidak ditemukan')
                    self.assertGreaterEqual(ver_tuple(m.group(1)), MIN_VERSION,
                                            f'{tree}/{css}: versi cache-bust masih {m.group(1)}')

    def test_tidak_ada_versi_lama_tertinggal(self):
        for tree, p in HEADERS.items():
            src = read(p)
            with self.subTest(tree=tree):
                for old in ('15.14.0', '15.8.0'):
                    self.assertNotIn(old, src, f'{tree}: versi lama {old} masih ada')

    def test_header_storefront_tetap_memuat_dua_css(self):
        for tree, p in HEADERS.items():
            with self.subTest(tree=tree):
                src = read(p)
                self.assertIn('assets/css/storefront.css', src)
                self.assertIn('assets/css/marketplace-theme.css', src)


class TestFileCssSudahMonokrom(unittest.TestCase):
    """Patch ini mengasumsikan palet v17.0.19 sudah terpasang (urutan apply 19 lalu 20)."""

    def test_css_hitam_putih(self):
        css = read(ROOT / 'legacy' / 'public' / 'assets' / 'css' / 'storefront.css')
        self.assertIn('--purple:#111111', css)
        self.assertNotIn('#7c3aed', css.lower())


class TestVersi(unittest.TestCase):
    def test_version_minimum(self):
        self.assertGreaterEqual(ver_tuple(read(ROOT / 'VERSION').strip()), MIN_VERSION)

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)