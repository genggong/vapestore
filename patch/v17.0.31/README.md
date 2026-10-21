# V17.0.31 — Notifikasi WA pesanan lewat Fonnte + varian tampil di struk & invoice

## 1. Notifikasi pesanan marketplace: **Fonnte saja**

Sesuai permintaan, notifikasi pesanan baru **tidak lagi lewat gateway WhatsApp Marketplace
(Teknorilis)** — sekarang khusus **Fonnte** (token WhatsApp POS).

- Nomor tujuan tetap: setting `marketplace_order_wa_target` → kalau kosong, **nomor WA cabang pengirim**
  (`branches.phone`) → fallback nomor admin/store.
- Kalau token Fonnte belum diisi, baris antrean ditandai `failed` dengan alasan
  "Token Fonnte belum diisi di Pengaturan." — pesanan tetap aman, tidak dibatalkan.
- Kirim ulang kapan saja:
  ```bash
  php tools/send_pending_order_notifications.php            # lihat antrean
  php tools/send_pending_order_notifications.php --send --retry
  ```
- Label di **Pengaturan Marketplace → WhatsApp Marketplace** diperjelas: *Notifikasi Pesanan Masuk
  (Fonnte)*.

## 2. Varian tampil di nota/invoice customer

Sebelumnya beberapa cetakan hanya menampilkan produk induk:

| dokumen | sebelumnya | sekarang |
|---|---|---|
| Struk POS (print) | nama produk saja | **nama produk + "Varian: 6mg"** |
| Invoice WhatsApp (Fonnte) | nama produk saja | `R99 x Hero 57 … - 6mg x1 @ Rp 135.000` |
| Invoice PDF marketplace | sudah ada varian | tetap |
| Halaman pesanan customer | sudah ada varian | tetap |

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_31.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_31.sh
bash apply_patch_v17_0_31.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi

1. **POS → Transaksi → cetak struk** transaksi yang ada variannya → muncul baris **"Varian: …"**.
2. **Kirim invoice WA** dari detail transaksi → item bertuliskan `nama produk - varian`.
3. Buat pesanan uji di website → WA cabang menerima pesan (lewat Fonnte).
4. `/admin/system-upgrade/index.php` → **v17.0.31**.

## Hasil uji lokal

| uji | hasil |
|---|---|
| struk POS transaksi #583 | `R99 x Hero 57 Matcha Pistacio 60ml` + **Varian: 6mg** + Level: retail |
| invoice WA transaksi #583 | `- R99 x Hero 57 Matcha Pistacio 60ml - 6mg x1 @ Rp 135.000 = Rp 135.000` |
| pengirim notifikasi | hanya `wa_send_fonnte()`; tidak ada pemanggilan `marketplace_wa_send_text()` |
| normalisasi nomor cabang | `0831326788499` → `62831326788499` |
| token Fonnte kosong | dispatch `failed` dengan alasan jelas, tidak ada pesan terkirim |

`tests/test_patch_v17_0_31.py` **10/10 OK**; regresi 17.0.29–17.0.30 hijau; `php -l` bersih (17 file paket).