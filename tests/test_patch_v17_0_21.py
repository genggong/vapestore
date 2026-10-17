"""Kontrak test v17.0.21 — hotfix daftar produk "Buat Request Stock" yang terlihat kosong.

Akar masalah: `.request-product-list` = flex-column + max-height + overflow:auto; anaknya
(`.request-product-group`) punya overflow:hidden sehingga min-height:auto = 0 -> dalam flex
container anak itu MENYUSUT sampai ~2px dan isinya terpotong (hanya garis border yang terlihat).

Jalankan: python3 -m unittest tests.test_patch_v17_0_21 -v
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
CSS_DIRS = {
    'root': ROOT / 'assets' / 'css',
    'legacy': ROOT / 'legacy' / 'public' / 'assets' / 'css',
    'public': ROOT / 'public' / 'assets' / 'css',
    'pos': ROOT / 'pos' / 'public' / 'assets' / 'css',
}
TREES = {
    'legacy': ROOT / 'legacy' / 'public' / 'admin',
    'public': ROOT / 'public' / 'admin',
    'pos': ROOT / 'pos' / 'public' / 'admin',
}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestGuardFlexShrink(unittest.TestCase):
    def test_warehouse_css_punya_guard_flex(self):
        for tree, d in CSS_DIRS.items():
            p = d / 'warehouse.css'
            if not p.is_file():
                continue
            with self.subTest(tree=tree):
                src = read(p)
                self.assertIn('.request-product-list>*{flex:0 0 auto}', src,
                              f'{tree}: guard flex:0 0 auto hilang — grup produk bisa menyusut jadi 2px')

    def test_daftar_produk_masih_flex_column_max_height(self):
        """Pastikan kombinasinya memang yang bikin masalah (kalau berubah, guard tetap wajib)."""
        src = read(CSS_DIRS['legacy'] / 'warehouse.css')
        m = re.search(r'\.request-product-list\{([^}]*)\}', src)
        self.assertIsNotNone(m)
        body = m.group(1)
        self.assertIn('display:flex', body)
        self.assertIn('flex-direction:column', body)
        self.assertIn('max-height', body)

    def test_grup_inline_tidak_menyusut(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'create.php')
                m = re.search(r'\.request-product-group\{([^}]*)\}', src)
                self.assertIsNotNone(m, f'{tree}: aturan .request-product-group hilang')
                self.assertIn('flex:0 0 auto', m.group(1), f'{tree}: grup masih bisa menyusut')
                self.assertIn('overflow:hidden', m.group(1), f'{tree}: overflow berubah tanpa alasan')

    def test_markup_grup_masih_utuh(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'create.php')
                self.assertIn('data-product-group', src)
                self.assertIn('request-product-row', src)
                self.assertIn('id="requestProductList"', src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 21))

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)