#!/bin/bash
# Runs the steps of local_adele's jest.yml literally, in order, on a fresh copy
# of the plugin (as the checkout has it: no node_modules, no build output).
S=/tmp/claude-0/-home-claude/de0bba9f-befc-51ef-8a1d-618fae4c7428/scratchpad/jest_ci
rm -rf $S; mkdir -p $S
rsync -a --exclude .git --exclude node_modules --exclude test-results --exclude playwright-report --exclude coverage \
  --exclude blob-report --exclude .nyc_output /home/claude/gh/moodle-local_adele/ $S/repo/
python3 - "$S" <<'PY'
import yaml, subprocess, sys
S=sys.argv[1]
steps=yaml.safe_load(open(f'{S}/repo/.github/workflows/jest.yml'))['jobs']['jest']['steps']
failed=False
for st in steps:
    if 'run' not in st: continue
    cond=st.get('if')
    if failed and cond is None:
        print(f"SKIP  {st['name']}"); continue
    wd=f"{S}/repo/"+st.get('working-directory','.')
    r=subprocess.run(['bash','-e','-c',st['run']],cwd=wd,capture_output=True,text=True,env={**__import__('os').environ,**st.get('env',{}),'CI':'true'})
    out=(r.stdout+r.stderr).strip().splitlines()
    print(f"{'OK  ' if r.returncode==0 else 'FAIL'}  {st['name']} (exit {r.returncode})")
    for l in [l for l in out if 'Tests:' in l or 'Test Suites:' in l or 'error' in l.lower() and 'npm warn' not in l][:6]: print('      '+l[:200])
    if r.returncode: failed=True
print('ALLDONE')
sys.exit(1 if failed else 0)
PY
