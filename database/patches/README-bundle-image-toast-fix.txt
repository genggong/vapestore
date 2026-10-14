Fix gambar Paket Bundling + SweetAlert Toast

Masalah:
1. Gambar paket broken di POS karena upload bundle disimpan di folder project/uploads,
   sedangkan URL base_url() mengarah dari public.
2. SweetAlert toast ikut jadi modal/backdrop gelap karena wrapper theme global memaksa semua Swal.fire menjadi popup modal.

Perbaikan:
- bundle_upload_image() sekarang menyimpan ke public/uploads/bundles.
- Tambah helper bundle_asset_url() dan bundle_image_exists().
- POS dan halaman bundle memakai helper tersebut.
- sweetalert-theme.js membedakan toast dan modal.
- Toast: backdrop=false, tetap kecil di kanan/atas, tidak menggelapkan layar.
- Modal: tetap memakai tema merah-putih aplikasi.

Catatan:
Untuk gambar paket yang sudah pernah di-upload sebelum fix:
- Edit paket bundling.
- Upload ulang gambarnya sekali.
