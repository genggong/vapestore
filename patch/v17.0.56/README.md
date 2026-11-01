# Patch v17.0.56 — Perbaikan Input Operasional (nominal) & Tarif Kurir Toko

Rilis di repo **vapestore**.

## Isi (7 berkas)
- `Operasional` (`admin/operasional/index.php`) di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `Tarif Kurir Toko` (`admin/marketplace-courier-rates/index.php`) di 3 pohon: `public/`, `legacy/public/`, `pos/public/`
- `VERSION` → 17.0.56

## Perubahan
### 1. Input Operasional — nominal tidak bisa disimpan
Field nominal `name="amount"` sebelumnya `step="1000"` → diubah jadi `step="1"`.
Dengan `step="1000"`, angka yang bukan kelipatan 1000 (mis. 125.000) kena validasi
HTML `:invalid`, sehingga tombol Simpan ditolak / nominal seolah "tidak bisa di-input".
Sekarang semua nominal bulat bisa disimpan.

### 2. Tarif Kurir Toko — tombol Edit, Hapus, & pilihan Cabang Pengirim
Mengirim versi final yang utuh (fix sejak v17.0.44/17.0.47) ke live yang tertinggal:
- **Edit** berfungsi (fungsi `fillEdit`/`cancelEdit` di-expose global; variabel `d`
  lama yang menimpa elemen select `rateDistrict` dipecah jadi `p2/c2/d2/s2`).
- **Hapus** per baris tersedia (form `action=delete` + tombol Hapus).
- **Pilihan Cabang Pengirim** (`select[name=branch_id]`) tersedia di form Tambah/Perbarui
  dan ikut terisi saat mode Edit — tarif khusus cabang menang atas tarif umum.

## Cara pasang
```bash
cd ~/public_html
wget -qO p1756.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.56/apply.sh"
bash p1756.sh
```
### apply.sh — yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz` (6 file + VERSION)
2. Unduh `PATCH_v17.0.56.zip`
3. `unzip -t` uji validitas
4. `unzip -o` overwrite (path root-relative benar: public/…, legacy/public/…, pos/public/…)
5. Bersihkan zip

### Setelah apply — manual
1. Backup dulu (database + file).
2. `/admin/system-upgrade/index.php` → Jalankan Upgrade Otomatis.
3. Purge LiteSpeed Cache.
4. Tes: simpan beban operasional nominal bebas (mis. 125000), dan klik Edit/Hapus + pilih Cabang di Tarif Kurir.
