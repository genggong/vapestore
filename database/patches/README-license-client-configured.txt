License Client Configured

Server license-api sudah kamu upload di hosting lain, jadi script POS ini hanya menjadi client.

Konfigurasi client:
- Product code: TEKNO_PPOB
- API secret: TEKNO_RILIS_PPOB_2026_03_11
- Runtime: app/Licenses/.license-runtime.php
- Runtime disamarkan/base64.
- Halaman aktivasi: public/license-setup.php
- Halaman blokir: public/license-required.php

Cara pakai:
1. Pastikan URL license-api di hosting lain bisa diakses.
2. Pastikan server license-api tetap memakai api_secret:
   TEKNO_RILIS_PPOB_2026_03_11
3. Pastikan license key di panel server memakai product_code:
   TEKNO_PPOB
4. Di POS buka:
   /public/license-setup.php
5. Isi URL server license, contoh:
   https://domain-license-kamu.com/license-api
6. Isi license key.
7. Klik Aktivasi Lisensi.

Catatan:
Kalau ingin URL server license di-hardcode langsung, kirim URL hosting license-api kamu.
