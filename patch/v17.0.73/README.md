# Patch v17.0.73 — Kode unik QRIS marketplace + cache POS

## 1. Kode unik QRIS marketplace (1-99)
Setiap pembayaran QRIS di marketplace kini mendapat kode unik (1-99) yang dijumlahkan ke nominal, sehingga nominal tiap QRIS berbeda dan tidak mudah tertukar.

- Nominal tagihan (payment_amount) = total pesanan + kode unik.
- Kode dijamin tidak bentrok dengan QRIS lain yang masih pending.
- Customer tetap melihat "Total Pembayaran" (sudah termasuk kode unik) di halaman status pesanan.

## 2. Cache POS (buka POS lebih cepat)
POS membuka katalog dengan loop produk → varian yang sebelumnya memicu banyak query `INFORMATION_SCHEMA` dan atribut varian berulang. Kini hasilnya di-cache per request, mempercepat load halaman POS pada katalog besar.

```bash
cd ~/public_html
wget -qO p17073.sh https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.73/apply.sh
bash p17073.sh
```

Setelah selesai: jalankan **Upgrade Otomatis** lalu **Purge LiteSpeed cache**.
