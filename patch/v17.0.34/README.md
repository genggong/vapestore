# V17.0.34 — COD (Bayar di Tempat) + notifikasi WhatsApp ke Kurir Toko

## 1. COD untuk Ambil di Toko & Kurir Toko

Pelanggan sekarang bisa memilih **Bayar di Tempat (COD)** di checkout — hanya muncul saat
metode pengambilan **Ambil di Toko** atau **Kurir Toko** yang dipilih. Untuk ekspedisi
(JNE/J&T/SiCepat dll.) pilihan COD otomatis disembunyikan dan checkout tetap QRIS/VA.

Alurnya:
1. Pesanan COD dibuat dengan status **Bayar di Tempat** + **Dikonfirmasi** (bukan menunggu
   pembayaran), tanpa instruksi QRIS/VA dan tanpa batas waktu pembayaran.
2. Pelanggan melihat tagihan tunai + kode pengambilan di halaman pesanan.
3. Kurir toko **boleh ditugaskan walau belum lunas** — uang ditagih saat serah terima.
4. Uang diterima → admin klik **"Tandai Lunas (COD)"** di detail pesanan (atau otomatis lunas
   saat "Selesai" pada Ambil di Toko). Poin member ikut diberikan.

Pengaturan: **Marketplace → Pengaturan → Bayar di Tempat (COD)** (Aktif/Nonaktif, default Aktif).

## 2. Notifikasi WhatsApp ke kurir toko saat ditugaskan

Begitu admin menugaskan paket ke kurir toko, kurir langsung menerima WA berisi:

```
*TUGAS KURIR TOKO*
Halo Juanito, ada paket baru untuk diantar.

No: WEB-20261022-031500-AB12
Cabang: Vapekong Mejasem
Penerima: Figo (0895...)
Alamat: Jalan Pala 9 No 121 ...
Ongkir: Rp 6.000
Total pesanan: Rp 98.000

*BAYAR DI TEMPAT (COD)*
Tagih tunai: Rp 98.000 saat serah terima.
Item:
- Salt Nic Foom 30ml - 6mg x1
Buka Portal Kurir untuk menerima tugas & konfirmasi.
```

- Dikirim lewat **Fonnte** (sama seperti notifikasi pesanan), tidak menahan proses simpan.
- Hasil kirim dicatat di timeline pesanan: `courier_notified` / `courier_notify_failed` + alasannya.
- Nomor diambil dari data kurir toko (`marketplace_couriers.phone`).

## Cara pasang

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_34.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_34.sh
bash apply_patch_v17_0_34.sh
```

Lalu **Purge All** + Ctrl+F5.

## Verifikasi lokal (sudah dijalankan)

| uji | hasil |
|---|---|
| `marketplace_cod_allowed()` | store ✅, pickup ✅, jne ❌ |
| Order COD pickup | `unpaid / confirmed`, tanpa expiry, tanpa txn, event `cod_confirmed`, kode ambil terbit |
| Kartu COD di checkout | `payment-method-cod` + `data-cod-enabled="1"` (HTTP 200) |
| Detail admin | label "COD (Bayar di Tempat)" + tombol **Tandai Lunas (COD) — Rp 40.000** |
| Aksi `cod_paid` | `payment_status=paid`, `paid_at` terisi, flash "Pembayaran COD ditandai lunas" |
| Halaman pesanan pelanggan | "Bayar tunai saat ambil di toko" + KODE PENGAMBILAN |
| Kurir toko + COD belum lunas | penugasan **berhasil** (`assigned`) |
| WA kurir | dikirim ke nomor kurir; pada token uji hasil `courier_notify_failed` ("invalid token") — jalur & pencatatan benar |

`tests/test_patch_v17_0_34.py` **9/9 OK**; `php -l` bersih (30 file paket); `node --check` JS OK.