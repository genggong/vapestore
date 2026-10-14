# Cara Pasang PATCH v17.0.3

1. Backup file website dan database.
2. Upload ZIP patch ke document root yang berisi `index.php`, `app`, `system`, dan `legacy`.
3. Extract dengan Overwrite/Replace.
4. Jangan import ulang dump SQL produksi.
5. Buka `/adm`, login admin.
6. Buka **Sistem -> Upgrade Sistem & Database**.
7. Pastikan bagian **Bridge CI4 & Routing** semua OK.
8. Pastikan **Audit 54 Menu Admin** tidak memiliki target HILANG.
9. Jalankan **Upgrade Otomatis** bila ada schema/index yang kurang.
10. Tes detail produk storefront dan menu admin.
11. Jika browser masih cache, gunakan Incognito atau hapus cache website/browser.
