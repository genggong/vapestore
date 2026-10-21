# V17.0.35 — Ambil di Toko: batas pengambilan maksimal hari ini

## Yang berubah

Pesanan **Ambil di Toko** sekarang punya **batas pengambilan**, default **hari yang sama**
sampai jam tutup toko (diambil dari setting *Jam Pengambilan*, mis. `09:00 - 21:00 WIB` → 21:00).

- Checkout: di bawah pilihan toko muncul *"Ambil **maksimal hari ini** sebelum **21:00**"*.
- Halaman pesanan pelanggan: baris **Batas Ambil** → `Hari ini, maks 21:00`. Kalau sudah lewat:
  tulisan merah **"Lewat batas (…)"** + catatan *"Hubungi toko untuk konfirmasi pengambilan."*
- Detail pesanan admin: baris **Batas ambil** dengan warna merah bila lewat batas.
- Notifikasi WA ke cabang menyertakan **Batas ambil: dd/mm/yyyy hh:mm**.

Batas bisa diubah di **Marketplace → Pengaturan → Batas Pengambilan**:
`Hari ini (maks jam tutup)` (default), `1 hari`, `2 hari`, `3 hari`.

Pesanan yang lewat batas **tidak otomatis dibatalkan** — hanya ditandai, supaya toko bisa
memutuskan (hubungi pembeli / batalkan manual).

## Teknis

- Kolom baru `online_orders.pickup_deadline_at` (additive, idempotent lewat `marketplace_store_pickup_install()`).
- Helper baru: `marketplace_store_pickup_deadline()`, `_close_time()`, `_max_days()`,
  `_deadline_label()`, `_deadline_passed()`.
- Pesanan dibuat jam 22:00 saat toko tutup 21:00 → batas tetap **hari itu 23:59** (tidak mundur ke besok).
- Setting baru `marketplace_store_pickup_max_days` (default `0`).

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_35.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_35.sh
bash apply_patch_v17_0_35.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi lokal (sudah dijalankan)

| uji | hasil |
|---|---|
| Order pickup baru | `pickup_deadline_at = <hari ini> <jam tutup>` |
| Label pesanan | `Hari ini, maks 11:00` (setting uji `11:00 - 11:00`) |
| Batas lewat | `lewat: ya` → tampil merah; `belum: tidak`; order `completed` tidak dianggap lewat |
| `max_days=1` | deadline = besok |
| Checkout | "Ambil maksimal hari ini sebelum 11:00" (HTTP 200) |
| Detail admin | "Batas ambil: Hari ini, maks 11:00" (HTTP 200) |

`tests/test_patch_v17_0_35.py` **7/7 OK**; `php -l` bersih (23 file paket).