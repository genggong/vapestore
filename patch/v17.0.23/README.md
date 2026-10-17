# V17.0.23 — Nilai Modal Dashboard = Akuntansi · Menu Gudang > Edit Stok · Reset Stok Gudang

## 1. Nilai modal stok Dashboard disamakan dengan Akuntansi

Sebelumnya dua perhitungan berbeda:

| | Sebelum | Sekarang |
|---|---|---|
| Dashboard (`dashboard_stock_value()`) | basis **stok master** `products.stock` | memakai fungsi bersama **basis per cabang** `product_branch_stocks` |
| Akuntansi (KPI *Nilai Persediaan*) | basis per cabang + varian | tidak berubah |

Ditambah fungsi bersama **`accounting_inventory_value(PDO $db, int $branchId = 0)`** di modul
`Accounting.php` (3 tree) yang dipakai Dashboard; aturannya sama seperti KPI Akuntansi:
produk harga beli > 0 → `stok cabang × harga beli`, dan produk ber-varian yang harga induknya 0 →
`stok varian cabang × HPP varian`. Bila modul akuntansi tidak tersedia, Dashboard punya fallback.

**Bukti (data produksi, seluruh cabang):** fungsi bersama = **Rp 61.939.048**,
kartu Dashboard = **Rp 61.939.048**, KPI Akuntansi = **Rp 61.939.048** — sama dengan angka Excel.

## 2. Menu Gudang → **Edit Stok**

Halaman baru: `admin/warehouse/edit-stock.php` (3 tree) + menu di sidebar tepat di bawah **Gudang**.

Yang bisa diubah per produk **dan per varian**:

| Kolom | Tabel yang diubah |
|---|---|
| **Stok Gudang** | `warehouse_stocks.stock` / `warehouse_variant_stocks.stock` |
| **Min Gudang** | `warehouse_stocks.minimum_stock` / `warehouse_variant_stocks.minimum_stock` |
| **Modal Gudang** (harga beli di gudang) | `warehouse_stocks.purchase_price` / `warehouse_variant_stocks.purchase_price` |
| **Stok Cabang** (cabang utama, yang dipakai POS & laporan) | `product_branch_stocks.stock` (stok master `products.stock` ikut disesuaikan), varian: `marketplace_product_variant_stocks` + sinkron stok induk |
| **Modal Master** (dipakai perhitungan nilai modal) | `products.purchase_price` / `marketplace_product_variants.purchase_price` |

Pengaman: hanya untuk **Super Admin/Owner** (`warehouse_require_manage()`), CSRF wajib, semua
perubahan dalam **satu transaksi** (gagal = rollback), hanya baris yang **diisi** yang diproses
(kosong = tidak diubah), nilai stok tidak boleh negatif, setiap perubahan stok dicatat ke
`warehouse_stock_movements` dan `stock_movements`, plus `activity_logs` lewat `Auth::log`.
Halaman juga menampilkan **12 pergerakan terakhir** gudang tersebut.

## 3. Tool reset stok gudang (untuk pembelian uji coba)

`tools/reset_warehouse_stock.php` — menolkan **stok gudang** tanpa menyentuh stok cabang, laporan,
atau harga modal:

```bash
cd ~/public_html
wget -qO tools/reset_warehouse_stock.php https://raw.githubusercontent.com/genggong/vapestore/main/tools/reset_warehouse_stock.php
php tools/reset_warehouse_stock.php            # DRY RUN: hanya menampilkan yang akan diubah
php tools/reset_warehouse_stock.php --apply    # benar-benar menolkan stok gudang
```

- Tanpa `--apply` **tidak ada** yang berubah.
- Sebelum menulis: nilai lama disalin ke `warehouse_stocks_backup_<ts>` dan
  `warehouse_variant_stocks_backup_<ts>` (bisa dipakai untuk rollback — perintahnya dicetak di akhir).
- Setiap baris bersisa dicatat sebagai pergerakan `reset_stock` di riwayat gudang.
- Aman diulang (yang sudah 0 dilewati).
- Opsi `--include-branch` bila ingin sekalian menolkan **stok cabang + stok master** (mempengaruhi
  POS & laporan), `--note="..."` untuk catatan kustom.

## Cara pasang patch (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_23.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_23.sh
bash apply_patch_v17_0_23.sh
```

Lalu Purge All (LiteSpeed Web Cache Manager).

## Verifikasi

1. `/admin/dashboard.php` → **Nilai Modal Stok** sama dengan `/admin/accounting/index.php` → **Nilai Persediaan**.
2. Menu **Gudang → Edit Stok**: isi satu kolom pada satu produk → Simpan → cek nilai berubah dan
   muncul di **Riwayat Perubahan Gudang**.
3. `/admin/system-upgrade/index.php` → **v17.0.23**.

## Hasil uji lokal

- Uji end-to-end halaman (curl + browser, data produksi): isi Stok Gudang 7, Min 3, Modal Gudang 150.000,
  Stok Cabang 9, Modal Master 175.000 → tersimpan benar (`warehouse_stocks` 7/3/150000,
  `products` stok 9 + harga 175000, `product_branch_stocks` 9) dan 2 pergerakan tercatat.
  Uji varian: gudang 4/2/99.000, cabang 5, modal varian 88.000 → benar.
- Dua bug ditemukan & diperbaiki saat uji: (a) form mengirim semua baris kosong sehingga ikut diproses,
  (b) update harga modal sempat menimpa balik stok master dengan nilai lama.
- Reset gudang: dry-run tidak mengubah apa pun; `--apply` menolkan stok gudang, membuat 2 tabel backup,
  mencatat pergerakan; stok cabang (4.579) tidak tersentuh; dijalankan dua kali → idempotent.
- `tests/test_patch_v17_0_23.py`: **10/10 OK** (termasuk uji runtime: nilai dashboard == nilai akuntansi).
  Regresi 17.0.16–17.0.22 tetap hijau. `php -l` bersih untuk seluruh isi paket (18 file).