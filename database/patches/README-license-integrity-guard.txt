License Integrity Guard

Perubahan:
- Tambah app/Licenses/.license-integrity.php
- Bootstrap memanggil integrity checker sebelum runtime lisensi.
- Jika file inti lisensi hilang atau berubah, script langsung berhenti dengan License Core Error.

File yang dikunci:
- app/Licenses/.license-runtime.php
- public/license-setup.php
- public/license-required.php
- public/assets/css/license-client.css

Catatan jujur:
- Jika orang punya akses penuh ke source dan sengaja mengedit bootstrap.php, proteksi PHP apa pun masih bisa dibypass.
- Guard ini membuat penghapusan/perubahan file lisensi inti tidak bisa diam-diam; aplikasi akan error.
