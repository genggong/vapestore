#!/usr/bin/env python3
"""AUDIT 3 — Peta kode<->database: semua tabel/kolom yang dipakai file rilis v17.0.16
harus ada di skema hasil repair (DB audit yang sudah di-upgrade)."""
import json, os, re, sys, subprocess, pathlib

REPO = pathlib.Path(__file__).resolve().parent.parent
VER = (REPO / "VERSION").read_text().strip()
SCHEMA = pathlib.Path(os.environ.get("VK_SCHEMA_OUT", "/tmp/vk_schema.json"))

# 1) dump skema dari MySQL (sumber kebenaran = hasil vk_sys_upgrade di DB audit)
dump = """
$p=new PDO("mysql:host=".(getenv("VK_DB_HOST")?:"127.0.0.1").";dbname=".(getenv("VK_DB_NAME")?:"vapekong_audit").";charset=utf8mb4", getenv("VK_DB_USER")?:"vkaudit", getenv("VK_DB_PASS")?:"VkAudit#2026");
$out=[];
foreach($p->query("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE()") as $r){ $out[$r["TABLE_NAME"]][]=$r["COLUMN_NAME"]; }
echo json_encode($out);
"""
r = subprocess.run(["php", "-r", dump], capture_output=True, text=True)
if r.returncode != 0:
    print("gagal dump skema:", r.stderr[:200]); sys.exit(2)
schema = json.loads(r.stdout)
SCHEMA.write_text(r.stdout)
print(f"skema dari DB audit: {len(schema)} tabel")

# 2) daftar file rilis (isi patch v17.0.16)
import zipfile
with zipfile.ZipFile(REPO / f"patch/v{VER}/PATCH_v{VER}.zip") as z:
    berkas = [n for n in z.namelist() if n.endswith(".php")]
print(f"file PHP rilis: {len(berkas)}")

SQL_RE = re.compile(r'"((?:[^"\\]|\\.){20,})"|\'((?:[^\'\\]|\\.){20,})\'', re.S)
TABEL_RE = re.compile(r'\b(?:FROM|JOIN|INTO|UPDATE)\s+`?([a-z_][a-z_0-9]{2,})`?(?:\s+(?:AS\s+)?([a-z_][a-z_0-9]{0,3}))?', re.I)
KOLOM_RE = re.compile(r'\b([a-z_][a-z_0-9]{0,5})\.([a-z_][a-z_0-9]{2,})\b', re.I)
KW = {"from", "join", "select", "where", "and", "or", "on", "as", "set", "values", "into", "update",
      "left", "inner", "outer", "group", "order", "by", "having", "limit", "case", "when", "then",
      "else", "end", "count", "sum", "coalesce", "ifnull", "now", "curdate", "date", "cast", "concat"}

tabel_tak_ada = {}
kolom_tak_ada = {}
for nama in berkas:
    with zipfile.ZipFile(REPO / f"patch/v{VER}/PATCH_v{VER}.zip") as z:
        isi = z.read(nama).decode("utf-8", "replace")
    for m in SQL_RE.finditer(isi):
        sql = (m.group(1) or m.group(2) or "").replace("\\n", " ")
        if not re.search(r"\b(SELECT|INSERT|UPDATE|DELETE|ALTER|CREATE)\b", sql, re.I):
            continue
        alias = {}
        for t in TABEL_RE.finditer(sql):
            tabel, al = t.group(1).lower(), (t.group(2) or "").lower()
            if tabel in schema:
                if al and al not in KW:
                    alias[al] = tabel
                alias.setdefault(tabel, tabel)
            elif tabel not in KW and not tabel.startswith(("select", "where", "and", "on", "set")):
                tabel_tak_ada.setdefault(tabel, set()).add(nama)
        for k in KOLOM_RE.finditer(sql):
            al, kol = k.group(1).lower(), k.group(2).lower()
            if al not in alias:
                continue
            tabel = alias[al]
            if kol not in [c.lower() for c in schema[tabel]]:
                kolom_tak_ada.setdefault(f"{tabel}.{kol}", set()).add(nama)

print(f"\ntabel yang dirujuk tapi TIDAK ADA di skema : {len(tabel_tak_ada)}")
dibuat_kode = set()
for folder in ["legacy/public/admin", "public/admin", "pos/public/admin", "legacy/app/Modules",
               "app/Modules", "pos/app/Modules", "legacy", "app"]:
    for f in (REPO / folder).rglob("*.php"):
        for m in re.finditer(r"CREATE TABLE IF NOT EXISTS\s+`?([a-z_][a-z_0-9]*)`?", f.read_text(errors="replace"), re.I):
            dibuat_kode.add(m.group(1).lower())
dibuat_kode |= {"information_schema"}
noise = {"current_timestamp", "cabang", "draft", "hak", "independent", "level", "lokasi", "master",
         "paket", "produk", "stok", "stock", "user", "select", "update", "delete", "insert"}
temuan_tabel = {}
for t, f in sorted(tabel_tak_ada.items()):
    if t in dibuat_kode:
        continue                      # diciptakan app sendiri (installer/ensure_* di halaman) -> wajar
    if t in noise:
        continue                      # kata Indonesia/keyword dari string non-SQL (bukan tabel)
    temuan_tabel[t] = f
for t, f in sorted(temuan_tabel.items()):
    print(f"   TEMUAN: {t}  ({len(f)} file) contoh: {sorted(f)[0]}")
print(f"   (diabaikan: {len(tabel_tak_ada) - len(temuan_tabel)} nama non-tabel / dibuat sendiri oleh app)")

print(f"\nkolom yang dirujuk tapi TIDAK ADA di tabelnya: {len(kolom_tak_ada)}")
for k, f in sorted(kolom_tak_ada.items()):
    print(f"   {k}  ({len(f)} file) contoh: {sorted(f)[0]}")

ok = not temuan_tabel and not kolom_tak_ada
print("\n" + ("KESIMPULAN: seluruh tabel/kolom yang dipakai file rilis ada di skema hasil repair.\n" if ok
              else "KESIMPULAN: ADA RUJUKAN KE SKEMA YANG TIDAK ADA — ini penyebab error di hosting!\n"))
sys.exit(0 if ok else 1)