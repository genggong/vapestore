# Patch v17.0.51 — Log diagnostik webhook CasaKu 403 (secret/signature)

Perbaikan lanjutan dari v17.0.50: webhook `marketplace-casaku-webhook.php` sekarang menulis log ke
`writable/logs/casaku-webhook.log` setiap kali memproses callback. Log itu menunjukkan **kenapa**
webhook ditolak 403 (secret kosong / signature tidak cocok / header tidak sampai) sehingga
penyebabnya tidak perlu ditebak lagi.

## Isi (3 berkas, 3 pohon)

- `public/marketplace-casaku-webhook.php`
- `legacy/public/marketplace-casaku-webhook.php`
- `pos/public/marketplace-casaku-webhook.php`

Masing-masing +19 baris logging diagnostik di awal blok `try` (sebelum verifikasi signature):

```
sig_in=...  secret_set=ya|TIDAK|len=N  body_len=N  headers=[...]  computed=xxxxxxxx (bila keduanya ada)
```

Log ditulis ke `writable/logs/casaku-webhook.log` (relatif `dirname(__DIR__)` — bukan path absolut).
Verifikasi HMAC-SHA256 (`hash_equals`) TIDAK berubah, hanya bertambah log.

## Cara apply di hosting — satu baris `wget` + `unzip` dari terminal cPanel

```bash
cd ~/public_html
wget -qO p51.zip "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.51/PATCH_v17.0.51.zip"
unzip -o p51.zip
rm -rf p51.zip
```

- `unzip -o` = jaminan overwrite (File Manager cPanel kadang menyimpan file lama).
- Tidak menyentuh `uploads/`, `.env`, atau kredensial DB.

## Setelah apply

1. **LiteSpeed Cache Manager → Purge All** (wajib, cache lama bisa menutupi hasil).
2. Pasang Webhook URL di dashboard CasaKu (jika belum).
3. Bukti webhook: kirim callback uji / tunggu transaksi nyata, lalu baca:

```bash
tail -5 ~/public_html/writable/logs/casaku-webhook.log
```

Kirim isi log ke sini — langsung terlihat letak masalahnya (secret tidak cocok / format signature).

## Catatan

- File kode master (`RESTORE_MASTER_v17.0.25`) & `latest.txt` sengaja TIDAK diubah — patch ini
  hanya menimpa 3 file webhook, bukan snapshot penuh. Rollback = `tar` dari backup restore terakhir.
- Tidak ada perubahan schema DB. Tidak perlu Jalankan Upgrade Otomatis.

Zip: `patch/v17.0.51/PATCH_v17.0.51.zip` (3 entri, ~10 KB).
