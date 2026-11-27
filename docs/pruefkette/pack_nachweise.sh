#!/bin/bash
# Pack the review comments and the evidence of one delivery (2026-11-27, as for catquizlab):
#   adele-<release>-kommentare.zip  kommentare/<issue>-kommentar-<release>.md
#   adele-<release>-nachweise.zip   nachweise/ (LIESMICH.md, repository, gate, phpunit, e2e, ci, lokal, werkzeug)
# Expects the evidence tree in $N and the comments in $K; adds what can be derived here:
# the comparison of every plugin ZIP with origin/development (read only), the SHA-256 of
# every file in the ZIPs, the gate and Jest logs of package.sh and the scripts used.
set -e
REL=${REL:?release, e.g. 0.8.0}
V=${V:?version, e.g. 2026112700}
N=${N:?evidence tree}
K=${K:?comments tree}
OUT=${OUT:-/mnt/user-data/outputs}
GH=/home/claude/gh
mkdir -p "$N/repository" "$N/gate" "$N/werkzeug"
for p in local mod enrol; do
  git -C "$GH/moodle-${p}_adele" fetch -q origin development
  python3 "$GH/moodle-enrol_adele/docs/pruefkette/repovergleich.py" "$GH/moodle-${p}_adele" origin/development \
    "$OUT/${p}_adele-$V.zip" "${p}_adele" > "$N/repository/${p}_adele-$(git -C "$GH/moodle-${p}_adele" rev-parse --short=8 origin/development)-gegen-$REL.txt"
done
: > "$N/gate/quelle-sha256.txt"
for p in local mod enrol; do
  tmp=$(mktemp -d); unzip -q "$OUT/${p}_adele-$V.zip" -d "$tmp"
  (cd "$tmp" && find . -type f | LC_ALL=C sort | sed 's|^\./||' | while read -r f; do
     printf '%s  %s/%s\n' "$(sha256sum "$f" | cut -d' ' -f1)" "${p}_adele" "$f"; done) >> "$N/gate/quelle-sha256.txt"
  rm -rf "$tmp"
done
cp /tmp/gate_last.log "$N/gate/ci_gate-$V.log"
cp /tmp/jest_last.log "$N/gate/jest-workflow-$V.log"
cp "$GH/moodle-enrol_adele/docs/pruefkette/"*.sh "$GH/moodle-enrol_adele/docs/pruefkette/"*.py "$N/werkzeug/"
rm -f "$OUT/adele-$REL-nachweise.zip" "$OUT/adele-$REL-kommentare.zip"
(cd "$(dirname "$N")" && mv "$(basename "$N")" nachweise && zip -qr -X "$OUT/adele-$REL-nachweise.zip" nachweise && mv nachweise "$(basename "$N")")
(cd "$(dirname "$K")" && mv "$(basename "$K")" kommentare && zip -qr -X "$OUT/adele-$REL-kommentare.zip" kommentare && mv kommentare "$(basename "$K")")
ls -la "$OUT/adele-$REL-"*.zip
