#!/usr/bin/env python3
"""AUDIT 1 — croscek perintah & script rilis v17.0.16 (tanpa deploy, tanpa sentuh hosting)."""
import re, subprocess, zipfile, sys, pathlib, urllib.request, hashlib

REPO = pathlib.Path(__file__).resolve().parent.parent
VER = (REPO / "VERSION").read_text().strip()
VER0 = (REPO / "VERSION").read_text().strip()
SNAP = REPO / f"patch/RESTORE_MASTER_v{VER0}/MASTER_VAPEKONG_v{VER0}_CODE.zip"
RAW = "https://raw.githubusercontent.com/genggong/vapestore/main"
TEMUAN = []   # (level, judul, detail)
def catat(ok, judul, detail=""):
    TEMUAN.append(("OK" if ok else "MASALAH", judul, detail))
    print(f"  [{'OK' if ok else 'MASALAH'}] {judul}" + (f" -> {detail}" if detail else ""))
def judul(t): print(f"\n=== {t} ===")

def sh(cmd, **kw):
    return subprocess.run(cmd, shell=True, capture_output=True, text=True, **kw).stdout.strip()

# ---------------------------------------------------------------- 1. URL
judul("1. SETIAP URL YANG DIRUJUK SCRIPT -> HARUS 200")
apply_sh = (REPO / "tools/apply_patch_v17_0_16.sh").read_text()
urls = set()
for f in list(REPO.glob("patch/**/*.sh")) + [REPO / "tools/apply_patch_v17_0_16.sh"]:
    if f.is_file():
        isi = f.read_text()
        isi = isi.replace("${VERSION}", VER)          # template -> versi nyata
        isi = re.sub(r'\$\{VK_[A-Z_]+\}:\-([^}]+)\}', r'\1', isi)  # nilai default var
        for u in re.findall(r'https://raw\.githubusercontent\.com/genggong/vapestore/[^\s"\'\)}]+', isi):
            urls.add(u.rstrip('";\' '))
for u in sorted(urls):
    nama = u.rstrip("/").split("/")[-1]
    if not nama.endswith((".sh", ".zip", ".md", ".txt", ".php", ".js", ".json")):
        # bukan target unduh (URL dasar/template ${VK_BASE_URL:-...} atau folder rilis)
        print(f"  [--]  dilewati (URL dasar/folder, bukan file): {u.replace(RAW+'/','')}")
        continue
    code = sh(f'curl -sS -o /dev/null -w "%{{http_code}}" -L "{u}"')
    catat(code == "200", f"{code}  {u.replace(RAW+'/','')}")

# ------------------------------------------------- 2. SYNTAX SEMUA SCRIPT
judul("2. SYNTAX SEMUA SCRIPT SHELL & PHP YANG DIRUJUK")
for f in sorted(REPO.glob("patch/**/*.sh")) + [REPO / "patch/UPDATE_VAPEKONG.sh", REPO / "tools/apply_patch_v17_0_16.sh"]:
    r = subprocess.run(["bash", "-n", str(f)], capture_output=True, text=True)
    catat(r.returncode == 0, f"bash -n {f.relative_to(REPO)}", r.stderr.strip()[:120])
php_files = sorted(REPO.glob("tools/*.php"))
bad_php = []
for f in php_files:
    r = subprocess.run(["php", "-l", str(f)], capture_output=True, text=True)
    if r.returncode != 0: bad_php.append(f.name)
catat(not bad_php, f"php -l {len(php_files)} file tools/*.php", ", ".join(bad_php) if bad_php else "semua bersih")

# ------------------------------------------------------- 3. KONSISTENSI VERSI
judul("3. KONSISTENSI VERSI DI SEMUA TITIK")
ver_repo = VER
with zipfile.ZipFile(SNAP) as z: ver_zip = z.read("VERSION").decode().strip()
ver_restore = re.search(r'^VERSION="([\d.]+)"', (REPO / f"patch/RESTORE_MASTER_v{VER}/restore_master.sh").read_text(), re.M).group(1)
lat = (REPO / "patch/latest.txt").read_text().strip()
ver_lat, dir_lat = lat.split("|")
upg = (REPO / "legacy/app/Modules/System/SystemUpgrade.php").read_text()
ver_sys = re.search(r'v(\d+\.\d+\.\d+)', upg.split("\n")[3]).group(1)
halaman = (REPO / "legacy/public/admin/system-upgrade/index.php").read_text()
vers_halaman = sorted(set(re.findall(r'(\d+\.\d+\.\d+)', halaman)))
for nama, v in [("VERSION repo", ver_repo), ("VERSION di zip snapshot", ver_zip), ("restore_master.sh", ver_restore),
                ("patch/latest.txt", ver_lat), ("SystemUpgrade docblock", ver_sys)]:
    catat(v == VER, f"{nama} = {v}")
