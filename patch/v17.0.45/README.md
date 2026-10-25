# Patch v17.0.45 — Waktu di notifikasi WA pakai jam MySQL (fix bug telat 8 jam)

## Bug
Notifikasi WhatsApp "PESANAN ONLINE BARU" setelah checkout menampilkan **Waktu yang salah** — **8 jam lebih cepat** dari waktu sebenarnya.

**Penyebab:** baris `Waktu: ' . date('d/m/Y H:i')` memakai fungsi PHP `date()`, sedangkan PHP berjalan di zona **UTC**, padahal server database (dan waktu nyata toko) berada di **+08**. Akibatnya notif WA menunjukkan jam 08:xx padahal sudah 16:xx.

```
DB NOW()   = 2026-10-25 16:54   (benar, +08)
PHP date() = 25/10/2026 08:54   (salah, UTC / telat 8 jam)
```

## Perbaikan (v17.0.45)
`marketplace_order_notify_message()` sekarang mengambil jam dari **MySQL** (`SELECT NOW()`), sehingga jam di notif WA = jam DB (+08). Ada fallback ke `date()` bila query DB gagal.

Berkas berubah (3 pohon): `app|legacy/app|pos/app/Modules/Marketplace/Marketplace.php`; VERSION → 17.0.45; SystemUpgrade + halaman System Upgrade.

## Verifikasi
- Probe lokal: notif `Waktu: 25/10/2026 17:00` = DB `NOW()` `17:00:24` ✓ (sebelumnya `08:5x`, telat 8 jam).
- `php -l` 0 error di 3 pohon; semua cek kontrak lulus.

## Catatan
- Baris "Batas ambil" sudah benar (memakai string datetime dari DB).
- Setelah apply: **LiteSpeed Web Cache Manager → Purge All**, lalu Ctrl+F5.
