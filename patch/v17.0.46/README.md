# Patch v17.0.46 — Validasi area Kurir Toko (fix "Alamat tidak termasuk area layanan Kurir Toko")

## Bug
Notifikasi **"Alamat tidak termasuk area layanan Kurir Toko atau data wilayah berubah. Silakan pilih wilayah kembali."** muncul di checkout padahal kelurahan sudah punya tarif Kurir Toko.

Ada **dua akar masalah** yang digabung di patch ini:

1. **`branch_id` tidak diteruskan** ke validasi → validasi tarif selalu dicari dengan `branch_id=0` (umum), padahal tarif dicatat **per cabang** (mis. Mejasem = branch 1). Tarif khusus cabang tidak ketemu → alamat dianggap di luar area. (commit `d39343d`)

2. **Validasi terpaksa resolve ulang ke API RajaOngkir live** setiap checkout (`marketplace_rajaongkir_resolve_direct_destination`) lalu membandingkan `destination_id`. Kalau API lambat/gagal/berbeda hasil di server produksi → `false` → notif itu muncul. (bagian update ini)

## Perbaikan
- `marketplace_create_order()` memanggil `marketplace_store_courier_validate_destination($payload, $destinationId, $branchId)` — teruskan branch yang terpilih.
- `marketplace_store_courier_validate_destination()` pada mode **subdistrict** kini validasi **langsung cek tarif di DB** (`marketplace_v16_store_courier_rate`), **tanpa** resolve ulang API live. Coverage (same_district/city/province) hanya untuk mode **flat**.

## Berkas (12, 3 pohon)
`app|legacy/app|pos/app/Modules/Marketplace/{Marketplace.php,StoreCourier.php,SystemUpgrade.php}`; VERSION → 17.0.46; halaman System Upgrade; test `tests/test_patch_v17_0_46.py`.
Tanpa DDL — skema tidak berubah.

## Verifikasi
- `marketplace_store_courier_validate_destination(branch 1, kelurahan MEJASEM BARAT bertarif)` → **true** (sebelumnya false).
- Kelurahan tanpa tarif (Slawi/KUBANGAN) → **false** (benar: memang di luar area).
- Checkout penuh `marketplace_create_order` store courier → **SUKSES**.
- `php -l` 0 error di 3 pohon; semua cek kontrak lulus.

## Cara pasang
- Repo private → **jangan pakai wget**; unggah `PATCH_v17.0.46.zip` ke File Manager → Extract → **Purge All** (LiteSpeed) → Ctrl+F5.
- Cek `VERSION` di `public_html` menjadi `17.0.46` setelah extract.
