#!/usr/bin/env bash
# Builds com_fgextensioncleanup_v<version>.zip (version read from the manifest) next to this script.
set -euo pipefail
cd "$(dirname "$0")"
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' com_fgextensioncleanup.xml | head -n1)
name="com_fgextensioncleanup_v${version}.zip"
tmp=$(mktemp -d)
mkdir "$tmp/com_fgextensioncleanup"
cp -r com_fgextensioncleanup.xml script.php admin "$tmp/com_fgextensioncleanup/"
rm -f "$name"
(cd "$tmp" && zip -qr "$OLDPWD/$name" com_fgextensioncleanup)
rm -rf "$tmp"
echo "Built $name"
