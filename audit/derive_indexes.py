#!/usr/bin/env python3
"""Turunkan daftar index dari blok CREATE TABLE di installer, lalu bandingkan
dengan vk_sys_expected_indexes() + indexJobs upgrader untuk menemukan yang belum bisa dipulihkan."""
import re, pathlib, json, sys

REPO = pathlib.Path(__file__).resolve().parent.parent

def blok_create(teks):
    """Yield (table, isi_definisi) dari CREATE TABLE IF NOT EXISTS `?table`? ( ... )"""
    for m in re.finditer(r"CREATE TABLE IF NOT EXISTS\s+`?([a-z_0-9]+)`?\s*\((.*?)\)\s*ENGINE", teks, re.S | re.I):
        yield m.group(1), m.group(2)

# kumpulkan definisi index dari SELURUH kode (3 tree)
definisi = {}   # index_name -> (table, columns, unique)
sumber = {}
for folder in ["legacy/public/admin", "public/admin", "pos/public/admin",
               "app/Modules", "legacy/app/Modules", "pos/app/Modules",
               "app/Modules/Marketplace", "legacy/app/Modules/Marketplace", "pos/app/Modules/Marketplace"]:
    for f in (REPO / folder).rglob("*.php"):
        t = f.read_text(errors="replace")
        for table, isi in blok_create(t):
            for km in re.finditer(r"(UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-z_0-9]+)`?\s*\(([^)]*)\)", isi, re.I):
                uniq = bool(km.group(1))
                nama = km.group(2)
                cols = ",".join(c.strip().strip("`") for c in km.group(3).split(","))
                definisi.setdefault(nama, (table, cols, uniq))
                sumber.setdefault(nama, str(f.relative_to(REPO)))

# indexJobs + expected dari upgrader
upg = (REPO / "legacy/app/Modules/System/SystemUpgrade.php").read_text()
jobs_raw = re.search(r"\$indexJobs = \[(.*?)\n    \];", upg, re.S).group(1)
jobs = {m.group(2): (m.group(1), m.group(3), m.group(4) == "true")
        for m in re.finditer(r"\['([a-z_0-9]+)','([a-z_0-9]+)','([^']+)',(true|false)\]", jobs_raw)}
exp_raw = re.search(r"function vk_sys_expected_indexes\(\): array\s*\{\s*return \[(.*?)\n    \];", upg, re.S).group(1)
exp = {}
for m in re.finditer(r"'([a-z_0-9]+)'\s*=>\s*\[([^\]]*)\]", exp_raw):
    exp[m.group(1)] = re.findall(r"'([a-z_0-9]+)'", m.group(2))

print(f"index yang DIHARAPKAN : {sum(len(v) for v in exp.values())}")
print(f"index yang dibuat indexJobs upgrader: {len(jobs)}")
print(f"index yang punya definisi di installer: {len(definisi)}")

belum = []
for tabel, daftar in exp.items():
    for i in daftar:
        if i in jobs:
            continue
        # apakah bisa diturunkan dari installer?
        d = definisi.get(i)
        if d and d[0] == tabel:
            belum.append((tabel, i, d[1], d[2]))
        else:
            belum.append((tabel, i, None, None))

print(f"\nINDEX YANG TIDAK BISA DIPULIHKAN UPGRADER: {len(belum)}")
for tabel, nama, cols, uniq in belum:
    tag = f"['{tabel}','{nama}','{cols}',{'true' if uniq else 'false'}]," if cols else f"### {tabel}.{nama}: DEFINISI TIDAK DITEMUKAN DI KODE"
    print(f"  {tag}")

print("\n--- siap ditempel ke \$indexJobs ---")
baris = [f"        ['{t}','{i}','{c}',{'true' if u else 'false'}]," for t, i, c, u in belum if c]
print("\n".join(baris))
pathlib.Path("/tmp/indexjobs_tambahan.txt").write_text("\n".join(baris))
print(f"\n({len(baris)} baris disimpan di /tmp/indexjobs_tambahan.txt)")