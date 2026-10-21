"""Kontrak test v17.0.26 — pagination katalog /produk.

Jalankan: python3 -m unittest tests.test_patch_v17_0_26 -v
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
STORES = {'legacy': ROOT / 'legacy/public/store', 'public': ROOT / 'public/store', 'pos': ROOT / 'pos/public/store'}
CSS = [ROOT / 'assets/css/storefront.css'] + [ROOT / t / 'assets/css/storefront.css'
                                              for t in ('legacy/public', 'public', 'pos/public')]


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestPagination(unittest.TestCase):
    def test_helper_ada_di_3_tree(self):
        for tree, base in STORES.items():
            with self.subTest(tree=tree):
                src = read(base / '_data.php')
                self.assertIn('function storefront_pagination(', src)
                self.assertIn('function storefront_products_total(', src)
                self.assertIn('SELECT COUNT(*) FROM products p', src)

    def test_halaman_produk_memakai_limit_offset_dan_nomor(self):
        for tree, base in STORES.items():
            with self.subTest(tree=tree):
                src = read(base / 'products.php')
                self.assertIn('storefront_products_total(', src)
                self.assertIn("'offset' => $offset", src)
                self.assertIn('$perPage = 24', src)
                self.assertIn('storefront_pagination($page, $totalPages, $pageQuery)', src)
                self.assertIn('(int)$totalProducts; ?> Produk ditemukan', src, 'judul harus pakai total, bukan count halaman')

    def test_filter_dipertahankan_di_query_link(self):
        for tree, base in STORES.items():
            with self.subTest(tree=tree):
                src = read(base / 'products.php')
                self.assertIn("$pageQuery = ['q' => $q, 'category' => $category", src)

    def test_css_pagination_di_semua_salinan(self):
        for p in CSS:
            with self.subTest(css=str(p.relative_to(ROOT))):
                src = read(p)
                self.assertIn('.catalog-pagination', src)
                self.assertIn('page-btn.active', src)

    def test_helper_tidak_memakai_url_tree_lain(self):
        """Tree root/pos tidak boleh memanggil legacy_base_url (undefined di sana)."""
        for tree in ('public', 'pos'):
            with self.subTest(tree=tree):
                src = read(STORES[tree] / '_data.php')
                m = re.search(r'function storefront_pagination\(.*?\n\}', src, re.S)
                blok = m.group(0)
                self.assertNotIn('legacy_base_url', blok, 'tree root/pos akan fatal')
                self.assertIn('base_url(', blok)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 26))

    def test_halaman_system_upgrade(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)