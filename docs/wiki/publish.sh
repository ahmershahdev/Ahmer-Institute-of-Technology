#!/usr/bin/env sh
# Publishes docs/wiki to the GitHub wiki. GitHub creates the wiki repository
# only after a first page is saved in the browser (Wiki tab -> Create the first page).
set -eu
cd "$(dirname "$0")"
tmp=$(mktemp -d)
git clone -q https://github.com/ahmershahdev/Ahmer-Institute-of-Technology.wiki.git "$tmp"
cp ./*.md "$tmp"/
cd "$tmp" && git add -A && git commit -qm "Update wiki from docs/wiki" && git push -q
echo "Wiki published."
