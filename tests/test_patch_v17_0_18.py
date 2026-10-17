"""Kontrak test v17.0.18 — konstanta APP_ASSET_VERSION harus SELALU terdefinisi.

Kasus nyata: helpers.php versi lama (tanpa `require asset_helpers.php`) + layout/pages versi
baru yang memakai APP_ASSET_VERSION -> halaman admin 500 "Terjadi kesalahan sistem".

Jalankan: python3 -m unittest tests.test_patch_v17_0_18 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent

# Bootstrap aplikasi membuka koneksi DB saat di-require; uji runtime butuh DB lokal.
# Coba DB dump produksi dulu, lalu DB sintetis. Kalau tak ada, uji runtime di-skip (dinyatakan di laporan).
_DB_CANDIDATES = [
    ('vapekong_prod', 'vk_test2', 'VkTest2!Pass2026'),
    ('vapekong_pos_test', 'vk_test2', 'VkTest2!Pass2026'),
]


def _pick_db():
    for name, user, pw in _DB_CANDIDATES:
        php = (f'try{{$p=new PDO("mysql:host=127.0.0.1;dbname={name}","{user}","{pw}");'
               f'$p->query("SELECT 1");echo "OK";}}catch(Throwable $e){{echo "NO";}}')
        r = subprocess.run(['php', '-r', php], capture_output=True, text=True)
        if r.stdout.strip() == 'OK':
            return {'VAPEKONG_DB_HOST': '127.0.0.1', 'VAPEKONG_DB_PORT': '3306',
                    'VAPEKONG_DB_NAME': name, 'VAPEKONG_DB_USER': user, 'VAPEKONG_DB_PASS': pw}
    return None


DB_ENV = _pick_db()

BOOTSTRAPS = {
    'root': ROOT / 'app' / 'bootstrap.php',
    'legacy': ROOT / 'legacy' / 'app' / 'bootstrap.php',
    'pos': ROOT / 'pos' / 'app' / 'bootstrap.php',
}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestGuardKonstantaAset(unittest.TestCase):
    def test_guard_ada_di_3_bootstrap(self):
        for name, p in BOOTSTRAPS.items():
            src = read(p)
            with self.subTest(tree=name):
                self.assertIn("if (!defined('APP_ASSET_VERSION'))", src, f'{name}: guard define hilang')
                self.assertIn("define('APP_ASSET_VERSION'", src, f'{name}: define hilang')
                self.assertIn('vk_asset_version', src, f'{name}: helper versi hilang')

    def test_guard_tidak_bergantung_helpers(self):
        """Definisi harus di bootstrap, bukan hanya di Helpers/ — itu inti perbaikannya."""
        for name, p in BOOTSTRAPS.items():
            src = read(p)
            with self.subTest(tree=name):
                self.assertLess(src.index("if (!defined('APP_ASSET_VERSION'))"),
                                src.index("if (function_exists('maintenance_guard'))"),
                                f'{name}: guard harus sebelum blok debug/maintenance')

    def test_kandidat_path_version_ada(self):
        for name, p in BOOTSTRAPS.items():
            src = read(p)
            with self.subTest(tree=name):
                self.assertIn("'/VERSION'", src, f'{name}: kandidat path VERSION hilang')
                self.assertIn('file_get_contents', src, f'{name}: pembacaan VERSION hilang')

    def test_helpers_3_tree_tetap_require_asset_helpers(self):
        for rel in ('app/Helpers/helpers.php', 'legacy/app/Helpers/helpers.php', 'pos/app/Helpers/helpers.php'):
            with self.subTest(file=rel):
                self.assertIn('asset_helpers.php', read(ROOT / rel), f'{rel}: require asset_helpers hilang')

    def test_asset_helpers_3_tree_ada_dan_guarded(self):
        for rel in ('app/Helpers/asset_helpers.php', 'legacy/app/Helpers/asset_helpers.php', 'pos/app/Helpers/asset_helpers.php'):
            with self.subTest(file=rel):
                src = read(ROOT / rel)
                self.assertIn("if (!defined('APP_ASSET_VERSION'))", src)
                self.assertIn('function aset_url', src)


@unittest.skipUnless(DB_ENV, 'butuh DB lokal (vapekong_prod / vapekong_pos_test) untuk uji runtime bootstrap')
class TestRuntimeKonstantaAset(unittest.TestCase):
    """Uji RUNTIME: require bootstrap langsung, konstanta harus terdefinisi dan bernilai VERSION."""

    def test_bootstrap_define_konstanta(self):
        version = read(ROOT / 'VERSION').strip()
        for name, p in BOOTSTRAPS.items():
            with self.subTest(tree=name):
                php = (f'require "{p}"; '
                       f'echo defined("APP_ASSET_VERSION") ? APP_ASSET_VERSION : "UNDEFINED";')
                r = subprocess.run(['php', '-r', php], capture_output=True, text=True, cwd=str(ROOT),
                                   env=dict(os.environ, **DB_ENV))
                got = r.stdout.strip().splitlines()[-1] if r.stdout.strip() else ''
                self.assertEqual(got, version, f'{name}: konstanta = {got!r} (harus {version!r}); stderr={r.stderr[-300:]}')

    def test_tetap_terdefinisi_saat_helpers_stale(self):
        """Tanpa require asset_helpers di helpers.php (kondisi server user) konstanta tetap ada."""
        import shutil
        import tempfile
        version = read(ROOT / 'VERSION').strip()
        with tempfile.TemporaryDirectory() as td:
            tmp = pathlib.Path(td) / 'tree'
            shutil.copytree(ROOT / 'legacy', tmp / 'legacy', ignore=shutil.ignore_patterns('*.zip'))
            shutil.copyfile(ROOT / 'VERSION', tmp / 'VERSION')
            hp = tmp / 'legacy' / 'app' / 'Helpers' / 'helpers.php'
            hp.write_text(read(hp).replace("require_once __DIR__ . '/asset_helpers.php';", '// stale'))
            (tmp / 'legacy' / 'app' / 'Helpers' / 'asset_helpers.php').unlink()
            php = ('require "' + str(tmp / 'legacy' / 'app' / 'bootstrap.php') + '"; '
                   'echo defined("APP_ASSET_VERSION") ? APP_ASSET_VERSION : "UNDEFINED";')
            r = subprocess.run(['php', '-r', php], capture_output=True, text=True, env=dict(os.environ, **DB_ENV))
            self.assertEqual(r.stdout.strip().splitlines()[-1], version,
                             f'konstanta gagal saat helpers stale: {r.stdout!r} {r.stderr[-300:]}')


class TestVersi(unittest.TestCase):
    def test_version_minimum(self):
        v = read(ROOT / 'VERSION').strip()
        self.assertGreaterEqual(tuple(int(x) for x in re.findall(r'\d+', v)[:3]), (17, 0, 18), f'VERSION turun: {v}')

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)