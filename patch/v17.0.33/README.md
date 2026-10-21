# V17.0.33 — Nolkan stok satu cabang + switcher cabang global (POS & laporan ikut)

## 1. Nolkan stok cabang (mis. Vapekong Slawi)

Tool baru `tools/reset_branch_stock.php` — **dry-run default**, menulis hanya dengan `--apply`.

```bash
cd ~/public_html
php tools/reset_branch_stock.php --branch-name=Slawi            # lihat dampaknya dulu
php tools/reset_branch_stock.php --branch-name=Slawi --apply    # nolkan
```

Yang dinolkan: `product_branch_stocks.stock` + `marketplace_product_variant_stocks.stock`
untuk cabang itu saja. Yang **tidak** disentuh: harga modal, minimum stok, stok gudang pusat,
riwayat transaksi, pesanan lama. Sebelum menulis, baris terdampak disalin ke
`product_branch_stocks_bak_<timestamp>` dan `marketplace_product_variant_stocks_bak_<timestamp>`,
dan setiap perubahan dicatat di `stock_movements` (`reference_type=reset_branch_stock`).
Cabang utama ditolak kecuali pakai `--force`.

**Penting:** sebelumnya stok cabang bisa "hidup lagi" karena POS menyalin stok master ke cabang
aktif setiap halaman dibuka (`pos_theme_sync_latest_master_to_branch`). Sekarang penyalinan itu
**hanya untuk cabang utama**, jadi cabang yang sudah dinolkan tetap nol.

## 2. Ganti cabang untuk admin/owner — berlaku ke semua fitur

- Selector **Cabang aktif** ada di sidebar (atas menu), muncul untuk `super_admin`/`owner`.
- Endpoint baru `admin/switch-branch.php` menyimpan `$_SESSION['active_branch_id']` lalu kembali
  ke halaman yang sama — jadi berlaku untuk: **POS Kasir, transaksi, stok, gudang, laporan,
  marketplace cabang**, tidak perlu pindah menu.
- Redirect dibatasi hanya path internal (tidak bisa dipakai untuk open redirect).
- Shift kasir kini ikut cabang: pindah cabang tidak lagi menampilkan shift cabang lain.

## 3. Stok POS benar per cabang

`pos_theme_stock_expr()` sekarang `COALESCE(pbs.stock, p.stock)` — stok cabang aktif yang menang,
stok master hanya dipakai kalau baris cabang belum ada. Sebelumnya, kalau stok master lebih besar
dan `updated_at` master lebih baru, POS menampilkan stok master di **semua** cabang.

## Pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_33.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_33.sh
bash apply_patch_v17_0_33.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi lokal (sudah dijalankan)

| uji | hasil |
|---|---|
| `--branch-name=Slawi` tanpa `--apply` | DRY-RUN, stok tidak berubah |
| `--apply` pada cabang 2 | produk & varian cabang 2 → 0; movement `adjustment -7` tercatat; backup 4.154 + 2 baris |
| cabang 1 (Mejasem) | stok tetap (4.579 unit) — tidak tersentuh |
| `--branch=1` tanpa `--force` | ditolak (exit ≠ 0) |
| switch ke Slawi | POS: "Cabang aktif: Vapekong Slawi"; transaksi: opsi cabang 2 terpilih |
| stok 4228 di POS | Mejasem 18, Slawi **0** (master 99 tidak bocor) |
| switch balik ke Mejasem | POS kembali "Vapekong Mejasem" |

`tests/test_patch_v17_0_33.py` **13/13 OK**; `php -l` bersih (22 file paket).