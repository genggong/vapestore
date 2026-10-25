# Patch v17.0.44 — Edit Ongkir per Kelurahan

Menambah **Edit** pada tarif kurir toko per kelurahan. Sebelumnya daftar tarif hanya bisa **Tambah** (form baru) dan **Hapus** — untuk mengubah ongkir satu kelurahan harus hapus lalu daftarkan ulang. Sekarang tiap baris punya tombol **Edit** yang mengisi ulang form, dan filter cabang di daftar.

## Yang berubah (8 berkas, 3 pohon)
- `admin/marketplace-courier-rates/index.php` (legacy/public, public, pos/public):
  - Tombol **Edit** per baris tarif → klik mengisi form (cabang, ongkir, ETA, urutan, status) + banner "Mode Edit".
  - Save dengan `id` terisi → `UPDATE ... WHERE id` (region tetap, hanya ongkir/ETA/status/urutan yang berubah). Tanpa `id` → INSERT + ON DUPLICATE (perilaku lama).
  - Dropdown **filter cabang** di atas daftar (`?fb=<id>`), menampilkan tarif per cabang.
  - Tiap baris menyimpan `data-edit` (JSON) untuk prefill.
- `VERSION` → 17.0.44, SystemUpgrade + halaman System Upgrade.

## Cara pakai
1. **Pengaturan Marketplace → Tarif Kurir Toko per Kelurahan**.
2. Klik **Edit** pada baris → form terisi, ubah ongkir/ETA/status → **Simpan Tarif**.
3. Pakai dropdown cabang di atas daftar untuk melihat/mengatur tarif per cabang.

## Catatan
- Edit tidak mengganti wilayah (kelurahan) — untuk pindah kelurahan, hapus lalu tambah baru.
- Tarif khusus cabang tetap menang atas tarif umum (branch_id=0) untuk kelurahan yang sama (v17.0.29).
- Tanpa perubahan DDL — skema `marketplace_store_courier_rates` tidak disentuh.
- Setelah apply: **LiteSpeed Web Cache Manager → Purge All**, lalu Ctrl+F5.

## Verifikasi lokal
- E2E: Edit tarif (fee 6000→7500, ETA "1-2 jam", urutan 5) sukses via POST by id; insert baru masih berfungsi; filter `?fb=` merender daftar; baris uji `ZZ TEST EDIT` dibersihkan.
- `php -l` 0 error di 3 pohon; semua cek kontrak lulus.
