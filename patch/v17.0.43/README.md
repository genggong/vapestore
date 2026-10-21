# Patch v17.0.43 — Stok Opname Gudang

Menu baru **Gudang → Opname Gudang**: stock opname berbasis **stok gudang** (`warehouse_stocks`), terpisah dari opname cabang yang sudah ada.

## Kenapa dipisah
- Opname lama menghitung stok **cabang aktif** (`product_branch_stocks` / stok varian per cabang).
- Opname gudang menghitung stok **gudang utama** (`warehouse_stocks`), sumber yang dipakai halaman Edit Stok dan nilai modal stok.
- Keduanya tidak saling menimpa: finalisasi opname gudang **tidak** mengubah `products.stock` maupun stok cabang.

## Isi patch (30 berkas)
- `admin/stock-opname-gudang/` (7 berkas) di 3 pohon (`legacy/public`, `public`, `pos/public`):
  - `index.php` — ringkasan + riwayat sesi (filter draft/final).
  - `create.php` — buat sesi; default **hanya produk stok > 0** (opsi sertakan stok 0).
  - `edit.php` — isi stok fisik, selisih dihitung otomatis (fisik − sistem), pencarian cepat, simpan draft.
  - `template.php` — unduh **template Excel** opname gudang, stok 0 otomatis dilewati (`?include_zero=1` untuk semua).
  - `import.php` — unggah Excel hasil template; baris tanpa stok fisik dilewati, produk tak cocok dilaporkan.
  - `finalize.php` — tulis penyesuaian ke `warehouse_stocks` + `stock_movements` (`reference_type='warehouse_opname'`), hanya sekali (guard `status='draft'`).
  - `_helpers.php` — tabel `warehouse_opnames` + `warehouse_opname_items` (dibuat otomatis), seed, total.
- Sidebar **Gudang** ditambah menu **Opname Gudang** (3 pohon).
- `assets/css/stock-opname.css` (4 salinan) ditambah kelas form/input/chip.
- `VERSION` → `17.0.43`, SystemUpgrade + halaman System Upgrade.

## Alur pakai
1. **Gudang → Opname Gudang → Buat Opname** (atau **Template Excel** → isi kolom `physical_stock` → **Upload Excel**).
2. Isi stok fisik; kosongkan baris yang belum dihitung.
3. **Simpan Draft** — stok belum berubah.
4. **Finalisasi** — stok gudang disesuaikan, selisih tercatat di kartu stok.

## Template Excel
Kolom: `name | category | unit | system_stock | physical_stock | purchase_price | total_purchase_price | note | product_id | product_code`.
- Hanya kolom `physical_stock` yang diisi.
- Produk **stok 0 tidak masuk** (sesuai permintaan).
- Kolom `product_id`/`product_code` (I–J, disembunyikan) dipakai untuk pencocokan; jangan dihapus.

## Catatan
- Tabel dibuat otomatis saat halaman pertama dibuka — tidak perlu SQL manual.
- Stok gudang 0 pada produk yang dihitung akan tetap dibuatkan baris `warehouse_stocks` bila belum ada.
- Nilai modal stok (accounting) memakai basis cabang; finalisasi gudang tidak mengubah angka tersebut.
- Setelah apply: **LiteSpeed Web Cache Manager → Purge All**, lalu Ctrl+F5.

## Verifikasi lokal
- E2E: sesi dibuat → 3 item → simpan (+3, −2, 0) → finalisasi → stok gudang 10→13 dan 20→18, 2 baris `stock_movements` tercatat.
- Template: 3 baris (hanya stok > 0), 0 baris stok ≤ 0.
- Import: 2 baris Excel → 1 masuk (stok fisik terisi), 1 dilewati (kosong).
- `php -l` 0 error pada semua berkas modul di 3 pohon; `tests/test_patch_v17_0_43.py` semua cek lulus.
