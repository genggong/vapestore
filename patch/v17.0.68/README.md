# Patch v17.0.68 — Sembunyikan metode pembayaran "QRIS Otomatis (Pakasir)" di POS

Gateway **Pakasir (QRIS otomatis) sudah tidak beroperasi**, sehingga opsi tersebut tidak lagi
ditampilkan di menu pilih metode pembayaran pada halaman kasir (POS) agar tidak membingungkan.

## Perubahan
1. **POS** (`*/admin/pos/index.php`): opsi `<option value="qris_pakasir">QRIS Otomatis (Pakasir)</option>`
   dikomentari (disembunyikan) dari dropdown metode pembayaran, beserta komentar penjelasan.
2. **Kode server & JS terkait tidak dihapus** — dibiarkan utuh sebagai *fallback*; hanya opsi menu yang
   disembunyikan. Metode *Cash*, *Cash + Piutang*, dan *QRIS Manual* (jika aktif) tetap tampil normal.

## Isi (4 berkas)
- `public/admin/pos/index.php` · `legacy/public/admin/pos/index.php` · `pos/public/admin/pos/index.php`
- `VERSION` → 17.0.68

## Cara pasang
```bash
cd ~/public_html
wget -qO p17068.sh "https://raw.githubusercontent.com/genggong/vapestore/main/patch/v17.0.68/apply.sh"
bash p17068.sh
```
### apply.sh — yang dikerjakan
1. Backup → `~/backup_code_<ts>.tar.gz` (3 file POS + VERSION)
2. Unduh `PATCH_v17.0.68.zip` · 3. `unzip -t` uji · 4. `unzip -o` overwrite · 5. bersihkan

### Setelah apply — WAJIB
1. `/admin/system-upgrade/index.php` → **Upgrade Otomatis**
2. **Purge LiteSpeed Cache**
3. Tes: buka halaman kasir (POS) → dropdown metode bayar tidak lagi menampilkan "QRIS Otomatis (Pakasir)".
