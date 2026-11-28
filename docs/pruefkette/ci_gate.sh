#!/bin/bash
# Pre-delivery gate: the static part of Moodle Plugin CI, reproduced as the CI
# runs it (re-built 2026-11-23 after CI run 45... failed on codechecker and
# grunt although the old local mirror was green):
#
#  1. SAME TOOLCHAIN: moodle-plugin-ci is installed fresh on every run exactly
#     as the CI installs it (catalyst ci.yml: composer create-project
#     moodlehq/moodle-plugin-ci ^4, --no-dev). The CI resolves "^4" and its
#     dependencies (moodle-cs, PHP_CodeSniffer, symfony/*) anew on every run;
#     a toolchain installed once drifts away from it - the cause of the
#     codechecker miss (local moodle-plugin-ci 4.5.11 / moodle-cs 3.7.0 /
#     PHPCS 3.13.6, CI 4.5.12 / 3.9.2 / 4.0.4).
#  2. SAME INPUT: each plugin is staged as the CI's checkout is - the files
#     of the delivery (package.sh's exclusions) plus a git repository - at its
#     place in the Moodle tree, the test site's copy moved aside meanwhile.
#  3. SAME USER: the steps run as an unprivileged user (claude), as the CI
#     runner does; as root, permission problems stay invisible (the grunt
#     failure on read-only .git objects).
#  4. SAME STEPS AND OPTIONS, read from each plugin's moodle-plugin-ci.yml:
#     ignore paths (tests/fixtures + ignore_paths) and mustache names as the
#     CI's environment, codechecker --max-warnings, grunt unless disabled,
#     phpmd reported (the CI does not fail on it).
#
# Usage: /tmp/ci_gate.sh [local mod enrol]      exit 0 only if all steps pass.
set -u
# It moves the test site's plugins aside for a while: never during a browser run.
if pgrep -f "playwright test" >/dev/null; then
  echo "ci_gate.sh: a Playwright run is active - the gate would move its plugins away. Not now." >&2
  exit 3
fi
M=/home/claude/moodlefix
GH=/home/claude/gh
RUNAS=claude
TOOL=/tmp/mpci_gate
STAGEROOT=/tmp/ci_gate_workspace
STAGE=$STAGEROOT/plugin
ASIDE=/home/claude/ci_gate_aside
rc=0

echo "== toolchain: composer create-project moodlehq/moodle-plugin-ci ^4 (as the CI)"
rm -rf "$TOOL.new" /tmp/ci_gate_home; mkdir -p /tmp/ci_gate_home; chown $RUNAS /tmp/ci_gate_home
# As the runner user: as root, composer disables its plugins, and the
# installer plugin that registers the "moodle" standard with PHPCS never runs.
# Only the network settings are passed on (proxy, CA bundle) - no tokens.
# The CA bundle lives below /root, which the runner user cannot read: a copy.
NET=()
for v in HTTPS_PROXY https_proxy HTTP_PROXY http_proxy NO_PROXY no_proxy; do
  [ -n "${!v:-}" ] && NET+=("$v=${!v}")
done
if [ -n "${SSL_CERT_FILE:-}" ]; then
  install -m 0644 "$SSL_CERT_FILE" /tmp/ci_gate_ca.crt
  NET+=("SSL_CERT_FILE=/tmp/ci_gate_ca.crt" "CURL_CA_BUNDLE=/tmp/ci_gate_ca.crt" "GIT_SSL_CAINFO=/tmp/ci_gate_ca.crt")
fi
if ! (cd /tmp && runuser -u $RUNAS -- env "${NET[@]}" HOME=/tmp/ci_gate_home composer create-project -n --no-dev --prefer-dist \
      moodlehq/moodle-plugin-ci "$TOOL.new" ^4 > /tmp/ci_gate_composer.log 2>&1); then
  echo "FAIL toolchain: composer create-project failed - no stale fallback"; tail -20 /tmp/ci_gate_composer.log; exit 2
fi
rm -rf "$TOOL"; mv "$TOOL.new" "$TOOL"
grep -q "installed_paths set to" /tmp/ci_gate_composer.log || { echo "FAIL toolchain: coding standards not registered"; exit 2; }
python3 - "$TOOL" <<'PY'
import json, sys
lock = json.load(open(sys.argv[1] + '/composer.lock'))
want = ('moodlehq/moodle-cs', 'squizlabs/php_codesniffer', 'phpcsstandards/phpcsextra', 'phpmd/phpmd', 'symfony/filesystem')
print('   ' + ', '.join(f"{p['name']} {p['version']}" for p in lock['packages'] if p['name'] in want))
PY
echo "   $(php $TOOL/bin/moodle-plugin-ci --version)"
CI="$TOOL/bin/moodle-plugin-ci"

# Root-level files Moodle's grunt writes (ignorefiles) must be writable for the runner user.
for f in .eslintignore .stylelintignore phpcs.xml; do touch "$M/$f"; chown $RUNAS "$M/$f"; done

for p in ${@:-local mod enrol}; do
  case $p in local) type=local;; mod) type=mod;; enrol) type=enrol;; esac
  src=$GH/moodle-${p}_adele
  wf=$src/.github/workflows/moodle-plugin-ci.yml
  dest=$M/$type/adele
  # Options from the workflow, as the catalyst workflow passes them on.
  eval "$(python3 - "$wf" <<'PY'