catat(ver_lat == ver_sys == ver_zip == ver_restore == ver_repo, f"kelima titik versi sepakat {VER}")
catat((REPO / "patch" / dir_lat).is_dir(), f"folder yang ditunjuk latest.txt ada: {dir_lat}")
catat((REPO / "patch" / dir_lat / f"MASTER_VAPEKONG_v{ver_lat}_CODE.zip").exists(), f"zip master v{ver_lat} ada di folder itu")
catat(VER in vers_halaman, f"versi {VER} muncul di halaman system-upgrade", f"versi lain di halaman: {[v for v in vers_halaman if v!=VER][:6]}")

# ------------------------------------- 4. DRIFT 3 TREE (SKEMA & SKRIP DB)
judul("4. DRIFT ANTAR-TREE: file penentu skema DB harus IDENTIK di legacy/ app/ pos/")
for rel in ["app/Modules/System/SystemUpgrade.php", "app/Modules/Marketplace/SchemaDoctor.php"]:
    h = {}
    for tree, p in [("legacy", f"legacy/{rel}"), ("root", rel), ("pos", f"pos/{rel}")]:
        f = REPO / p
        h[tree] = hashlib.sha256(f.read_bytes()).hexdigest()[:12] if f.is_file() else "TIDAK ADA"
    catat(len(set(h.values())) == 1, f"{rel} identik di 3 tree", str(h))
# daftar skema/idempoten
def daftar(teks, nama):
    return re.search(rf"function {nama}\(\): array.*?\n\}}", teks, re.S).group(0)
