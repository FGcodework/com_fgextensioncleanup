#!/usr/bin/env python3
"""Build the installable package and publish its SHA-512 into updates.xml.

    python build.py

Creates com_fgextensioncleanup_v<version>.zip next to this script (version is read from the
manifest), then writes the archive's SHA-512 into updates.xml. The archive is deterministic
(sorted entries, fixed timestamps and permissions), so building twice gives the same file.
Upload exactly this file as the GitHub release asset - the checksum belongs to these bytes.
Works on Windows, macOS and Linux; Python 3.8+ only, no third-party packages.
"""
import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
NAME = "com_fgextensioncleanup"
INCLUDE = ["com_fgextensioncleanup.xml", "script.php", "admin"]
SKIP_NAMES = {".DS_Store", "Thumbs.db", "desktop.ini"}
FIXED_TIME = (2020, 1, 1, 0, 0, 0)


def read_version() -> str:
    match = re.search(r"<version>\s*(\d+\.\d+\.\d+)\s*</version>", (ROOT / "com_fgextensioncleanup.xml").read_text(encoding="utf-8"))
    if not match:
        sys.exit("Cannot read a x.y.z version from the manifest.")
    return match.group(1)


def collect():
    files = []
    for entry in INCLUDE:
        path = ROOT / entry
        if path.is_dir():
            files += [p for p in path.rglob("*") if p.is_file() and p.name not in SKIP_NAMES]
        elif path.is_file():
            files.append(path)
        else:
            sys.exit(f"Missing {entry}")
    return sorted(files, key=lambda p: p.relative_to(ROOT).as_posix())


def build(archive: Path) -> None:
    if archive.exists():
        archive.unlink()
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in collect():
            info = zipfile.ZipInfo(f"{NAME}/{path.relative_to(ROOT).as_posix()}", FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            info.create_system = 3
            zf.writestr(info, path.read_bytes(), compresslevel=9)


def write_checksum(version: str, digest: str) -> None:
    updates = ROOT / "updates.xml"
    xml = updates.read_text(encoding="utf-8")
    declared = re.search(r"<version>([^<]+)</version>", xml)
    if not declared or declared.group(1) != version:
        sys.exit(f"updates.xml version ({declared.group(1) if declared else '?'}) differs from the manifest ({version}). Update updates.xml first.")
    if "<sha512>" in xml:
        xml = re.sub(r"<sha512>[^<]*</sha512>", f"<sha512>{digest}</sha512>", xml)
    else:
        xml = re.sub(r"(\n(\s*)<tags>)", lambda m: f"\n{m.group(2)}<sha512>{digest}</sha512>{m.group(1)}", xml, count=1)
    updates.write_text(xml, encoding="utf-8", newline="\n")


def main() -> None:
    version = read_version()
    archive = ROOT / f"{NAME}_v{version}.zip"
    build(archive)
    digest = hashlib.sha512(archive.read_bytes()).hexdigest()
    write_checksum(version, digest)
    print(f"Built {archive.name} ({archive.stat().st_size} bytes)")
    print(f"SHA-512 {digest}")
    print("updates.xml updated - commit it, then upload this exact zip to the release.")


if __name__ == "__main__":
    main()
