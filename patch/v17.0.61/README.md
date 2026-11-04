# Patch v17.0.61 — Fix stok produk bervarian tidak kembali saat hapus transaksi

Rilis di repo **vapestore**.

## Isi (4 berkas)
- `admin/transactions/delete.php` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.61

## Masalah
Setelah **menghapus transaksi** yang berisi **produk bervarian**, stok produk varian
tidak kembali (tetap berkurang), padahal stok parent (produk) ikut dikembalikan.

## Akar
Di sistem ini, untuk produk bervarian berlaku invariant: **stok parent == SUM(stok varian)**
(`marketplace_product_variant_stocks`), dijaga via sinkronisasi. Saat penjualan varian,
stok varian dikurangi + parent disinkron.

Namun di `admin/transactions/delete.php`, restore stok hanya membaca `stock_movements`
(yang **tidak menyimpan `variant_id`**) → hanya stok **parent** yang dikembalikan (+qty),
sedangkan baris stok **varian** (`marketplace_product_variant_stocks`) tidak disentuh,
sehingga stok varian tidak pernah kembali.

## Perbaikan
Setelah loop restore parent, tambahkan blok **restore stok varian** yang membaca
`transaction_items.variant_id` (kolom sudah ada sejak skema V16) untuk transaksi tsb:

- Kembalikan `marketplace_product_variant_stocks (variant_id, branch)` naik sebesar qty
  (UPDATE; INSERT bila baris belum ada),
- dalam satu transaksi yang sama dengan hapus transaksi (atomik).

Karena stok varian dikembalikan (+qty) dan stok parent juga dikembalikan (+qty) dari
stock_movements, invariant `parent == SUM(varian)` tetap terjaga — konsisten, tanpa
double-count.

## Cara pasang
```bash
cd ~/public_html
wget -qO p17061.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.61/apply.sh"
bash p17061.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (3 file + VERSION)
2. Unduh `PATCH_v17.0.61.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: jual produk varian (mis. Xlim V3 3ml), lalu hapus transaksinya → cek stok varian & stok parent kembali seperti semula.
