# Patch v17.0.57 — Fix Edit Tarif Kurir Toko (tidak bisa simpan perubahan)

Rilis di repo **vapestore**. Patch lanjutan dari v17.0.56: menyempurnakan mode Edit
agar **Save perubahan tarif kurir berhasil**.

## Isi (4 berkas)
- `admin/marketplace-courier-rates/index.php` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.57

## Masalah
Setelah mode Edit muncul (v17.0.56), klik **Simpan** di mode Edit tidak berhasil
— tidak muncul perubahan tersimpan.

## Akar & Perbaikan
Di fungsi `fillEdit`, saat mengisi kembali dropdown wilayah, select **Provinsi**
di-set dengan value **kosong**:
```js
setOne(p2, data.province, '')   // ← value '' padahal field required
```
Karena select Provinsi bertanda `required` dan value-nya kosong, validasi HTML
browser menolak submit form — makanya perubahan tidak tersimpan.

Perbaikan: isi value Provinsi dengan id-nya dari data baris (`data.province_id`),
sama seperti Kota/Kecamatan/Kelurahan yang sudah memakai `city_id`/`district_id`/`subdistrict_id`:
```js
setOne(p2, data.province, data.province_id)
```
Sekarang mode Edit punya value valid untuk semua level wilayah → **Simpan berfungsi**.

## Cara pasang
```bash
cd ~/public_html
wget -qO p1757.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.57/apply.sh"
bash p1757.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (3 file + VERSION)
2. Unduh `PATCH_v17.0.57.zip`
3. `unzip -t` uji validitas
4. `unzip -o` overwrite (path root-relative benar)
5. Bersihkan zip

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Jalankan Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: Edit satu tarif, ubah ongkir/ETA/status, klik Simpan → pastikan tersimpan.
