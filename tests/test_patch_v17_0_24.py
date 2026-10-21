"""Kontrak test v17.0.24 — foto kategori, HPP rata-rata pembelian, request stok tanpa blokir stok gudang.

Jalankan: python3 -m unittest tests.test_patch_v17_0_24 -v
"""
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
TREES = {'legacy': ROOT / 'legacy/public/admin', 'public': ROOT / 'public/admin', 'pos': ROOT / 'pos/public/admin'}
STORES = {'legacy': ROOT / 'legacy/public/store', 'public': ROOT / 'public/store', 'pos': ROOT / 'pos/public/store'}
MODS = {'root': ROOT / 'app/Modules', 'legacy': ROOT / 'legacy/app/Modules', 'pos': ROOT / 'pos/app/Modules'}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestFotoKategori(unittest.TestCase):
    def test_form_upload_di_create_dan_edit(self):
        for tree, base in TREES.items():
            for page in ('create.php', 'edit.php'):
                with self.subTest(tree=tree, page=page):
                    src = read(base / 'categories' / page)
                    self.assertIn('enctype="multipart/form-data"', src)
                    self.assertIn('name="image"', src)
                    self.assertIn('accept="image/jpeg,image/png,image/webp"', src)
                    self.assertIn('upload_category_image(', src)

    def test_simpan_kolom_image(self):
        create = read(TREES['legacy'] / 'categories' / 'create.php')
        self.assertIn('INSERT INTO categories (name, slug, icon, color, image, sort_order, is_active)', create)
        edit = read(TREES['legacy'] / 'categories' / 'edit.php')
        self.assertIn('UPDATE categories SET name=?, slug=?, icon=?, color=?, image=?', edit)
        self.assertIn('remove_image', edit, 'tidak ada opsi hapus foto')

    def test_helper_kategori_3_tree(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                h = base / 'categories' / '_helpers.php'
                self.assertTrue(h.is_file(), f'{tree}: _helpers.php kategori tidak ada')
                src = read(h)
                self.assertIn('function categories_ensure_image_column', src)
                self.assertIn('ADD COLUMN image VARCHAR(255)', src)
                self.assertIn('function category_image_url', src)

    def test_helper_upload_gambar_3_tree(self):
        for rel in ('app', 'legacy/app', 'pos/app'):
            with self.subTest(app=rel):
                src = read(ROOT / rel / 'Helpers' / 'helpers.php')
                self.assertIn('function upload_category_image', src)
                self.assertIn('uploads/categories', src)

    def test_storefront_pakai_foto_kategori(self):
        for tree, base in STORES.items():
            with self.subTest(tree=tree):
                data = read(base / '_data.php')
                self.assertRegex(data, r"function storefront_category_image\(\?string \$categoryName, \?string \$image = null\)")
                self.assertIn("SELECT image FROM categories", data)
                self.assertIn('c.image', data, 'query kategori belum mengambil kolom image')
                idx = read(base / 'index.php')
                self.assertIn("storefront_category_image($cat['name'], $cat['image'] ?? null)", idx)

    def test_thumbnail_di_menu_kategori(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'categories' / 'index.php')
                self.assertIn('category_image_url($cat', src, 'tabel kategori belum menampilkan foto')

    def test_kolom_image_di_schema_doctor(self):
        for tree, mods in MODS.items():
            with self.subTest(tree=tree):
                src = read(mods / 'System' / 'SystemUpgrade.php')
                self.assertIn("'description','image'", src, 'categories.image belum masuk daftar harapan')
                self.assertIn("['categories','image','VARCHAR(255) NULL']", src, 'kolom categories.image tidak diperbaiki upgrader')


class TestHppRataRata(unittest.TestCase):
    def test_hitung_rata_rata_sesuai_rumus(self):
        php = ('require "legacy/public/admin/purchases/_helpers.php";'
               'echo json_encode(['
               ' "stok0"   => avg_hpp(0, 0, 10, 300000),'
               ' "campur"  => avg_hpp(10, 300000, 10, 100000),'
               ' "qty0"    => avg_hpp(5, 1000, 0, 999),'
               ' "tanpaharga" => avg_hpp(7, 0, 3, 50000),'
               ']);')
        r = subprocess.run(['php', '-r', php], capture_output=True, text=True, cwd=str(ROOT))
        self.assertEqual(r.returncode, 0, r.stderr[-300:])
        import json
        d = json.loads(r.stdout.strip())
        self.assertEqual(d['stok0'], 300000)
        self.assertEqual(d['campur'], 200000)
        self.assertEqual(d['qty0'], 1000)
        self.assertEqual(d['tanpaharga'], 50000)

    def test_varian_tidak_menimpa_modal_induk(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'purchases' / 'create.php')
                self.assertIn('V17.0.24: pembelian varian', src, 'guard modal induk belum ada')
                # update purchase_price produk induk harus berada di dalam if ($variantId <= 0)
                m = re.search(r'if \(\$variantId <= 0\) \{(.{0,400}?)UPDATE products SET purchase_price', src, re.S)
                self.assertIsNotNone(m, 'UPDATE modal induk tidak dibungkus guard varian')


class TestRequestStokTidakDiblok(unittest.TestCase):
    def test_tidak_ada_throw_stok_gudang_saat_request(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'create.php')
                self.assertNotIn("throw new Exception('Stok gudang untuk ", src,
                                 'request masih diblokir stok gudang')
                self.assertIn('$shortages[]', src)
                self.assertIn("flash('warning'", src)

    def test_cek_stok_tetap_ada_saat_pemenuhan(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'stock-requests' / 'action.php')
                self.assertIn("Stok gudang untuk ", src, 'validasi stok saat pemenuhan hilang')
                self.assertIn('throw new Exception', src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 24))

    def test_halaman_system_upgrade(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)