s_legacy = daftar((REPO / "legacy/app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_schema")
s_root = daftar((REPO / "app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_schema")
s_pos = daftar((REPO / "pos/app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_schema")
catat(s_legacy == s_root == s_pos, "daftar vk_sys_expected_schema() identik di 3 tree")
i_legacy = daftar((REPO / "legacy/app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_indexes")
i_root = daftar((REPO / "app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_indexes")
i_pos = daftar((REPO / "pos/app/Modules/System/SystemUpgrade.php").read_text(), "vk_sys_expected_indexes")
catat(i_legacy == i_root == i_pos, "daftar vk_sys_expected_indexes() identik di 3 tree")

# ------------------------- 5. BACKUP LIST APPLY SCRIPT vs ISI PATCH ZIP
judul("5. APPLY SCRIPT: backup diambil dari ISI PATCH ZIP (bukan daftar manual) + cadangan penuh")
m = re.search(r"done <<'EOF'\n(.*?)\nEOF", apply_sh, re.S)
if m:   # desain lama (daftar manual)
    backup_list = {l.strip() for l in m.group(1).splitlines() if l.strip()}
    with zipfile.ZipFile(REPO / "patch/v17.0.16/PATCH_v17.0.16.zip") as z:
        patch_members = {n for n in z.namelist() if not n.endswith("/")}
    tanpa_backup = sorted(patch_members - backup_list)
    catat(not tanpa_backup, f"{len(patch_members)} file di patch zip, backup list {len(backup_list)} entri",
          f"TANPA BACKUP: {tanpa_backup}" if tanpa_backup else "semua file patch punya backup")
    tidak_ada = [b for b in sorted(backup_list) if not (REPO / b).exists()]
    catat(not tidak_ada, "semua entri backup list benar-benar ada di repo", f"TIDAK ADA: {tidak_ada}" if tidak_ada else "")
else:   # desain baru: daftar backup diturunkan dari isi zip saat dijalankan
    with zipfile.ZipFile(REPO / "patch/v17.0.16/PATCH_v17.0.16.zip") as z:
        n_members = len([n for n in z.namelist() if not n.endswith("/")])
    catat('unzip -Z1 "${ZIP}"' in apply_sh, f"backup diturunkan dari isi zip ({n_members} file) saat dijalankan")
    catat("N_BACKUP" in apply_sh and ".bak" in apply_sh, "tiap file yang DITIMPA disalin ke <path>.bak")
    catat("full_docroot.tar.gz" in apply_sh, "ada cadangan penuh (tar) sebagai jaring pengaman kedua")
    catat("unzip -t -q" in apply_sh, "zip patch diuji sebelum dipakai (unzip -t)")
    print("      (diuji nyata di test_apply_patch.sh: 303 file ditimpa -> 303 .bak terbentuk)")

# ------------------- 6. MARKER YANG DIGREP RESTORE HARUS ADA DI SNAPSHOT
judul("6. VERIFIKASI RESTORE: marker yang dicek harus ADA di dalam snapshot")
marker = [
    ("shipping_income", "legacy/app/Modules/Accounting/Accounting.php"),
    ("branch_sync_all_product_stocks", "legacy/public/admin/branches/_helpers.php"),
    ("haveWs < $needWs", "legacy/public/admin/warehouse/_helpers.php"),
    ("APP_ASSET_VERSION", "legacy/public/layouts/admin-header.php"),
]
with zipfile.ZipFile(SNAP) as z:
    nama = set(z.namelist())
    for pola, f in marker:
        if f not in nama:
            catat(False, f"marker '{pola}'", f"FILE {f} TIDAK ADA di snapshot")
            continue
        isi = z.read(f).decode("utf-8", "replace")
        catat(pola in isi, f"marker '{pola}' ada di {f}")
    # file wajib lain
    for f in ["index.php", ".htaccess", "media.php", "spark", "tools/apply_patch_v17_0_16.sh",
              "tools/backfill_v17_0_16.php", "app/Modules/System/SystemUpgrade.php",
              "legacy/app/Modules/System/SystemUpgrade.php", "pos/app/Modules/System/SystemUpgrade.php",
              "writable/.htaccess", "legacy/app/Config/database.php"]:
        catat(f in nama, f"snapshot memuat {f}")

# ------------------------------------------- 7. FILE YANG DIHAPUS (hash.php dll)
judul("7. FILE BERBAHAYA TIDAK BOLEH IKUT SNAPSHOT")
with zipfile.ZipFile(SNAP) as z:
    n2 = z.namelist()
for pola in ["hash.php", ".env"]:
    hit = [x for x in n2 if x == pola or x.endswith("/" + pola)]
    catat(not hit, f"tidak ada '{pola}' di snapshot", ", ".join(hit) if hit else "")
# app/Config/database.php BOLEH ada di snapshot (isinya getenv tanpa kredensial), TAPI
# restore_master.sh wajib mempertahankan versi milik hosting (diuji di test_restore.sh TEST 8).
config_di_zip = [x for x in n2 if x.endswith("/Config/database.php") or x.endswith("/Config/Database.php")]
restore_sh = (REPO / f"patch/RESTORE_MASTER_v{VER}/restore_master.sh").read_text()
catat(bool(config_di_zip) and "dipertahankan (config hosting)" in restore_sh,
      f"config DB ada di zip ({len(config_di_zip)} file) DAN restore mempertahankan versi hosting",
      ", ".join(config_di_zip))
tanpa_kredensial = []
with zipfile.ZipFile(SNAP) as z:
    for c in config_di_zip:
        isi = z.read(c).decode("utf-8", "replace")
        aktif = "\n".join(l for l in isi.splitlines() if not l.strip().startswith(("//", "*", "#")))
        # aman bila: memakai getenv(), atau nilai password-nya kosong
        punya_nilai = re.search(r"['\"]password['\"]\s*=>\s*['\"][^'\"]{3,}['\"]", aktif)
        if "getenv(" not in aktif and punya_nilai:
            tanpa_kredensial.append(c)
catat(not tanpa_kredensial, "config DB di snapshot tidak menyimpan kredensial asli (getenv)",
      f"PERIKSA: {tanpa_kredensial}" if tanpa_kredensial else "semua pakai getenv()")

judul("8. MARKER YANG DIGREP APPLY SCRIPT HARUS ADA DI ISI PATCH ZIP")
with zipfile.ZipFile(REPO / "patch/v17.0.16/PATCH_v17.0.16.zip") as zp:
    members = set(zp.namelist())
    isi_map = {m: zp.read(m).decode("utf-8", "replace") for m in members if m.endswith((".php", ".js", ".sh"))}
marker_apply = re.findall(r'grep -q (?:"([^"]+)"|\'([^\']+)\') ([^\s]+)', apply_sh)
for a, b, f in marker_apply:
    pola = a or b
    if pola in {"haveWs", "needWs"}:
        continue
    f = f.rstrip(";") 
    if f not in members:
        catat(False, f"marker '{pola}' -> file {f} tidak ada di patch zip")
        continue
    catat(pola in isi_map.get(f, ""), f"marker '{pola}' ada di {f} (isi patch zip)")

judul("9. AUDIT TOOLS BACKFILL: urutan bootstrap + anti-fatal-di-hosting")
for nama in ["tools/backfill_v17_0_16.php", "tools/backfill_pos_transactions.php"]:
    f = REPO / nama
    if not f.is_file():
        catat(False, f"{nama} tidak ada"); continue
    t = f.read_text()
    # cari daftar kandidat bootstrap
    kandidat = re.findall(r"(?:__DIR__\s*\.\s*)?'([^']*bootstrap\.php)'", t)
    pertama = kandidat[0] if kandidat else "(tidak ada)"
    catat(bool(kandidat) and "app/bootstrap.php" in kandidat[0],
          f"{nama}: kandidat bootstrap pertama = {pertama}", 
          "" if (kandidat and "legacy" not in kandidat[0]) else "BERISIKO: kandidat legacy lebih dulu")
    catat("display_errors" in t, f"{nama}: menyalakan display_errors (agar tidak blank)", "")
    catat(bool(re.search(r"marker|idempoten|pos_recorded_at|IS NULL", t)),
          f"{nama}: punya guard idempotensi", "")
    r = subprocess.run(["php", "-l", str(f)], capture_output=True, text=True)
    catat(r.returncode == 0, f"{nama}: php -l bersih", r.stderr.strip()[:100])

print("\n" + "=" * 76)
gagal = [t for t in TEMUAN if t[0] == "MASALAH"]
print(f"RINGKASAN AUDIT 1: {len(TEMUAN)-len(gagal)} OK, {len(gagal)} MASALAH")
for _, j, d in gagal: print(f"  - {j}" + (f" ({d})" if d else ""))
sys.exit(1 if gagal else 0)