import sys, yaml
w = yaml.safe_load(open(sys.argv[1]))
job = next(iter(w['jobs'].values()))
i = job.get('with', {}) or {}
ignore = ','.join(x for x in ['tests/fixtures'] + [s.strip() for s in str(i.get('ignore_paths', '') or '').split(',') if s.strip()])
print(f"IGNORE='{ignore}'")
print(f"MUSTACHE='{i.get('mustache_ignore_names', '') or ''}'")
print(f"IGNNAMES='{i.get('ignore_names', '') or ''}'")
print(f"CCIGNORE='{i.get('codechecker_ignore_paths', '') or ''}'")
print(f"MAXW='{i.get('codechecker_max_warnings', '') if i.get('codechecker_max_warnings', '') != '' else ''}'")
print(f"GRUNT='{'no' if i.get('disable_grunt') else 'yes'}'")
print(f"PHPMD='{'yes' if i.get('enable_phpmd') else 'no'}'")
# Lines of extra_plugin_runners that prepare the checkout (not the add-plugin
# lines: the other plugins are in the test site already).
prep = [l for l in str(i.get('extra_plugin_runners', '') or '').splitlines()
        if l.strip() and 'add-plugin' not in l and 'moodle-plugin-ci' not in l]
open('/tmp/ci_gate_prep.sh', 'w').write('set -e\n' + '\n'.join(prep) + '\n')
PY
)"
  # Stage the checkout: delivery files + git repository, owned by the runner user.
  rm -rf "$STAGEROOT"; mkdir -p "$STAGE"
  rsync -a --exclude .git --exclude node_modules --exclude test-results --exclude playwright-report --exclude coverage \
    --exclude blob-report --exclude .nyc_output "$src/" "$STAGE/"
  git -C "$STAGE" init -q && git -C "$STAGE" add -A && git -C "$STAGE" -c user.name=gate -c user.email=gate@localhost commit -qm stage && git -C "$STAGE" gc -q
  # The ignores reach the CI's commands through "moodle-plugin-ci install",
  # which turns IGNORE_PATHS / <COMMAND>_IGNORE_NAMES into .moodle-plugin-ci.yml.
  # (catalyst ci.yml: IGNORE_PATHS, IGNORE_NAMES, MUSTACHE_IGNORE_NAMES,
  # CODECHECKER_IGNORE_PATHS, PHPDOCCHECKER_IGNORE_PATHS)
  { echo "filter:"; echo "  notPaths: [${IGNORE//,/, }]"
    [ -n "$IGNNAMES" ] && echo "  notNames: [${IGNNAMES//,/, }]"
    [ -n "$MUSTACHE" ] && { echo "filter-mustache:"; echo "  notNames: [${MUSTACHE//,/, }]"; }
    [ -n "$CCIGNORE" ] && { echo "filter-codechecker:"; echo "  notPaths: [${CCIGNORE//,/, }]"; }
    # PHPDOCCHECKER_IGNORE_PATHS: no command of that name in moodle-plugin-ci
    # (it reads PHPDOC_IGNORE_PATHS), so it has no effect in the CI either.
  } > "$STAGE/.moodle-plugin-ci.yml"
  chown -R $RUNAS "$STAGEROOT"
  # The workflow's own preparation lines ("Install dependencies" step), run
  # where the CI runs them: GITHUB_WORKSPACE with the checkout in ./plugin.
  if [ -s /tmp/ci_gate_prep.sh ] && [ "$(wc -l < /tmp/ci_gate_prep.sh)" -gt 1 ]; then
    if ! (cd "$STAGEROOT" && runuser -u $RUNAS -- env GITHUB_WORKSPACE="$STAGEROOT" bash /tmp/ci_gate_prep.sh); then
      rc=1; echo "FAIL $p extra_plugin_runners (preparation lines)"
    else
      echo "ok   $p extra_plugin_runners: $(sed 1d /tmp/ci_gate_prep.sh | tr '\n' ';')"
    fi
  fi
  # "moodle-plugin-ci install" copies the checkout into Moodle keeping file
  # modes (cp -a), as the CI's Symfony mirror now does.
  # Outside the Moodle tree, where grunt (gherkinlint) would see it as a second plugin.
  mkdir -p "$ASIDE"; rm -rf "$ASIDE/$p"; mv "$dest" "$ASIDE/$p"
  cp -a "$STAGE" "$dest"
  steps="phplint"
  [ "$PHPMD" = yes ] && steps="$steps phpmd"
  steps="$steps codechecker phpdoc validate savepoints mustache"
  [ "$GRUNT" = yes ] && steps="$steps grunt"
  echo "== $p  (IGNORE_PATHS=$IGNORE${MUSTACHE:+, MUSTACHE_IGNORE_NAMES=$MUSTACHE}, codechecker --max-warnings=${MAXW:-default}, grunt=$GRUNT)"
  # The runner user owns the Moodle tree in the CI. moodle-plugin-ci 4.5.13
  # restores after grunt by removing the plugin directory itself, which needs
  # write access to its parent (2026-11-27).
  chown $RUNAS "$M/$type"
  for s in $steps; do
    args=""
    case $s in
      codechecker) [ -n "$MAXW" ] && args="--max-warnings=$MAXW";;
      phpmd|phpdoc|validate|mustache|grunt) args="--moodle=$M";;
    esac
    out=$(cd "$M" && runuser -u $RUNAS -- env HOME=/tmp/ci_gate_home \
      php "$CI" $s $args "$dest" 2>&1); r=$?
    if [ $s = phpmd ]; then
      echo "info $p phpmd (reported, not failing in CI): $(echo "$out" | grep -c VIOLATION) violations"
    elif [ $r -ne 0 ]; then
      rc=1; echo "FAIL $p $s"; echo "$out" | tail -40
    else
      echo "ok   $p $s"
    fi
  done
  rm -rf "$dest"; mv "$ASIDE/$p" "$dest"; chown root "$M/$type"
done
rm -rf "$STAGEROOT"
[ $rc -eq 0 ] && echo "GATE GREEN" || echo "GATE RED"
exit $rc
