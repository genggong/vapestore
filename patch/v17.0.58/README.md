# Patch v17.0.58 — Fix Edit Tarif Kurir: "Wilayah terpilih belum dapat dipetakan ke ID ongkir"

Rilis di repo **vapestore**. Melanjutkan v17.0.57.

## Isi (4 berkas)
- `admin/marketplace-courier-rates/index.php` di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.58

## Masalah
Setelah v17.0.57, Edit bisa dibuka & disimpan tapi muncul error:
> Wilayah terpilih belum dapat dipetakan ke ID ongkir. Coba pilih kelurahan/desa yang lebih spesifik.

## Akar & Perbaikan
Di handler simpan, resolusi wilayah ke ID ongkir (`marketplace_rajaongkir_resolve_direct_destination`)
dijalankan **di semua simpan**, termasuk mode Edit. Padahal saat Edit, wilayah TIDAK diubah —
yang berubah hanya ongkir/ETA/status/urutan/cabang. Nama kelurahan yang tersimpan di DB sering
tidak persis sama dengan nama di API RajaOngkir, sehingga resolve gagal → simpan edit dibatalkan.

Perbaikan: resolusi wilayah + validasi "pilih sampai Kelurahan/Desa" **hanya dijalankan saat INSERT**
(membuat tarif baru). Mode Edit (id > 0) langsung UPDATE ongkir/ETA/status/urutan/cabang tanpa
resolve wilayah. Sekaligus dihapus cek `rowCount()===0` yang bisa salah mengirim "Tarif tidak
ditemukan" ketika nilai tidak berubah (MySQL lapor 0 baris berubah).

## Cara pasang
```bash
cd ~/public_html
wget -qO p1758.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.58/apply.sh"
bash p1758.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (3 file + VERSION)
2. Unduh `PATCH_v17.0.58.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — manual
1. Backup dulu.
2. `/admin/system-upgrade/index.php` → Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: Edit satu tarif → ubah ongkir/ETA/status → Simpan (harus berhasil, tanpa error wilayah).
