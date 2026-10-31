#!/usr/bin/env bash
#
# Builds the installable plugin zip: every file except what .distignore lists (tests, dev configs,
# Composer files, ...), inside a folder osec-addon-contributors/, as WordPress expects it.
# Used by the release workflows; works locally too.
#
# Usage: bash bin/build-zip.sh <name> [<stamp version>]
#   name           part of the zip name: the version for releases, "dev" for the development build
#   stamp version  optional: written into the "Version" header and OSEC_ADDON_CONTRIBUTORS_VERSION of
#                  the copy in the zip (the repository is not changed); the build fails if it does not land
# Examples: bash bin/build-zip.sh 0.2.0
#           bash bin/build-zip.sh dev 0.1.0-dev.2977043
# Result: build/osec-addon-contributors-<name>.zip (build/ is git-ignored).

set -eu
cd "$(dirname "$0")/.."

name=${1:?Usage: bash bin/build-zip.sh <name> [<stamp version>]}
stamp=${2:-}
slug=osec-addon-contributors

rm -rf build
mkdir -p "build/$slug"
rsync -a --exclude-from=.distignore --exclude=build ./ "build/$slug/"

if [ -n "$stamp" ]; then
	[[ $stamp =~ ^[0-9A-Za-z.+-]+$ ]] || { echo "Invalid version: $stamp" >&2; exit 1; }
	main="build/$slug/$slug.php"
	sed -i -E \
		-e "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*)[^[:space:]]+/\1$stamp/" \
		-e "s/^(const OSEC_ADDON_CONTRIBUTORS_VERSION[[:space:]]*=[[:space:]]*')[^']+'/\1$stamp'/" \
		"$main"
	grep -qE "^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*$stamp\$" "$main" || { echo "Version header not stamped." >&2; exit 1; }
	grep -qF "const OSEC_ADDON_CONTRIBUTORS_VERSION = '$stamp';" "$main" || { echo "Version constant not stamped." >&2; exit 1; }
fi

(cd build && zip -qr "$slug-$name.zip" "$slug")
echo "build/$slug-$name.zip"
