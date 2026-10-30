#!/usr/bin/env bash
#
# Builds the installable plugin zip: every file except what .distignore lists (tests, dev configs,
# Composer files, ...), inside a folder osec-addon-contributors/, as WordPress expects it.
# Used by the release workflow; works locally too.
#
# Usage: bash bin/build-zip.sh 0.2.0
# Result: build/osec-addon-contributors-0.2.0.zip (build/ is git-ignored).

set -eu
cd "$(dirname "$0")/.."

version=${1:?Usage: bash bin/build-zip.sh <version>}
slug=osec-addon-contributors

rm -rf build
mkdir -p "build/$slug"
rsync -a --exclude-from=.distignore --exclude=build ./ "build/$slug/"
(cd build && zip -qr "$slug-$version.zip" "$slug")
echo "build/$slug-$version.zip"
