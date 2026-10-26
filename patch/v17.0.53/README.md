# Patch v17.0.53 — Kirim QRIS Merchant ID (UUID) sebagai `qr_id`, bukan order ID

Perbaikan atas error dashboard Casaku *"QRIS tidak ditemukan. Daftarkan QRIS statis terlebih
dahulu di Cashify"* saat generate QRIS checkout.

## Masalah

Kode lama mengirim **order ID** sebagai `qr_id` ke `/api/generate/v2/qris`:

```php
'qr_id' => $orderId,
```

Padahal menurut SDK Casaku, `qr_id` = **ID QRIS Merchant (UUID)** yang ada di dashboard
Casaku — beda dengan order ID. Karena backend mencari QRIS merchant dengan ID yang salah,
ia selalu membalas "QRIS tidak ditemukan".

## Perbaikan

1. **Modul `CasaKuPayment.php`**: baca setting baru `marketplace_casaku_qr_id` di
   `marketplace_casaku_config()`, lalu kirim `'qr_id' => $config['qr_id']` (bukan orderId).
   Tambah guard: jika QRIS Merchant ID kosong → error jelas "QRIS Merchant ID belum diatur".
2. **Admin Marketplace Settings**: tambah kolom **"QRIS Merchant ID CasaKu"** di kartu
   CasaKu, ikut tersimpan lewat `$simple` list.

## Isi (6 berkas, 3 pohon)

### Modul CasaKu (3 salinan)
- `app/Modules/Marketplace/CasaKuPayment.php`
- `legacy/app/Modules/Marketplace/CasaKuPayment.php`
- `pos/app/Modules/Marketplace/CasaKuPayment.php`

### Admin Marketplace Settings (3 salinan)
- `legacy/public/admin/marketplace-settings/index.php`
- `public/admin/marketplace-settings/index.php`
- `pos/public/admin/marketplace-settings/index.php`

## Cara apply di hosting — satu baris `wget` + `unzip` dari terminal cPanel

```bash
cd ~/public_html
wget -qO p53.zip "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.53/PATCH_v17.0.53.zip"
unzip -o p53.zip
rm -rf p53.zip
```

- `unzip -o` = jaminan overwrite. Tidak menyentuh `uploads/`, `.env`, kredensial DB.

## Setelah apply — WAJIB diisi di Admin Marketplace Settings

1. **LiteSpeed Cache Manager → Purge All**.
2. Buka **Marketplace Settings → CasaKu (QRIS Otomatis)**.
3. Isi kolom baru **"QRIS Merchant ID CasaKu"** dengan **ID QRIS Merchant (UUID)** dari
   dashboard Casaku (format `986b2a0a-5c95-4177-9800-02d84a35338e`). Simpan.
4. Tes checkout → sekarang harusnya generate QRIS sukses (bukan "QRIS tidak ditemukan").

## Catatan

- Tidak ada perubahan schema DB — tak perlu Upgrade Otomatis.
- Kalau kolom QRIS Merchant ID dikosongkan, generate akan gagal dengan pesan yang jelas
  (bukan error samar) agar admin langsung tahu kolom mana yang diisi.

Zip: `patch/v17.0.53/PATCH_v17.0.53.zip` (6 entri, ~67 KB).
