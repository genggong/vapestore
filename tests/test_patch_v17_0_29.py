"""Kontrak test v17.0.29 — tarif kurir toko per cabang + pengiriman multi-cabang + stok agregat.

Jalankan: python3 -m unittest tests.test_patch_v17_0_29 -v
"""
import os
import pathlib
import re
import subprocess
import unittest

ROOT = pathlib.Path(__file__).resolve().parent.parent
WEB = {'legacy': 'legacy/public', 'public': 'public', 'pos': 'pos/public'}
MOD = {'legacy': 'legacy/app', 'public': 'app', 'pos': 'pos/app'}
ENV = dict(os.environ, VAPEKONG_DB_HOST='127.0.0.1', VAPEKONG_DB_PORT='3306',
           VAPEKONG_DB_NAME='vapekong_prod', VAPEKONG_DB_USER='vk_test2', VAPEKONG_DB_PASS='VkTest2!Pass2026')


def read(p):
    return (ROOT / p).read_text(encoding='utf-8', errors='replace')


def sql(q, db='vapekong_prod'):
    r = subprocess.run(['mysql', '-h127.0.0.1', '-uvk_test2', '-pVkTest2!Pass2026', db, '-N', '-B', '-e', q],
                       capture_output=True, text=True)
    return r.stdout.strip()


def php(code):
    r = subprocess.run(['php', '-r', code], capture_output=True, text=True, env=ENV,
                       cwd=str(ROOT / 'legacy/public'))
    assert r.returncode == 0, r.stderr[:400]
    return r.stdout.strip()


class TestKode(unittest.TestCase):
    def test_rate_lookup_terima_branch(self):
        for t in ('legacy', 'public', 'pos'):
            with self.subTest(tree=t):
                s = read(f'{MOD[t]}/Modules/Marketplace/MarketplaceV16.php')
                self.assertIn('int $branchId=0): ?array', s)
                self.assertIn('branch_id IN (', s)
                self.assertIn('ORDER BY branch_id DESC', s)

    def test_migrasi_kolom_dan_index(self):
        s = read('legacy/app/Modules/Marketplace/MarketplaceV16.php')
        self.assertIn("ADD COLUMN branch_id BIGINT NOT NULL DEFAULT 0", s)
        self.assertIn('uniq_mp_store_courier_dest_branch', s)
        self.assertIn('DROP INDEX uniq_mp_store_courier_destination', s)

    def test_order_pilih_cabang_pengirim(self):
        for t in ('legacy', 'public', 'pos'):
            with self.subTest(tree=t):
                s = read(f'{MOD[t]}/Modules/Marketplace/Marketplace.php')
                self.assertIn('function marketplace_delivery_branch(', s)
                self.assertIn('$branchId = marketplace_delivery_branch($db, $rawItems);', s)
                self.assertIn('marketplace_store_courier_option($province,$city,$district,$subdistrict,$destinationId,$branchId)', s)

    def test_stok_katalog_agregat(self):
        for t in ('legacy', 'public', 'pos'):
            with self.subTest(tree=t):
                s = read(f'{WEB[t]}/store/_data.php')
                self.assertIn('SUM(pbs_all.stock)', s)
                self.assertIn('b_all.is_active = 1', s)
                self.assertIn('function storefront_variant_stock_total', s)

    def test_admin_pilih_cabang(self):
        for t in ('legacy', 'public', 'pos'):
            with self.subTest(tree=t):
                s = read(f'{WEB[t]}/admin/marketplace-courier-rates/index.php')
                self.assertIn('name="branch_id"', s)
                self.assertIn('<th>Cabang</th>', s)
                self.assertIn('branch_id,destination_id', s)


