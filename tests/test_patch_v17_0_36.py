"""Kontrak test v17.0.36 — guard helper agar halaman toko tidak 500 saat fungsi belum ada."""
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


class TestGuard(unittest.TestCase):
    def test_checkout_cod_guard(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/checkout.php')
                self.assertIn("function_exists('marketplace_cod_enabled')", s)
                self.assertIn("function_exists('marketplace_store_pickup_close_time')", s)

    def test_order_guard(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                s = read(f'{w}/store/order.php')
                self.assertIn("function_exists('marketplace_store_pickup_deadline_label')", s)
                self.assertIn("function_exists('marketplace_store_pickup_deadline_passed')", s)

    def test_detail_guard(self):
        for t, w in WEB.items():
            with self.subTest(tree=t):
                self.assertIn("function_exists('marketplace_store_pickup_deadline_label')",
                              read(f'{w}/admin/marketplace-orders/detail.php'))


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 36))

    def test_halaman_system_upgrade(self):
        self.assertIn(f"v{read('VERSION').strip()}", read('legacy/public/admin/system-upgrade/index.php'))


if __name__ == '__main__':
    unittest.main(verbosity=2)
