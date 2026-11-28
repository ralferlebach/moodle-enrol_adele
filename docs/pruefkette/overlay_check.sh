#!/bin/bash
# The delivery as the repository receives it (2026-11-28): a ZIP unpacked over
# origin/development removes nothing. Check both ways for every plugin:
#   - the files that must be deleted in the repository (there, not in the ZIP);
#   - local: the bundle gate on "ZIP over development" without and with those
#     deletions. With the deletions it must be green, or no package.
# Read only on the repositories (fetch, ls-tree, cat-file).
V=${V:?version}
OUT=${OUT:-/mnt/user-data/outputs}
GH=/home/claude/gh
RC=0
for p in local mod enrol; do
  repo=$GH/moodle-${p}_adele
  git -C "$repo" fetch -q origin development
  base=$(mktemp -d)
  python3 $GH/moodle-enrol_adele/docs/pruefkette/gitexport.py "$repo" origin/development "$base" > /dev/null
  git -C "$repo" ls-tree -r --name-only origin/development | sort > /tmp/ov_repo_$p.txt
  unzip -Z1 "$OUT/${p}_adele-$V.zip" | grep -v '/$' | sort > /tmp/ov_zip_$p.txt
  comm -23 /tmp/ov_repo_$p.txt /tmp/ov_zip_$p.txt > /tmp/ov_delete_$p.txt
  echo "== ${p}_adele: to delete in the repository (origin/development $(git -C "$repo" rev-parse --short=8 origin/development)): $(wc -l < /tmp/ov_delete_$p.txt)"
  sed 's/^/   /' /tmp/ov_delete_$p.txt
  unzip -q -o "$OUT/${p}_adele-$V.zip" -d "$base"
  if [ "$p" = local ]; then
    echo "   bundle gate, ZIP over development, nothing deleted:"
    (cd "$base/vue3" && node buildtools/check-bundle.js 2>&1 | sed 's/^/     /')
    while read -r f; do rm -f "$base/$f"; done < /tmp/ov_delete_$p.txt
    echo "   bundle gate, ZIP over development, the files above deleted:"
    if (cd "$base/vue3" && node buildtools/check-bundle.js 2>&1 | sed 's/^/     /'; exit "${PIPESTATUS[0]}"); then
      echo "   -> green"
    else
      echo "   -> RED with the deletions"; RC=1
    fi
  fi
  rm -rf "$base"
done
exit $RC
