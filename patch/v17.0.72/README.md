# Patch v17.0.72 — Fix transaksi POS

Memperbaiki error SQL `SQLSTATE[21S01]` saat transaksi produk tanpa varian disimpan: jumlah placeholder INSERT `transaction_items` disamakan dengan jumlah nilai.

Patch tetap mencakup search referral dan Member Cepat POS dari v17.0.71.

```bash
cd ~/public_html
wget -qO p17072.sh https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.72/apply.sh
bash p17072.sh
```
