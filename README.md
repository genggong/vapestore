# Vapestore POS

Sistem POS + Marketplace + Akuntansi (CodeIgniter 4, PHP 8.x, MySQL).

## Versi
- **v17.0.11** — fix penjualan online masuk laporan POS + ongkir terpisah dari gross profit
- CodeIgniter 4.7.4, front-controller CI4 + modul legacy teroptimasi

## Installasi
1. Clone repo: `git clone https://github.com/genggong/vapestore`
2. Siapkan database MySQL, lalu set environment:
   ```bash
   export VAPEKONG_DB_HOST=localhost
   export VAPEKONG_DB_NAME=vapekong_pos
   export VAPEKONG_DB_USER=vapekong_user
   export VAPEKONG_DB_PASS=password_anda
   ```
   (atau edit `app/Config/database.php` / `legacy/app/Config/database.php`)
3. Import skema `database/*.sql` (tidak disertakan data produksi di repo ini)
4. Arahkan web root ke folder `public/` (CI4) — legacy diakses via front-controller
5. Jalankan `composer install` di root bila perlu

## Struktur
- `app/` — aplikasi CodeIgniter 4
- `legacy/` — modul legacy teroptimasi (POS, marketplace, akuntansi, kurir)
- `public/` — web root (index.php front-controller)
- `pos/` — viewer POS
- `database/` — skema & patch SQL
- `tools/` — alat bantu audit (check_sql_binding.py)

## Patch terbaru (v17.0.11)
- **Fix fatal:** penjualan online (marketplace) yang sudah lunas & selesai sekarang
  otomatis dicatat ke tabel `transactions` — muncul di laporan penjualan, akuntansi, & profit.
- **Ongkir terpisah:** `shipping_cost` dicatat di kolom sendiri; laporan akuntansi
  menampilkan "Pendapatan Ongkir" terpisah dari Laba Kotor barang.
- Idempotent: `online_orders.pos_recorded_at` mencegah pencatatan ganda.

## Verifikasi
```bash
bash tests/run_checks.sh   # PHP lint + JS syntax + SQL binding + regression
```

Aplikasi asli: Vapekong POS (CI4) — repo publik ini adalah versi open untuk pengembangan.