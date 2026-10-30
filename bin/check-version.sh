#!/usr/bin/env bash
#
# Checks that a version number matches the three places that state the plugin version:
# the "Version" header of osec-addon-contributors.php, "Stable tag" in readme.txt and the
# constant OSEC_ADDON_CONTRIBUTORS_VERSION. Used by the release workflow before publishing.
#
# Usage: bash bin/check-version.sh 0.2.0
# Exit code 0 when all match, 1 otherwise (each mismatch is printed).

set -u
cd "$(dirname "$0")/.." || exit 1

expected=${1:?Usage: bash bin/check-version.sh <version>}
header=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' osec-addon-contributors.php | head -n 1)
stable=$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' readme.txt | head -n 1)
constant=$(sed -nE "s/^const OSEC_ADDON_CONTRIBUTORS_VERSION[[:space:]]*=[[:space:]]*'([^']+)'.*/\1/p" osec-addon-contributors.php | head -n 1)

status=0
for pair in "plugin header Version:$header" "readme.txt Stable tag:$stable" "OSEC_ADDON_CONTRIBUTORS_VERSION:$constant"; do
	name=${pair%%:*}
	value=${pair#*:}
	if [ "$value" != "$expected" ]; then
		echo "Mismatch: $name is '$value', expected '$expected'."
		status=1
	fi
done
[ $status -eq 0 ] && echo "Version $expected matches header, readme and constant."
exit $status
