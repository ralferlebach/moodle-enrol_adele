#!/bin/bash
# Full browser stand, CI-like: every suite starts from a freshly seeded site
# (CI builds a new site per workflow). Smoke before E2E per plugin.
summ() { sed "s/\x1b\[[0-9;]*[A-Za-z]//g" "$1" | grep -aE "^\s+[0-9]+ (passed|failed|flaky|skipped|did not run)|^exit|^\s+[0-9]+\) "; }
fresh() { bash /tmp/seed.sh > /tmp/seed_all.log 2>&1; (cd /home/claude/moodlefix && php -r 'define("CLI_SCRIPT",1);require("config.php");unset_config("lastreport","enrol_adele");'); }
# Keep each suite's test-results (screenshots, videos): the next suite of the
# same plugin would otherwise overwrite them (2026-11-27, M2 video lost).
keep() { local d; d=/home/claude/moodlefix/$(case $1 in local) echo local/adele;; mod) echo mod/adele;; enrol) echo enrol/adele;; esac)/tests/$2/test-results;
  rm -rf /tmp/results_$1_$3; [ -d "$d" ] && cp -r "$d" /tmp/results_$1_$3; }
for p in local mod enrol; do
  fresh; echo "== smoke $p"; bash /tmp/run_spec.sh $p playwright "" > /dev/null; cp /tmp/spec_run.out /tmp/pw_$p.out; summ /tmp/pw_$p.out; keep $p playwright smoke
  fresh; echo "== e2e $p chromium"; bash /tmp/run_spec.sh $p e2e "" "" chromium > /dev/null; cp /tmp/spec_run.out /tmp/e2e_$p.out; summ /tmp/e2e_$p.out; keep $p e2e chromium
  if [ $p != enrol ]; then echo "== e2e $p firefox"; ADELE_E2E_FIREFOX=1 bash /tmp/run_spec.sh $p e2e "" "" firefox > /dev/null; cp /tmp/spec_run.out /tmp/e2e_${p}_ff.out; summ /tmp/e2e_${p}_ff.out; keep $p e2e firefox; fi
done
echo ALLDONE
