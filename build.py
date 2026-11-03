#!/usr/bin/env python3
"""Build the installable package and publish its SHA-512 into updates.xml.

    python build.py

Creates com_fgextensioncleanup_v<version>.zip next to this script (version is read from the
manifest), then writes the archive's SHA-256 and SHA-512 into updates.xml. The archive is deterministic
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


def set_tag(xml: str, tag: str, digest: str) -> str:
    if f"<{tag}>" in xml:
        return re.sub(rf"<{tag}>[^<]*</{tag}>", f"<{tag}>{digest}</{tag}>", xml)
    return re.sub(r"(\n(\s*)<tags>)", lambda m: f"\n{m.group(2)}<{tag}>{digest}</{tag}>{m.group(1)}", xml, count=1)


def write_checksum(version: str, sha256: str, sha512: str) -> None:
    updates = ROOT / "updates.xml"
    xml = updates.read_text(encoding="utf-8")
    declared = re.search(r"<version>([^<]+)</version>", xml)
    if not declared or declared.group(1) != version:
        sys.exit(f"updates.xml version ({declared.group(1) if declared else '?'}) differs from the manifest ({version}). Update updates.xml first.")
    xml = set_tag(set_tag(xml, "sha512", sha512), "sha256", sha256)
    updates.write_text(xml, encoding="utf-8", newline="\n")


def main() -> None:
    version = read_version()
    archive = ROOT / f"{NAME}_v{version}.zip"
    build(archive)
    data = archive.read_bytes()
    sha256 = hashlib.sha256(data).hexdigest()
    sha512 = hashlib.sha512(data).hexdigest()
    write_checksum(version, sha256, sha512)
    print(f"Built {archive.name} ({archive.stat().st_size} bytes)")
    print(f"SHA-256 {sha256}")
    print(f"SHA-512 {sha512}")
    print("updates.xml updated - commit it, then upload this exact zip to the release.")


if __name__ == "__main__":
    main()
