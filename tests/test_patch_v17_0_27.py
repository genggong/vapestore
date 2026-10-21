"""Kontrak test v17.0.27 — teks kartu Poin Member jadi putih + cache-buster CSS storefront.

Jalankan: python3 -m unittest tests.test_patch_v17_0_27 -v
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
CSS = [ROOT / 'assets/css/marketplace-theme.css'] + [
    ROOT / t / 'assets/css/marketplace-theme.css' for t in ('legacy/public', 'public', 'pos/public')]
HEADERS = {'legacy': ROOT / 'legacy/public/store/_header.php',
           'public': ROOT / 'public/store/_header.php',
           'pos': ROOT / 'pos/public/store/_header.php'}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestPoinMember(unittest.TestCase):
    def test_label_putih_di_semua_css(self):
        for p in CSS:
            with self.subTest(css=str(p.relative_to(ROOT))):
                src = read(p)
                self.assertIn('.account-points span', src)
                self.assertRegex(src, r'\.account-points span[^{]*\{[^}]*color:#fff!important')
                self.assertRegex(src, r'\.account-points strong[^{]*\{[^}]*color:#fff!important')

    def test_aturan_ditaruh_setelah_latar_gelap(self):
        """Override harus sesudah gradient supaya menang (specificity sama, sama-sama !important)."""
        for p in CSS:
            with self.subTest(css=str(p.relative_to(ROOT))):
                src = read(p)
                latar = src.index('.account-points,.member-points-card{background')
                label = src.index('.account-points span,.member-points-card span{color:#fff')
                self.assertGreater(label, latar, 'aturan putih diletakkan sebelum latar gelap dan akan kalah')

    def test_cache_buster_css_ikut_versi(self):
        for tree, p in HEADERS.items():
            with self.subTest(tree=tree):
                src = read(p)
                self.assertNotIn('storefront.css?v=17.0.20', src, 'versi statis: browser tidak ambil CSS baru')
                self.assertIn("defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION :", src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 27))

    def test_halaman_system_upgrade(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)
