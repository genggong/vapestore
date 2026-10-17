# V17.0.22 — Ongkir & Penjualan Online Sampai ke Laporan Akuntansi

## Yang dilaporkan

Di halaman **Akuntansi** tidak ada kartu **"Pendapatan Ongkir"**. Setelah dicek, ada **dua lapis** sebabnya:

### Lapis 1 — file halaman akuntansi di server tertinggal versi
Repo sudah punya KPI `Pendapatan Ongkir` (`$summary['shipping_income']`) di
`admin/accounting/index.php`, tapi salinan di server belum memuatnya. Paket ini mengirim ulang
halaman akuntansi + modulnya untuk 3 tree.

### Lapis 2 — belum ada satu pun order online yang tercatat ke `transactions` (ini yang utama)
Di data produksi: `transactions.reference_type='online_order'` = **0 baris**, padahal ada order
`paid` + `completed` (WEB-20261014-152303-F345) dengan ongkir Rp 6.000. Akibatnya
`transactions.shipping_cost` **seluruhnya 0** → KPI ongkir akan tetap Rp 0 walau kartunya ada.

Jalur pencatatannya sudah ada di repo (`marketplace_record_pos_transaction()`, dipanggil saat
admin menandai selesai, kurir toko delivered, ambil di toko, **dan** auto-reconcile tiap halaman
`/admin/marketplace-orders/index.php` dibuka) — tetapi salinan **tree `public/` dan `pos/`**
masih generasi lama: `INSERT INTO transactions` di sana belum menyertakan `shipping_cost`, dan
blok auto-reconcile belum ada. Tree `legacy/` (yang disajikan hosting) memilikinya, tapi file di
server ikut tertinggal versi — pola yang sama seperti kasus `helpers.php` dan `accounting/index.php`.

## Perbaikan

1. **Sync 3 tree untuk file jalur pencatatan**: `Modules/Marketplace/{Marketplace,StoreCourier,StorePickup}.php`,
   `admin/marketplace-orders/{index,action}.php` (tree `public/` & `pos/` disalin dari generasi
   terbaru `legacy/`, dengan helper URL disesuaikan: `legacy_redirect→redirect`, `legacy_base_url→base_url`;
   sudah dicek tidak ada fungsi yang hilang saat penimpaan).
2. **Kirim ulang halaman & modul akuntansi** (3 tree) supaya KPI ongkir pasti ada.
3. **`tools/backfill_online_ongkir.php`** — memperbaiki data yang sudah terlanjur:
   - mencatat order `paid` + `completed` yang belum masuk `transactions` (memakai fungsi resmi aplikasi,
     idempotent lewat `online_orders.pos_recorded_at`);
   - mengisi `transactions.shipping_cost` untuk transaksi online yang masih 0 padahal ordernya berongkir
     (**hanya menambah, tidak pernah mengurangi**);
   - mencetak ringkasan sebelum/sesudah.
   Script apply menjalankannya otomatis.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_22.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_22.sh
bash apply_patch_v17_0_22.sh
```

Lalu Purge All (LiteSpeed Web Cache Manager).

## Verifikasi

1. `/admin/accounting/index.php` → kartu **Pendapatan Ongkir** terisi (data contoh: **Rp 6.000**).
2. `/admin/marketplace-orders/index.php` → order lunas + selesai otomatis muncul di tabel transaksi POS
   (buka halaman ini sekali supaya reconcile jalan untuk order lama).
3. `/admin/system-upgrade/index.php` → **v17.0.22**.
4. Order online **baru** ke depan tercatat otomatis (tanpa tool ini).

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.22_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
```

## Hasil uji lokal

- Data produksi: sebelum → 0 transaksi online, ongkir Rp 0; setelah tool dijalankan → **1 transaksi**
  (Rp 98.000, ongkir **Rp 6.000**), ringkasan akuntansi "ongkir di laporan" = **Rp 6.000**. Dijalankan
  dua kali: langkah 2 idempotent (0 kandidat) — tidak pernah mengurangi nilai.
- Render lokal `/admin/accounting/index.php`: kartu `Pendapatan Ongkir = Rp 6.000`, Laba Bersih naik
  (2.917.449) karena ongkir masuk laba bersih, bukan laba kotor barang.
- Port lintas tree: pemeriksaan fungsi memastikan tidak ada fungsi hilang; `php -l` bersih untuk
  seluruh isi paket (30 file).
- `tests/test_patch_v17_0_22.py`: **10/10 OK**; seluruh regresi 17.0.16–17.0.21 tetap hijau.