#!/usr/bin/env python3
"""Turunkan 'kolom repair' dari seluruh CREATE TABLE installer:
kolom yang dideklarasikan installer tapi TIDAK ada di vk_sys_expected_schema() untuk tabel yang sama.
Tabel itu bisa sudah ada di dump lama (CREATE TABLE IF NOT EXISTS tidak menambah kolom) ->
tanpa daftar ini halaman bisa fatal 'Unknown column'."""
import os, re, pathlib, collections, json

REPO = pathlib.Path(__file__).resolve().parent.parent
upg = (REPO / "legacy/app/Modules/System/SystemUpgrade.php").read_text()

exp_raw = re.search(r"function vk_sys_expected_schema\(\): array\s*\{\s*return \[(.*?)\n    \];", upg, re.S).group(1)
exp = {}
for m in re.finditer(r"'([a-z_0-9]+)'\s*=>\s*\[([^\]]*)\]", exp_raw):
    exp[m.group(1)] = set(re.findall(r"'([a-z_0-9]+)'", m.group(2)))

CREATE = re.compile(r"CREATE TABLE IF NOT EXISTS\s+`?([a-z_0-9]+)`?\s*\((.*?)\)\s*ENGINE", re.S | re.I)
KOL = re.compile(r"^\s*`?([a-z_][a-z_0-9]*)`?\s+([A-Za-z][A-Za-z0-9_]*(?:\([^)]*\))?[^,\n]*)", re.M | re.I)
SKIP_KATA = {"primary", "unique", "key", "index", "constraint", "foreign", "spatial", "fulltext"}

semua = collections.defaultdict(dict)   # tabel -> kolom -> definisi
pemilik = collections.defaultdict(dict)
for folder in ["legacy/public/admin", "public/admin", "pos/public/admin", "legacy/app/Modules", "app/Modules", "pos/app/Modules"]:
    for f in (REPO / folder).rglob("*.php"):
        t = f.read_text(errors="replace")
        for m in CREATE.finditer(t):
            tabel, body = m.group(1).lower(), m.group(2)
            for km in KOL.finditer(body.replace("\\n", "\n")):
                kol, def_ = km.group(1).lower(), " ".join(km.group(2).split()).rstrip(',')
                if kol in SKIP_KATA:
                    continue
                if len(def_) < 3 or def_.lower().startswith(("foreign", "references")):
                    continue
                semua[tabel].setdefault(kol, def_)
                pemilik[tabel].setdefault(kol, str(f.relative_to(REPO)))

# kolom repair = dideklarasikan installer, untuk tabel yang SUDAH ada di daftar harapan,
# dan kolomnya belum ada di daftar harapan -> bisa hilang di dump lama
repair = collections.defaultdict(dict)
for tabel, kols in semua.items():
    if tabel not in exp:
        continue
    for kol, def_ in kols.items():
        if kol in exp[tabel] or kol == 'id':
            continue
        repair[tabel][kol] = (def_, pemilik[tabel].get(kol, "?"))

total = sum(len(v) for v in repair.values())
print(f"kolom repair yang bisa diturunkan: {total} di {len(repair)} tabel\n")
for tabel, kols in sorted(repair.items()):
    print(f"  {tabel} ({len(kols)}): {', '.join(kols)}")
json.dump({t: {k: v[0] for k, v in ks.items()} for t, ks in repair.items()}, open(os.environ.get("VK_COLUMNS_OUT", "/tmp/kolom_repair.json"), "w"), indent=1)
print("\ndisimpan di /tmp/kolom_repair.json")

# tabel yang diciptakan installer tapi TIDAK ada di daftar harapan (informasi)
luar = sorted(set(semua) - set(exp))
print(f"\ntabel di luar daftar harapan ({len(luar)}): {', '.join(luar[:40])}{' ...' if len(luar)>40 else ''}")