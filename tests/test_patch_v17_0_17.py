"""Kontrak test v17.0.17 — tutup lubang fitur password member (POS) + guard input member POS.

Menguji STRUKTUR sumber (bukan runtime). Jalankan:
    python3 -m unittest tests.test_patch_v17_0_17 -v
"""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent

TREES = {
    'legacy': ROOT / 'legacy' / 'public' / 'admin',
    'public': ROOT / 'public' / 'admin',
    'pos': ROOT / 'pos' / 'public' / 'admin',
}

POS_INDEX = {
    'legacy': ROOT / 'legacy' / 'public' / 'admin' / 'pos' / 'index.php',
    'public': ROOT / 'public' / 'admin' / 'pos' / 'index.php',
    'pos': ROOT / 'pos' / 'public' / 'admin' / 'pos' / 'index.php',
}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestCreateMemberPunyaPassword(unittest.TestCase):
    """Tambah Member dari POS (admin/customers/create.php) harus bisa set password marketplace."""

    def test_field_password_ada_di_3_tree(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'customers' / 'create.php')
                self.assertIn('name="marketplace_password"', src,
                              f'{tree}/customers/create.php belum punya field password marketplace')
                self.assertIn('Password Marketplace', src, f'{tree}: label password belum ada')

    def test_insert_menyimpan_hash_dan_registered_at(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'customers' / 'create.php')
                self.assertIn('marketplace_password_hash', src, f'{tree}: INSERT belum menyimpan hash')
                self.assertIn('marketplace_registered_at', src, f'{tree}: registered_at belum di-set')
                self.assertIn('password_hash(', src, f'{tree}: hash belum dihitung')

    def test_password_opsional_tanpa_mengubah_insert_lama(self):
        """Kosongkan field = perilaku lama (11 kolom), jadi member tanpa password tetap bisa dibuat."""
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'customers' / 'create.php')
                self.assertIn("if ($newPassword !== '')", src, f'{tree}: guard password kosong hilang')
                self.assertIn('INSERT INTO customers (name, phone, email, address, member_code, member_level, price_level, points, credit_limit, note, status)',
                              src, f'{tree}: INSERT lama (tanpa password) harus tetap ada')


class TestEditSyncKeSemuaTree(unittest.TestCase):
    """Fitur set/reset password di halaman edit pelanggan harus ada & konsisten di 3 tree."""

    def test_edit_punya_fitur_password_di_3_tree(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'customers' / 'edit.php')
                self.assertIn('marketplace_password_hash', src, f'{tree}: edit.php belum set hash')
                self.assertIn('Password Marketplace', src, f'{tree}: field password belum ada')
                self.assertIn('Password minimal 6 karakter', src, f'{tree}: validasi panjang hilang')

    def test_helper_sesuai_tree(self):
        """legacy pakai legacy_*; public & pos pakai helper non-legacy."""
        for tree, base in TREES.items():
            src = read(base / 'customers' / 'edit.php')
            with self.subTest(tree=tree):
                if tree == 'legacy':
                    self.assertIn('legacy_redirect(', src)
                else:
                    self.assertNotIn('legacy_redirect(', src, f'{tree}: helper legacy_ bikin 500 di tree ini')
                    self.assertNotIn('legacy_base_url(', src, f'{tree}: helper legacy_ bikin 500 di tree ini')


class TestPosIndexGuardInputMember(unittest.TestCase):
    """POS index tidak boleh menelan error daftar member — kasir harus tahu kalau input member gagal."""

    def test_ada_fallback_dan_log_di_3_tree(self):
        for tree, path in POS_INDEX.items():
            with self.subTest(tree=tree):
                src = read(path)
                self.assertIn('$customerLoadError', src, f'{tree}: belum ada penanda error member')
                self.assertIn('error_log(', src, f'{tree}: error tidak di-log')
                self.assertIn('AS price_level', src, f'{tree}: fallback query tanpa price_level belum ada')

    def test_bukan_lagi_catch_bisu(self):
        for tree, path in POS_INDEX.items():
            with self.subTest(tree=tree):
                src = read(path)
                self.assertNotIn("$customers = [];\n} catch (Throwable $e) {", src,
                                 f'{tree}: masih ada pola catch bisu utk daftar member')

    def test_notice_error_ditampilkan(self):
        for tree, path in POS_INDEX.items():
            with self.subTest(tree=tree):
                src = read(path)
                self.assertIn('Gagal memuat daftar member', src, f'{tree}: pesan gagal belum ditampilkan')


class TestVersi(unittest.TestCase):
    """v17.0.17 tetap terpasang minimal setelah rilis berikutnya."""

    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_file(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 17))

    def test_system_upgrade_3_tree(self):
        for rel in ('app/Modules/System/SystemUpgrade.php',
                    'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                src = read(ROOT / rel)
                self.assertGreaterEqual(self._ver(src), (17, 0, 17), f'{rel}: versi turun')


if __name__ == '__main__':
    unittest.main(verbosity=2)