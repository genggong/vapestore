# 🔄 RESTORE MASTER VAPEKONG v17.0.10 — timpa semua di ~/public_html

Paket ini mengembalikan website ke **file master v17.0.10** (sumber: `app.zip` master lokal, 690 MB — bagian **kode** saja, tanpa media/uploads karena media sudah ada di server).

## Isi
| File | Isi |
|---|---|
| `MASTER_VAPEKONG_v17.0.10_CODE.zip` | Kode master lengkap (2191 file, ~10 MB): `index.php`, `.htaccess`, `app/`, `legacy/`, `system/`, `public/`, `pos/`, `assets/`, `tools/`, `writable/` … |
| `restore_master.sh` | Script bash satu-kali jalan: backup → bersihkan folder nyasar → timpa semua → bersihkan cache → verifikasi |

## Cara pakai (cPanel → Terminal)

```bash
cd ~/public_html
wget -q -O restore_master.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/RESTORE_MASTER_v17.0.10/restore_master.sh"
bash restore_master.sh
```

Lalu **WAJIB**: cPanel → **LiteSpeed Cache Manager → Purge All** (tanpa ini situs masih menyajikan versi lama).

## Yang dilakukan script
1. **Backup** kode saat ini → `~/backup_code_<timestamp>.tar.gz` (media/uploads tidak ikut — sudah aman)
2. **Pindah folder nyasar** `store/`, `admin/` (campur versi legacy/public = penyebab 500) → `~/_backup_stray_<timestamp>/`
3. **Unduh** master v17.0.10 dari repo (public, bisa wget langsung dari hosting)
4. **Timpa semua file** (`unzip -o`)
5. **Bersihkan cache** (`writable/cache`, `legacy/storage/cache`)
6. **Verifikasi** VERSION / index.php / .htaccess / legacy/public / app / system

## Untuk kembali ke kondisi sebelum restore
```bash
cd ~/public_html
tar xzf ~/backup_code_<timestamp>.tar.gz
mv ~/_backup_stray_<timestamp>/store ~/_backup_stray_<timestamp>/admin . 2>/dev/null
```

## Catatan
- Master **tidak** memuat media (`uploads/`) — gambar produk di `legacy/public/uploads` tetap tak tersentuh.
- `.htaccess` master memakai flag `[L]` + fallback `legacy/public` — sudah cocok untuk LiteSpeed shared hosting.
- Verifikasi setelah pasang: `/` (storefront), `/produk`, `/store`, `/kurir/login` harus 200.
