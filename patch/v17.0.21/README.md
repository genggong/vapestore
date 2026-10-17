# V17.0.21 — HOTFIX: Daftar Produk "Buat Request Stock" Terlihat Kosong

## Gejala

Halaman **Buat Request Stock** menampilkan panel daftar produk sebagai deretan **garis tipis samar**
tanpa teks, ikon, harga, atau badge — seolah tidak ada produk. Di panel kanan tertulis "Belum ada barang".

## Akar masalah (bug kode, bukan data)

`warehouse.css`:

```css
.request-product-list{margin-top:12px;display:flex;flex-direction:column;gap:8px;max-height:650px;overflow:auto;...}
```

dan markup dari `create.php` membungkus produk dalam grup:

```css
.request-product-group{... overflow:hidden; ...}   /* tanpa flex-shrink */
```

Dalam **flex container berarah kolom yang dibatasi `max-height`**, anak flex punya `min-height:auto`
— kecuali bila anak itu punya `overflow` bukan `visible`, di mana ukuran minimumnya menjadi **0**.
Akibatnya, dengan 751 grup di dalam kotak 650px, tiap grup **menyusut jadi ~2px** (sisa hanya border
atas+bawah), dan karena `overflow:hidden` seluruh isi grup terpotong. Yang tersisa di layar hanya
garis-garis border grup.

Dulu ini tidak terlihat karena markup lama masih daftar datar (tanpa pembungkus grup); bug muncul
sejak daftar dikelompokkan per produk induk.

**Bukti terukur** (halaman yang sama dibuka di browser sungguhan, data produksi):

| | Sebelum | Sesudah |
|---|---|---|
| tinggi grup pertama | **2 px** | **160 px** |
| tinggi anak (heading / baris) | 58 px / 100 px (terpotong) | terlihat |
| grup & baris di DOM | 751 / 936 (ada, tapi tak terlihat) | 751 / 936 terlihat |
| scroll isi daftar | 15.144 px | 150.144 px |

## Perbaikan

1. **`warehouse.css` (4 salinan tree)** — guard eksplisit:
   ```css
   .request-product-list>*{flex:0 0 auto}
   ```
2. **`create.php` (3 tree)** — aturan inline grup diberi `flex:0 0 auto` (jaring pengaman bila
   CSS di server tertinggal versi lama).
3. Versi terlihat di-bump → `VERSION` 17.0.21 + System Upgrade (3 tree) + halaman system-upgrade.

Markup/data tidak diubah sama sekali — murni perbaikan tata letak.

## Cara pasang (cPanel → Terminal)

```bash
cd ~/public_html
wget -qO apply_patch_v17_0_21.sh https://raw.githubusercontent.com/genggong/vapestore/main/tools/apply_patch_v17_0_21.sh
bash apply_patch_v17_0_21.sh
```

Script: backup `.bak` → `unzip -t` → `unzip -o` → verifikasi file + 2 marker guard flex.

Lalu Purge All di **LiteSpeed Web Cache Manager**. CSS di-cache browser: kalau masih kosong, **Ctrl+F5** sekali.

## Verifikasi

1. `/admin/stock-requests/create.php` → daftar produk muncul (grup "PRODUK INDUK" + baris varian,
   badge stok gudang, harga modal, tombol +).
2. Daftar bisa di-scroll dan kartu keranjang di kanan tetap berfungsi (tambah barang → stok gudang berkurang).
3. `/admin/system-upgrade/index.php` → **v17.0.21**.

## Rollback

```bash
cd ~/public_html
for b in $(find backup_17.0.21_* -name '*.bak'); do cp -p "$b" "${b%.bak}"; done
```

## Hasil uji lokal sebelum dikirim

- Dibuka di browser sungguhan (Chromium) dengan data produksi: sebelum perbaikan grup = **2 px**
  (isi terpotong), sesudah perbaikan grup = **160 px** dan 936 baris produk benar-benar terlihat.
- `tests/test_patch_v17_0_21.py`: **6/6 OK** (guard di 4 salinan `warehouse.css`, kombinasi
  flex-column + max-height tetap terdeteksi, `flex:0 0 auto` di 3 tree `create.php`, markup utuh, versi).
- Regresi 17.0.16–17.0.20 tetap hijau; `php -l` bersih untuk seluruh isi paket (12 file).