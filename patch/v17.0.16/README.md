# V17.0.16 — Patch Nilai Modal Stok, Ongkir Akuntansi, Average HPP & Request Stock

## Masalah yang diperbaiki

1. **Nilai modal stok berbeda** antara Laporan Stok, Stock Opname, Export Excel, dan modul Akuntansi
   (KPI *Nilai Persediaan*). Produk ber-varian yang harga induknya 0 dihitung ulang dari stok
   varian × HPP varian → semua laporan kini konsisten.
2. **Akuntansi ongkir belum terpisah** — ongkir penjualan online (`transactions.shipping_cost`)
   kini tampil sebagai **Pendapatan Ongkir** sendiri di dashboard Akuntansi, export Excel/PDF,
   ringkasan per cabang, dan rekap harian (tidak lagi tercampur ke laba barang). Tree
   `public/` (root) & `pos/` disinkronkan ke perilaku yang sudah ada di `legacy/`.
3. **Average pembelian (HPP rata-rata tertimbang)** — pembelian supplier & fulfill request stock
   sekarang memakai `avg_hpp()`: `(stok_lama × hpp_lama + qty × harga_baru) / total stok`,
   dihitung dari stok TARGET (gudang/cabang), bukan menimpa ke harga terakhir.
4. **Display request stock** — daftar produk request stok kini memakai `CatalogFast` (sekali
   query, konsisten dgn POS), stok gudang di list berkurang sesuai keranjang, dan nilai modal
   request mengikuti HPP gudang (bukan harga induk).

## File yang terlibat (3 tree: `legacy/`, `public/` root, `pos/`)

- `app/Modules/Accounting/Accounting.php` — ongkir terpisah + inventory variant-aware
- `public/admin/accounting/index.php` — KPI Pendapatan Ongkir
- `public/admin/accounting/export.php` — kolom Ongkir di Excel/PDF
- `public/admin/purchases/_helpers.php` — helper `avg_hpp()`
- `public/admin/purchases/create.php` — HPP rata-rata tertimbang + CatalogFast
- `public/admin/stock-requests/action.php` — HPP rata-rata tertimbang saat fulfill
- `public/admin/stock-requests/{create,index,detail}.php` — display konsisten
- `public/admin/reports/stock.php` — nilai modal variant-aware
- `app/Modules/CatalogFast.php` — baru untuk root & pos
- `assets/js/stock-request.js` (+ semua jalur) — sinkron stok gudang di keranjang
- `tools/backfill_v17_0_16.php` — verifikasi angka (aman diulang, tanpa DDL/backfill harga)
- Versi visible: `VERSION` → 17.0.16, halaman & modul System Upgrade di-bump

## Cara pasang (Terminal / cPanel)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_16.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_16.sh
bash apply_patch_v17_0_16.sh
```

Script otomatis: backup file lama → `unzip -o` (overwrite pasti) → verifikasi file kunci →
jalankan backfill verifikasi. Lalu buka `/admin/system-upgrade/index.php` dan klik
**Jalankan Upgrade Otomatis** (aman, idempotent).

## Verifikasi setelah pasang

1. Buka **Laporan Stok** → Nilai Modal (Rp)
2. Buka **Akuntansi** → KPI *Nilai Persediaan* (harus sama dengan Laporan Stok)
3. Buka **Akuntansi** → KPI *Pendapatan Ongkir* (kolom terpisah dari Laba Kotor)
4. Buat **Pembelian** harga baru ≠ harga lama → cek Harga Beli produk jadi rata-rata tertimbang
5. Fulfill **Request Stock** → HPP produk cabang ikut rata-rata tertimbang

Catatan: patch TIDAK menulis ulang harga historis (forward-going). Nilai persediaan laporan
dihitung dari data saat ini sehingga langsung konsisten.