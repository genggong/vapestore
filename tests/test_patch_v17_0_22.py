"""Kontrak test v17.0.22 — Ongkir & penjualan online sampai ke laporan akuntansi.

Dua hal yang dijamin:
 1. Halaman Akuntansi punya KPI "Pendapatan Ongkir" (dan nilainya dipakai di Laba Bersih) — 3 tree.
 2. Jalur pencatatan order online -> transactions aktif (reconcile saat halaman order dibuka,
    INSERT menyertakan shipping_cost, idempotent via pos_recorded_at) — 3 tree.

Jalankan: python3 -m unittest tests.test_patch_v17_0_22 -v
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
MODS = {
    'root': ROOT / 'app' / 'Modules',
    'legacy': ROOT / 'legacy' / 'app' / 'Modules',
    'pos': ROOT / 'pos' / 'app' / 'Modules',
}


def read(p):
    return p.read_text(encoding='utf-8', errors='replace')


class TestKpiOngkirDiAkuntansi(unittest.TestCase):
    def test_kartu_pendapatan_ongkir_ada_3_tree(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'accounting' / 'index.php')
                self.assertIn('Pendapatan Ongkir', src, f'{tree}: kartu ongkir hilang')
                self.assertIn("$summary['shipping_income']", src, f'{tree}: nilai ongkir tidak dipakai')

    def test_export_punya_kolom_ongkir(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'accounting' / 'export.php')
                self.assertRegex(src, r'Ongkir', f'{tree}: kolom ongkir hilang di export')

    def test_module_hitung_ongkir_terpisah_dari_laba_barang(self):
        for tree, mods in MODS.items():
            with self.subTest(tree=tree):
                src = read(mods / 'Accounting' / 'Accounting.php')
                self.assertIn("'shipping_income' => 0.0", src, f'{tree}: metric ongkir hilang')
                self.assertIn('t.shipping_cost', src, f'{tree}: sumber ongkir hilang')
                # laba kotor murni barang; ongkir masuk laba bersih
                self.assertRegex(src, r"gross_profit'\]\s*=\s*\$\w+\['net_sales'\]\s*-\s*\$\w+\['cogs'\]")
                self.assertRegex(src, r"net_profit'\]\s*=\s*\$\w+\['gross_profit'\]\s*\+\s*\$\w+\['shipping_income'\]")


class TestJalurCatatOrderOnline(unittest.TestCase):
    def test_reconcile_saat_halaman_order_dibuka(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'marketplace-orders' / 'index.php')
                self.assertIn('marketplace_record_pos_transaction', src, f'{tree}: reconcile hilang')
                self.assertIn('pos_recorded_at IS NULL', src, f'{tree}: guard idempotent hilang')

    def test_action_tandai_selesai_mencatat(self):
        for tree, base in TREES.items():
            with self.subTest(tree=tree):
                src = read(base / 'marketplace-orders' / 'action.php')
                self.assertIn('marketplace_record_pos_transaction', src, f'{tree}: pencatatan saat selesai hilang')

    def test_insert_transaksi_menyertakan_shipping_cost(self):
        for tree, mods in MODS.items():
            with self.subTest(tree=tree):
                src = read(mods / 'Marketplace' / 'Marketplace.php')
                m = re.search(r'INSERT INTO transactions\s*\(([^)]*)\)', src)
                self.assertIsNotNone(m, f'{tree}: INSERT transaksi online hilang')
                self.assertIn('shipping_cost', m.group(1), f'{tree}: shipping_cost tidak ikut dicatat')
                self.assertIn("pos_recorded_at", src, f'{tree}: penanda idempotent hilang')

    def test_pencatat_dipanggil_dari_kurir_dan_pickup(self):
        for tree, mods in MODS.items():
            for f in ('StoreCourier.php', 'StorePickup.php'):
                with self.subTest(tree=tree, file=f):
                    self.assertIn('marketplace_record_pos_transaction', read(mods / 'Marketplace' / f))


class TestToolBackfill(unittest.TestCase):
    def test_tool_ada_dan_idempotent(self):
        src = read(ROOT / 'tools' / 'backfill_online_ongkir.php')
        self.assertIn('marketplace_record_pos_transaction', src)
        self.assertIn("pos_recorded_at IS NULL", src)
        # langkah 2 hanya menambah (tidak pernah mengurangi ongkir)
        self.assertIn('COALESCE(shipping_cost,0)=0', src)
        self.assertIn('UPDATE transactions SET shipping_cost=? WHERE id=? AND COALESCE(shipping_cost,0)=0', src)


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read(ROOT / 'VERSION').strip()), (17, 0, 22))

    def test_system_upgrade_ikut_version(self):
        version = read(ROOT / 'VERSION').strip()
        self.assertIn(f'v{version}', read(ROOT / 'legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(version, read(ROOT / rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)