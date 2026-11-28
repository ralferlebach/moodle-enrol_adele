#!/usr/bin/env python3
"""Write the full tree of a commit into a directory, read only (ignores export-ignore).

    gitexport.py <git-dir> <ref> <target-dir>
"""
import os
import subprocess
import sys

gitdir, ref, target = sys.argv[1:4]
out = subprocess.run(['git', '-C', gitdir, 'ls-tree', '-r', '-z', ref], check=True, capture_output=True).stdout
count = 0
for entry in out.split(b'\0'):
    if not entry:
        continue
    meta, path = entry.split(b'\t', 1)
    mode, kind, sha = meta.decode().split()
    if kind != 'blob':
        continue
    data = subprocess.run(['git', '-C', gitdir, 'cat-file', 'blob', sha], check=True, capture_output=True).stdout
    dest = os.path.join(target, path.decode())
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    with open(dest, 'wb') as fh:
        fh.write(data)
    if mode == '100755':
        os.chmod(dest, 0o755)
    count += 1
print(f'{count} files of {ref} written to {target}')
