#!/usr/bin/env python3
"""Compare a plugin ZIP with a commit of its repository, byte for byte.

    repovergleich.py <git-dir> <ref> <zip> <title>

Reads the repository only (git cat-file on a fetched ref), changes nothing.
Prints: files in the repository, in the ZIP, byte-equal; to delete in the
repository (there, not in the ZIP); new (in the ZIP, not there); changed.
Paths the ZIP never carries (.git) are not compared.
"""
import subprocess
import sys
import zipfile


def repo_files(gitdir, ref):
    out = subprocess.run(['git', '-C', gitdir, 'ls-tree', '-r', '-z', ref],
                         check=True, capture_output=True).stdout
    files = {}
    for entry in out.split(b'\0'):
        if not entry:
            continue
        meta, path = entry.split(b'\t', 1)
        mode, kind, sha = meta.split()
        if kind == b'blob':
            files[path.decode()] = sha.decode()
    return files


def blob(gitdir, sha):
    return subprocess.run(['git', '-C', gitdir, 'cat-file', 'blob', sha],
                          check=True, capture_output=True).stdout


def main():
    gitdir, ref, zippath, title = sys.argv[1:5]
    commit = subprocess.run(['git', '-C', gitdir, 'rev-parse', ref], check=True,
                            capture_output=True, text=True).stdout.strip()
    repo = repo_files(gitdir, ref)
    with zipfile.ZipFile(zippath) as z:
        inzip = {n: z for n in z.namelist() if not n.endswith('/')}
        zipdata = {n: z.read(n) for n in inzip}
    same, changed = [], []
    for path in sorted(set(repo) & set(zipdata)):
        (same if blob(gitdir, repo[path]) == zipdata[path] else changed).append(path)
    delete = sorted(set(repo) - set(zipdata))
    new = sorted(set(zipdata) - set(repo))
    print(f'{title}: Repository {commit[:8]} ({ref}, nur lesend) gegen {zippath.split("/")[-1]}')
    print(f'Commit: {commit}')
    print(f'Dateien im Repository: {len(repo)}, im ZIP: {len(zipdata)}, byte-gleich: {len(same)}')
    for label, items in (('Zu löschen im Repository (im Repository, nicht im ZIP)', delete),
                         ('Neu', new), ('Geändert', changed)):
        print(f'\n{label}: {len(items)}')
        for item in items:
            print(f'  {item}')


if __name__ == '__main__':
    main()
