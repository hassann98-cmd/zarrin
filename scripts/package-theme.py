#!/usr/bin/env python3
"""Deterministic source + runtime theme ZIP. Run build/tests first; never pack ignored files or credentials."""
import json
import os
from pathlib import Path
import subprocess
import time
import zipfile

root = Path(__file__).resolve().parents[1]
os.chdir(root)
version = json.loads((root / 'package.json').read_text())['version']
manifest = root / 'assets/compiled/manifest.json'
if not manifest.is_file():
    raise SystemExit('Missing build: run npm ci && npm run build first.')
paths = set(subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z']).decode().split('\0'))
epoch = int(os.environ.get('SOURCE_DATE_EPOCH', subprocess.check_output(['git', 'show', '-s', '--format=%ct', 'HEAD']).decode().strip()))
stamp = time.gmtime(max(315532800, epoch))[:6]
destination = root / 'artifacts' / f'zarrin-{version}.zip'
destination.parent.mkdir(exist_ok=True)
excluded = ('.git/', '.github/', 'node_modules/', 'artifacts/', '.cache/', 'coverage/')
with zipfile.ZipFile(destination, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for name in sorted(paths):
        if not name or name.startswith(excluded) or any(part in ('.env', '.git-credentials', '.netrc') or part.startswith('.env.') for part in Path(name).parts):
            continue
        source = root / name
        if source.is_symlink():
            raise SystemExit(f'Refusing symlink in release: {name}')
        if not source.is_file():
            continue
        info = zipfile.ZipInfo('zarrin/' + name, stamp)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
with zipfile.ZipFile(destination) as archive:
    names = archive.namelist()
    for required in ('style.css', 'functions.php', 'assets/compiled/manifest.json', 'src/main.js', 'package-lock.json'):
        if 'zarrin/' + required not in names:
            raise SystemExit('Release missing ' + required)
    if archive.testzip() is not None:
        raise SystemExit('Corrupt ZIP')
print(f'Release verified: {destination.relative_to(root)} ({len(names)} files; {destination.stat().st_size} bytes)')
