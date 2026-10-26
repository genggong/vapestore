# Patch v17.0.52 — Webhook CasaKu balas 200 untuk transaksi tidak dikenal

Perbaikan atas pesan dashboard Casaku *"Server merespon dengan HTTP 404. Pastikan endpoint
Anda mengembalikan 200 OK."*

## Masalah

Setelah secret terisi, verifikasi webhook Casaku jadi LOLOS signature, tapi dashboard Casaku
masih merah. Sebabnya: Casaku mengirim payload **uji** (transactionId sintetis) yang tidak punya
order asli di `online_orders`, dan webhook lama balas `404 "Pesanan tidak ditemukan."` —
Casaku menolak status itu dan bilang harusnya 200.

## Perbaikan

Untuk request yang **signature-nya sudah lolos** (terbukti asli dari Casaku) tapi txn tidak ada
di DB (test webhook / ping verifikasi endpoint), webhook sekarang tetap balas **200 OK**:

- Sebelum: `if (!$order) ... 404 "Pesanan tidak ditemukan."`
- Sesudah: `if (!$order) ... 200 "Webhook diterima (transaksi tidak dikenal, diabaikan)."`

Aman secara keamanan: 200 hanya diberikan setelah signature HMAC lolos — jadi penyerang tanpa
secret tidak bisa memanfaatkannya. Request asli untuk transaksi yang tidak kita kenal (uji/ping/
event terdahulu) cukup di-ack, retry berhenti, dashboard verifikasi lolos.

## Isi (3 berkas, 3 pohon — webhook CasaKu)

- `public/marketplace-casaku-webhook.php`
- `legacy/public/marketplace-casaku-webhook.php`
- `pos/public/marketplace-casaku-webhook.php`

## Cara apply di hosting — satu baris `wget` + `unzip` dari terminal cPanel

```bash
cd ~/public_html
wget -qO p52.zip "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.52/PATCH_v17.0.52.zip"
unzip -o p52.zip
rm -rf p52.zip
```

- `unzip -o` = jaminan overwrite (File Manager cPanel kadang menyimpan file lama).
- Tidak menyentuh `uploads/`, `.env`, atau kredensial DB.

## Setelah apply

1. **LiteSpeed Cache Manager → Purge All** (wajib, cache lama bisa menutupi hasil).
2. Tekan **lagi "Test Webhook" / simpan endpoint di dashboard Casaku** — harusnya sekarang lolos.
3. Kalau masih tidak 200, kirim baris terbaru log:

```bash
tail -5 ~/public_html/writable/logs/casaku-webhook.log
```

## Catatan

- Hanya 3 file webhook yang berubah. Tidak ada perubahan schema DB — tak perlu Upgrade Otomatis.
- Rollback: re-apply `PATCH_v17.0.51.zip` untuk kembali ke perilaku 404 (bila perlu), atau
  gunakan backup restore terakhir (`~/backup_code_*.tar.gz`).

Zip: `patch/v17.0.52/PATCH_v17.0.52.zip` (3 entri, ~6 KB).
