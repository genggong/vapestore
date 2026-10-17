#!/usr/bin/env python3
"""Bangun MASTER_VAPEKONG_v17.0.16_CODE.zip dari tree repo vapestore.

Konvensi mengikuti MASTER_VAPEKONG_v17.0.10_CODE.zip:
  - SERTAKAN : app/ legacy/ pos/ public/ system/ assets/ database/ docs/ storage/
               writable/ tools/ index.php .htaccess .user.ini php.ini VERSION
               .env.example composer.json spark media.php LICENSE_CI4 README*
  - BUANG    : patch/ (distribusi, jangan masuk docroot), tests/, uploads/ (media live),
               .git*, dan hash.php (utility bocor kredensial — sengaja TIDAK dikirim)
"""
import subprocess, zipfile, os, sys, pathlib, re

TREE = pathlib.Path(__file__).resolve().parent.parent
VER = (TREE / "VERSION").read_text().strip()
OUT = pathlib.Path(os.environ.get("VK_ZIP_OUT", "/tmp/MASTER_VAPEKONG_" + f"v{VER}_CODE.zip"))
EXCLUDE_PREFIX = ("patch/", "tests/", "uploads/", "audit/")
EXCLUDE_EXACT = {".gitignore", ".env", "hash.php"}

files = subprocess.run(["git", "-C", str(TREE), "ls-files"], capture_output=True, text=True, check=True).stdout.split("\n")
files = [f for f in files if f]

# File konfigurasi yang MENYIMPAN KREDENSIAL ASLI tidak boleh ikut snapshot (contoh nyata:
# pos/app/Config/database.php memuat host/user/password produksi doang di repo publik).
# Restore juga mempertahankan versi milik hosting, jadi mengirimkannya tidak ada gunanya.
KREDENSIAL = []
for f in files:
    nama = os.path.basename(f).lower()
    if nama in {"database.php", ".env"} and "config/" in f.lower().replace("\\", "/"):
        isi = (TREE / f).read_text(errors="replace")
        # abaikan baris komentar (CI4 menyimpan contoh koneksi 'password' => 'root' di komentar)
        aktif = "\n".join(l for l in isi.splitlines() if not l.strip().startswith(("//", "*", "#")))
        if "getenv(" not in aktif and re.search(r"['\"]password['\"]\s*=>\s*['\"][^'\"]{3,}['\"]", aktif):
            KREDENSIAL.append(f)

keep, dropped = [], []
for f in files:
    if f.startswith(EXCLUDE_PREFIX) or f in EXCLUDE_EXACT or f in KREDENSIAL:
        dropped.append(f)
    else:
        keep.append(f)
print(f"file konfigurasi berisi kredensial yang DIKELUARKAN: {KREDENSIAL if KREDENSIAL else 'tidak ada'}")

# skeleton runtime CI4 (sesuai preseden zip v17.0.10; idempotent bila sudah ada)
skeleton = [
    "writable/.htaccess", "writable/index.html",
    "writable/cache/index.html", "writable/cache/.gitkeep",
    "writable/logs/index.html", "writable/logs/.gitkeep",
    "writable/session/index.html", "writable/session/.gitkeep",
    "writable/debugbar/index.html", "writable/uploads/index.html",
    "writable/backups/index.html",
]

if OUT.exists():
    OUT.unlink()
added_skeleton = []
with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for f in keep:
        src = TREE / f
        if not src.is_file():
            continue
        zi = zipfile.ZipInfo(f, date_time=(2026, 10, 17, 12, 0, 0))
        zi.external_attr = 0o644 << 16
        zi.compress_type = zipfile.ZIP_DEFLATED
        z.writestr(zi, src.read_bytes())
    have = set(keep)
    for s in skeleton:
        if s in have or (TREE / s).is_file():
            continue
        zi = zipfile.ZipInfo(s, date_time=(2026, 10, 17, 12, 0, 0))
        zi.external_attr = 0o644 << 16
        zi.compress_type = zipfile.ZIP_DEFLATED
        body = b'<html><body bgcolor="#FFFFFF"></body></html>\n' if s.endswith(".html") else b""
        z.writestr(zi, body)
        added_skeleton.append(s)

print(f"file disertakan : {len(keep)}")
print(f"file dibuang    : {len(dropped)} -> {[d for d in dropped][:6]}{' ...' if len(dropped) > 6 else ''}")
print(f"skeleton tambahan: {added_skeleton}")
print(f"ukuran zip      : {OUT.stat().st_size:,} bytes")

# --- validasi integritas + isi kunci ---
with zipfile.ZipFile(OUT) as z:
    bad = z.testzip()
    names = set(z.namelist())
    print(f"\ntestzip()       : {'OK' if bad is None else 'RUSAK di ' + bad}")
    wajib = ["index.php", ".htaccess", "VERSION", "media.php", "spark",
             "app/Config/Database.php", "legacy/app/Config/database.php",
             "legacy/public/admin/dashboard.php", "public/admin/dashboard.php",
             "pos/public/admin/dashboard.php", "system/CodeIgniter.php",
             "tools/apply_patch_v17_0_16.sh", "writable/.htaccess",
             "assets/js/stock-request.js", "legacy/public/layouts/admin-header.php"]
    hilang = [w for w in wajib if w not in names]
    print(f"file kunci      : {'lengkap' if not hilang else 'HILANG ' + str(hilang)}")
    terlarang = [n for n in names if n.startswith(EXCLUDE_PREFIX) or n in EXCLUDE_EXACT or n.startswith(".git")]
    print(f"entri terlarang : {terlarang if terlarang else 'tidak ada'}")
    print(f"VERSION di zip  : {z.read('VERSION').decode().strip()}")
    print(f"total entri     : {len(names)}")
    sys.exit(0 if (bad is None and not hilang and not terlarang) else 1)