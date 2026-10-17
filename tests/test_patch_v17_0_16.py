"""Contract test V17.0.16 — verifikasi struktur source (bukan runtime DB).

Cek:
1. Ongkir terpisah (shipping_income) di Accounting index/export/module, 3 tree.
2. inventory_value variant-aware di Accounting module.
3. avg_hpp dipakai di purchases/create (3 tree).
4. HPP rata-rata tertimbang di stock-requests/action (3 tree).
5. Laporan stok variant-aware (3 tree).
6. Tidak ada legacy_base_url/legacy_redirect tersisa di tree root/pos (harus base_url/redirect).
7. VERSION = 17.0.16 & system-upgrade menunjukkan 17.0.16.
"""
import os
import re
import unittest

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

TREES = {
    'root': '',
    'legacy': 'legacy/',
    'pos': 'pos/',
}


def read(path):
    p = os.path.join(ROOT, path)
    with open(p, encoding='utf-8') as f:
        return f.read()


class TestOngkirTerpisah(unittest.TestCase):
    def test_module_shipping_income(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'app/Modules/Accounting/Accounting.php')
            self.assertIn('shipping_income', src, f'{name}: shipping_income hilang')
            # Dipisah dari gross profit: net_sales = gross - shipping - refund
            self.assertIn("$branch['net_sales'] = $branch['gross_sales'] - $branch['shipping_income'] - $branch['refunds']", src,
                          f'{name}: ongkir tidak dipisah dari net_sales')

    def test_index_kpi_ongkir(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/accounting/index.php')
            self.assertIn('Pendapatan Ongkir', src, f'{name}: KPI ongkir hilang di index')
            self.assertIn("$summary['shipping_income']", src, f'{name}: summary shipping hilang')

    def test_export_ongkir(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/accounting/export.php')
            self.assertIn('Pendapatan Ongkir', src, f'{name}: export ongkir hilang')
            self.assertIn("$branch['shipping_income']", src, f'{name}: export per-cabang ongkir hilang')


class TestInventoryVariantAware(unittest.TestCase):
    def test_inventory_uses_variant_stock(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'app/Modules/Accounting/Accounting.php')
            self.assertIn('marketplace_product_variant_stocks mvs', src, f'{name}: nilai persediaan belum variant-aware')
            # Query ringkas v2: filter harga induk 0 langsung di JOIN, tanpa subquery GROUP BY
            self.assertIn('p.purchase_price <= 0', src, f'{name}: filter harga induk 0 hilang')
            self.assertNotIn('LEFT JOIN (SELECT product_id', src, f'{name}: masih pakai subquery GROUP BY (lambat)')

    def test_no_date_function_range(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'app/Modules/Accounting/Accounting.php')
            # Query transaksi harus pakai range bersih supaya index (status, transaction_date) terpakai
            self.assertIn('DATE_ADD(?, INTERVAL 1 DAY)', src, f'{name}: optimasi range tanggal hilang')


class TestAverageHpp(unittest.TestCase):
    def test_avg_hpp_helper(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/purchases/_helpers.php')
            self.assertIn('function avg_hpp', src, f'{name}: avg_hpp hilang di _helpers')

    def test_avg_hpp_dipakai_purchase(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/purchases/create.php')
            self.assertIn('avg_hpp(', src, f'{name}: purchase belum pakai avg_hpp')

    def test_avg_hpp_weighted_fulfill(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/stock-requests/action.php')
            self.assertRegex(src, r'Rata-rata tertimbang|HPP varian rata-rata|HPP produk rata-rata',
                             f'{name}: fulfill request belum weighted HPP')


class TestLaporanStokVariantAware(unittest.TestCase):
    def test_stock_report_variant(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/reports/stock.php')
            self.assertIn('variant_count', src, f'{name}: laporan stok belum variant-aware')
            self.assertIn('stock_used', src, f'{name}: laporan stok belum pakai stock_used')


class TestCrossTreeKonvensiUrl(unittest.TestCase):
    def test_no_legacy_helper_di_root_pos(self):
        for name in ('root', 'pos'):
            base = '' if name == 'root' else 'pos/'
            for f in [
                'public/admin/purchases/create.php',
                'public/admin/stock-requests/action.php',
                'public/admin/stock-requests/create.php',
                'public/admin/stock-requests/detail.php',
                'public/admin/stock-requests/index.php',
                'public/admin/reports/stock.php',
                'public/admin/accounting/index.php',
                'public/admin/accounting/export.php',
            ]:
                src = read(base + f)
                self.assertNotIn('legacy_base_url', src, f'{name}/{f}: masih legacy_base_url')
                self.assertNotIn('legacy_redirect', src, f'{name}/{f}: masih legacy_redirect')

    def test_legacy_tree_keep_legacy_helper(self):
        src = read('legacy/public/admin/purchases/create.php')
        self.assertIn('legacy_redirect', src, 'legacy tree harus pakai legacy_redirect')


class TestVersi(unittest.TestCase):
    """Versi 17.0.16 tetap terpasang minimal setelah rilis berikutnya (17.0.17 dst)."""

    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_file(self):
        v = read('VERSION').strip()
        self.assertGreaterEqual(self._ver(v), (17, 0, 16), f'VERSION turun ke {v}')

    def test_system_upgrade_visible(self):
        src = read('legacy/public/admin/system-upgrade/index.php')
        version = read('VERSION').strip()
        self.assertIn(f'v{version}', src, 'versi terlihat di halaman system-upgrade belum ikut VERSION')
        # Catatan historis lama tidak boleh ikut di-rewrite
        self.assertIn('v17.0.12 mempertahankan perbaikan pengambilan ID order', src)

    def test_system_upgrade_module_3_tree(self):
        for prefix in ('', 'legacy/', 'pos/'):
            src = read(prefix + 'app/Modules/System/SystemUpgrade.php')
            self.assertIn('idx_transactions_status_date', src, f'{prefix}: index pendukung akuntansi hilang')
            self.assertIn('`status`,`transaction_date`', src, f'{prefix}: definisi index salah')


class TestDashboardModalKonsisten(unittest.TestCase):
    def test_dashboard_stock_value_3_tree(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/dashboard.php')
            self.assertIn('function dashboard_stock_value', src, f'{name}: helper modal variant-aware hilang')
            self.assertIn('$stockValue = dashboard_stock_value($db)', src, f'{name}: dashboard belum pakai helper baru')

    def test_no_legacy_helper_di_root_pos_dashboard(self):
        for prefix in ('', 'pos/'):
            src = read(prefix + 'public/admin/dashboard.php')
            self.assertNotIn('legacy_base_url', src)
            self.assertNotIn('legacy_redirect', src)


class TestQueryAkuntansiDioptimasi(unittest.TestCase):
    def test_date_range_tanpa_fungsi(self):
        for prefix in ('', 'legacy/', 'pos/'):
            src = read(prefix + 'app/Modules/Accounting/Accounting.php')
            # Tidak boleh ada DATE(...) BETWEEN pada transaction_date (menghambat index)
            self.assertNotRegex(src, r"DATE\(t\.transaction_date\) BETWEEN")
            self.assertIn('DATE_ADD(?, INTERVAL 1 DAY)', src, f'{prefix}: optimasi range tanggal hilang')
            # Tidak ada sama sekali pola DATE(...) BETWEEN di WHERE (return/cdp ikut dibersihkan)
            self.assertNotRegex(src, r"DATE\([a-z_]+\) BETWEEN", f'{prefix}: masih ada DATE() BETWEEN')

    def test_semua_date_between_hilang(self):
        for prefix in ('', 'legacy/', 'pos/'):
            src = read(prefix + 'app/Modules/Accounting/Accounting.php')
            self.assertNotIn('DATE(sr.return_date) BETWEEN', src, f'{prefix}: return_date masih DATE() BETWEEN')
            self.assertNotIn('DATE(cdp.created_at) BETWEEN', src, f'{prefix}: cdp.created_at masih DATE() BETWEEN')


class TestGuardSyncStok(unittest.TestCase):
    def test_branch_sync_guarded_3_tree(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/branches/_helpers.php')
            self.assertIn('function branch_sync_all_product_stocks', src, f'{name}: guard sync branch hilang')
            self.assertIn('$have >= $need', src, f'{name}: guard count branch hilang')

    def test_warehouse_sync_guarded_3_tree(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/warehouse/_helpers.php')
            self.assertIn('$haveWs < $needWs', src, f'{name}: guard warehouse hilang')

    def test_dashboard_range_sargable(self):
        for name, prefix in TREES.items():
            src = read(prefix + 'public/admin/dashboard.php')
            self.assertNotIn('DATE(COALESCE(transaction_date, created_at)) = CURDATE()', src, f'{name}: dashboard masih full-scan harian')
            self.assertIn('transaction_date >= CURDATE()', src, f'{name}: range harian hilang')


class TestCatalogFastTersedia(unittest.TestCase):
    def test_catalog_fast_di_root_pos(self):
        for prefix in ('', 'pos/'):
            src = read(prefix + 'app/Modules/CatalogFast.php')
            self.assertIn('function catalog_fast_variants_by_product', src, f'{prefix}: CatalogFast belum ada')
            self.assertIn('function catalog_fast_warehouse_variant_stocks', src, f'{prefix}: CatalogFast warehouse map hilang')


if __name__ == '__main__':
    unittest.main(verbosity=2)