class TestRuntime(unittest.TestCase):
    def setUp(self):
        self.dest = sql("SELECT destination_id FROM marketplace_store_courier_rates WHERE destination_id IS NOT NULL LIMIT 1")
        row = sql("SELECT province,city,district,subdistrict FROM marketplace_store_courier_rates WHERE destination_id=%s LIMIT 1" % self.dest).split('\t')
        self.geo = row
        sql("DELETE FROM marketplace_store_courier_rates WHERE branch_id=2 AND destination_id=%s" % self.dest)

    def tearDown(self):
        sql("DELETE FROM marketplace_store_courier_rates WHERE branch_id=2 AND destination_id=%s" % self.dest)

    def test_skema_aktif_di_database(self):
        cols = sql("SHOW COLUMNS FROM marketplace_store_courier_rates LIKE 'branch_id'")
        self.assertIn('branch_id', cols)
        idx = sql("SHOW INDEX FROM marketplace_store_courier_rates WHERE Key_name='uniq_mp_store_courier_dest_branch'")
        self.assertIn('destination_id', idx)
        self.assertEqual(sql("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='vapekong_prod' AND TABLE_NAME='marketplace_store_courier_rates' AND INDEX_NAME='uniq_mp_store_courier_destination'"), '0')

    def test_tarif_cabang_menang_atas_umum(self):
        prov, city, dist, sub = self.geo
        sql("""INSERT INTO marketplace_store_courier_rates(branch_id,destination_id,province,city,district,subdistrict,fee,eta,is_active,sort_order,created_at)
               VALUES(2,%s,'%s','%s','%s','%s',9999,'uji',1,0,NOW())""" % (self.dest, prov, city, dist, sub))
        out = php('require "store/_data.php"; $db=storefront_db();'
                  '$b=marketplace_v16_store_courier_rate($db,%s,"%s","%s","%s","%s",2);'
                  '$a=marketplace_v16_store_courier_rate($db,%s,"%s","%s","%s","%s",1);'
                  'echo json_encode([(float)$b["fee"],(int)$b["branch_id"],(float)$a["fee"],(int)$a["branch_id"]]);'
                  % (self.dest, prov, city, dist, sub, self.dest, prov, city, dist, sub))
        global_fee = float(sql("SELECT fee FROM marketplace_store_courier_rates WHERE branch_id=0 AND destination_id=%s ORDER BY sort_order,id DESC LIMIT 1" % self.dest) or 0)
        self.assertEqual(out.replace(' ', ''), f'[9999,2,{int(global_fee) if global_fee == int(global_fee) else global_fee},0]')

    def test_stok_katalog_jumlah_semua_cabang(self):
        pid = sql("SELECT p.id FROM products p WHERE p.is_active=1 AND NOT EXISTS(SELECT 1 FROM marketplace_product_variants v WHERE v.product_id=p.id AND v.is_active=1) ORDER BY p.id LIMIT 1")
        sql("INSERT IGNORE INTO product_branch_stocks(branch_id,product_id,stock,minimum_stock,created_at) VALUES(2,%s,0,0,NOW())" % pid)
        old = sql("SELECT stock FROM product_branch_stocks WHERE branch_id=2 AND product_id=%s" % pid)
        sql("UPDATE product_branch_stocks SET stock=11 WHERE branch_id=2 AND product_id=%s" % pid)
        try:
            b1 = int(sql("SELECT COALESCE(stock,0) FROM product_branch_stocks WHERE branch_id=1 AND product_id=%s" % pid) or 0)
            out = php('require "store/_data.php"; $p=storefront_product(%s); echo (int)$p["stock_total"];' % pid)
            self.assertEqual(int(out), b1 + 11, f'produk {pid}: stok detail harus {b1}+11')
        finally:
            sql("UPDATE product_branch_stocks SET stock=%s WHERE branch_id=2 AND product_id=%s" % (old or 0, pid))

    def test_cabang_pengirim_otomatis(self):
        pid = sql("SELECT p.id FROM products p WHERE p.is_active=1 AND NOT EXISTS(SELECT 1 FROM marketplace_product_variants v WHERE v.product_id=p.id AND v.is_active=1) ORDER BY p.id LIMIT 1")
        sql("INSERT IGNORE INTO product_branch_stocks(branch_id,product_id,stock,minimum_stock,created_at) VALUES(2,%s,0,0,NOW())" % pid)
        old1 = sql("SELECT stock FROM product_branch_stocks WHERE branch_id=1 AND product_id=%s" % pid) or 0
        old2 = sql("SELECT stock FROM product_branch_stocks WHERE branch_id=2 AND product_id=%s" % pid) or 0
        sql("UPDATE product_branch_stocks SET stock=0 WHERE branch_id=1 AND product_id=%s" % pid)
        sql("UPDATE product_branch_stocks SET stock=4 WHERE branch_id=2 AND product_id=%s" % pid)
        try:
            out = php('require "store/_data.php"; echo marketplace_delivery_branch(storefront_db(), [["id"=>%s,"qty"=>3]]);' % pid)
            self.assertEqual(out, '2', 'harus memilih cabang 2 karena hanya cabang 2 punya stok')
        finally:
            sql("UPDATE product_branch_stocks SET stock=%s WHERE branch_id=1 AND product_id=%s" % (old1, pid))
            sql("UPDATE product_branch_stocks SET stock=%s WHERE branch_id=2 AND product_id=%s" % (old2, pid))


class TestVersi(unittest.TestCase):
    @staticmethod
    def _ver(text):
        return tuple(int(x) for x in re.findall(r'\d+', text)[:3])

    def test_version_minimum(self):
        self.assertGreaterEqual(self._ver(read('VERSION').strip()), (17, 0, 29))

    def test_halaman_system_upgrade(self):
        v = read('VERSION').strip()
        self.assertIn(f'v{v}', read('legacy/public/admin/system-upgrade/index.php'))
        for rel in ('app/Modules/System/SystemUpgrade.php', 'legacy/app/Modules/System/SystemUpgrade.php',
                    'pos/app/Modules/System/SystemUpgrade.php'):
            with self.subTest(file=rel):
                self.assertIn(v, read(rel))


if __name__ == '__main__':
    unittest.main(verbosity=2)
