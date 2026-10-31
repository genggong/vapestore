# Patch v17.0.54 — Guest checkout → tautkan/buat member (CasaKu tetap utuh)

Rilis v17.0.54 di repo **vapestore** (basis yang ber-CasaKu). Semua di repo vapestore.

## Isi isi
- `Marketplace.php` di 3 pohon: `app/` + `legacy/app/` + `pos/app/Modules/Marketplace/Marketplace.php`
- `VERSION` → 17.0.54
- (Edit tarif kurir sudah ada sejak rilis sebelumnya — tidak diubah di patch ini.)

## Perubahan (item 3-4)

Pada `marketplace_create_order`, untuk checkout tamu (belum login) & nomor HP penerima terisi:
- **Nomor sudah terdaftar member** → transaksi ditautkan ke member (customer_id = id member aktif dgn no itu).
- **Nomor belum terdaftar** → dibuatkan member otomatis:
  - name = nama penerima (fallback "Member Marketplace"), phone = no HP, email bila ada
  - password default `vapekong123` (bcrypt hash)
  - status = active
- **CasaKu TIDAK disentuh** — `require_once CasaKuPayment.php` & alur QRIS tetap ada.

## Cara pasang (`wget` + `bash` di terminal cPanel, dari docroot)

```bash
cd ~/public_html
wget -qO p1754.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.54/apply.sh"
bash p1754.sh
```

### apply.sh — apa yang dikerjakan
1. Backup kode → `~/backup_code_<ts>.tar.gz`
2. Unduh `PATCH_v17.0.54.zip`
3. `unzip -t` uji validitas
4. `unzip -o` overwrite (path root-relative benar: app/... legacy/... pos/... VERSION)
5. Bersihkan zip

### Setelah apply — manual
1. Backup dulu (database + file).
2. Extract/overwrite zip di docroot.
3. `/admin/system-upgrade/index.php` → Jalankan Upgrade Otomatis.
4. Purge LiteSpeed Cache.
5. Tes: checkout tamu dgn nomor member vs nomor baru.

## Berkas zip
`patch/v17.0.54/PATCH_v17.0.54.zip` (~96 KB, 4 entri).
