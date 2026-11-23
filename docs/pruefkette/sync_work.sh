#!/bin/bash
# Mirror the work copies (git clones of ralferlebach development) into the test site.
set -e
M=/home/claude/moodlefix
for p in local enrol mod; do
  src=/home/claude/gh/moodle-${p}_adele
  mkdir -p $M/$p/adele
  rsync -a --delete --exclude .git --exclude node_modules --exclude test-results --exclude playwright-report --exclude coverage \
    --exclude 'tests/e2e/node_modules' --exclude 'tests/playwright/node_modules' --exclude 'vue3/node_modules' "$src/" "$M/$p/adele/"
done
bash /tmp/start_server.sh >/dev/null
