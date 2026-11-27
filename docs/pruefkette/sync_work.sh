#!/bin/bash
# Refuse while a browser run is going: this script restarts the web server
# (rule since 2026-11-23, broken twice - now enforced, 2026-11-26).
if pgrep -f "playwright test" >/dev/null && [ "${SYNC_DURING_E2E:-}" != "I know" ]; then
  echo "sync_work.sh: a Playwright run is active - not syncing (the server restart would break it)." >&2
  exit 3
fi
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
