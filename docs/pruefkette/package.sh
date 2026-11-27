#!/bin/bash
# Zip the three plugins (contents at the zip root, as before) and list files removed since $PREV.
set -e
# No package without the gate (2026-11-23): the static Moodle Plugin CI steps
# as the CI runs them (fresh toolchain, staged checkout, runner user) and the
# Jest workflow step by step. A red gate stops here.
if [ "${SKIP_GATE:-}" != "I know" ]; then
  bash /tmp/ci_gate.sh > /tmp/gate_last.log 2>&1 || { grep -E "^(FAIL|GATE)" /tmp/gate_last.log; echo "PACKAGE REFUSED: ci_gate red (/tmp/gate_last.log)"; exit 1; }
  bash /tmp/jest_ci.sh > /tmp/jest_last.log 2>&1 || { grep -E "^(FAIL|SKIP)" /tmp/jest_last.log; echo "PACKAGE REFUSED: Jest workflow red (/tmp/jest_last.log)"; exit 1; }
  echo "gate green: $(grep -c '^ok' /tmp/gate_last.log) static steps, Jest workflow $(grep -c '^OK' /tmp/jest_last.log) steps"
fi
V=2026112700
PREV=2026112600
OUT=/mnt/user-data/outputs
for p in local mod enrol; do
  src=/home/claude/gh/moodle-${p}_adele
  tmp=$(mktemp -d)
  rsync -a --exclude .git --exclude node_modules --exclude test-results --exclude playwright-report --exclude coverage \
    --exclude 'blob-report' --exclude '.nyc_output' "$src/" "$tmp/"
  rm -f "$OUT/${p}_adele-$V.zip"
  (cd "$tmp" && zip -qr -X "$OUT/${p}_adele-$V.zip" .)
  rm -rf "$tmp"
  unzip -Z1 "$OUT/${p}_adele-$PREV.zip" | sort > /tmp/old_$p.txt
  unzip -Z1 "$OUT/${p}_adele-$V.zip" | sort > /tmp/new_$p.txt
  echo "== ${p}_adele removed since $PREV:"
  comm -23 /tmp/old_$p.txt /tmp/new_$p.txt
done
ls -la $OUT/*-$V.zip
