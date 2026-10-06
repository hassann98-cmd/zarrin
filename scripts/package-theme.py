#!/usr/bin/env python3
"""Deterministic source + runtime theme ZIP. Run build/tests first; never pack ignored files or credentials."""
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import subprocess
import tempfile
import time
import zipfile

root = Path(__file__).resolve().parents[1]
os.chdir(root)
version = json.loads((root / 'package.json').read_text())['version']
package_lock = json.loads((root / 'package-lock.json').read_text())
if package_lock.get('version') != version or package_lock.get('packages', {}).get('', {}).get('version') != version:
    raise SystemExit('package-lock.json root versions must match package.json before packaging.')
manifest = root / 'assets/compiled/manifest.json'
if not manifest.is_file():
    raise SystemExit('Missing build: run npm ci && npm run build first.')
paths = set(subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z']).decode().split('\0'))
epoch = int(os.environ.get('SOURCE_DATE_EPOCH', subprocess.check_output(['git', 'show', '-s', '--format=%ct', 'HEAD']).decode().strip()))
stamp = time.gmtime(max(315532800, epoch))[:6]
destination = root / 'artifacts' / f'zarrin-{version}.zip'
download_destination = root / 'downloads' / f'jluxe-mobile-nav-{version}.zip'
destination.parent.mkdir(exist_ok=True)
download_destination.parent.mkdir(exist_ok=True)
excluded = ('.git/', '.github/', 'node_modules/', 'dist/', 'artifacts/', 'downloads/', '.cache/', 'coverage/', '.arena/')
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
    for required in ('style.css', 'functions.php', 'assets/compiled/manifest.json', 'src/main.js', 'package.json', 'package-lock.json'):
        if 'zarrin/' + required not in names:
            raise SystemExit('Release missing ' + required)
    if any(name.startswith('zarrin/dist/') for name in names):
        raise SystemExit('Release contains the obsolete legacy dist/ build; use assets/compiled/.')
    if any(not name.startswith('zarrin/') for name in names):
        raise SystemExit('Release contains a path outside the zarrin/ theme directory.')
    for name in names:
        path = PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts:
            raise SystemExit('Release contains an unsafe archive path: ' + name)
    corrupt = archive.testzip()
    if corrupt is not None:
        raise SystemExit('Corrupt ZIP member: ' + corrupt)

    # Verify the archive after extraction, not just the in-memory source tree/CRC.
    with tempfile.TemporaryDirectory(prefix='zarrin-zip-verify-') as unpacked:
        archive.extractall(unpacked)
        extracted_theme = Path(unpacked) / 'zarrin'
        if not (extracted_theme / 'assets/compiled/manifest.json').is_file():
            raise SystemExit('Extracted release is missing its Vite manifest.')
        extracted_version = json.loads((extracted_theme / 'package.json').read_text()).get('version')
        extracted_lock = json.loads((extracted_theme / 'package-lock.json').read_text())
        stylesheet = (extracted_theme / 'style.css').read_text()
        if extracted_lock.get('version') != version or extracted_lock.get('packages', {}).get('', {}).get('version') != version:
            raise SystemExit('Extracted package-lock.json root versions do not match the release version.')
        if extracted_version != version or f'Version: {version}\n' not in stylesheet:
            raise SystemExit('Extracted package/style versions do not match the release version.')
        extracted_manifest = json.loads((extracted_theme / 'assets/compiled/manifest.json').read_text())
        for entry_name, entry in extracted_manifest.items():
            for reference in (*entry.get('imports', []), *entry.get('dynamicImports', [])):
                if reference not in extracted_manifest:
                    raise SystemExit(f'Extracted manifest dependency is missing: {entry_name} -> {reference}')
            for key in ('file', 'css', 'assets'):
                references = entry.get(key, [])
                if isinstance(references, str):
                    references = [references]
                for reference in references:
                    asset_path = PurePosixPath('assets/compiled') / reference
                    if asset_path.is_absolute() or '..' in asset_path.parts or not (extracted_theme / asset_path).is_file():
                        raise SystemExit(f'Extracted manifest reference is missing/unsafe: {entry_name} -> {reference}')
        if (extracted_theme / 'dist').exists():
            raise SystemExit('Extracted release contains dist/; the current runtime bundle is assets/compiled/.')
        if len(names) != len(set(names)):
            raise SystemExit('Release contains duplicate archive paths.')

# Publish the exact validated archive to the download server's watched directory.
# Replace atomically so a concurrent request cannot stream a half-copied ZIP.
# The preview server reads package.json on each request, so no restart is needed.
temporary_download = download_destination.with_name(f'.{download_destination.name}.{os.getpid()}.tmp')
try:
    shutil.copyfile(destination, temporary_download)
    with zipfile.ZipFile(temporary_download) as download_archive:
        if download_archive.testzip() is not None:
            raise SystemExit('The download-server ZIP copy failed CRC verification.')
        download_version = json.loads(download_archive.read('zarrin/package.json')).get('version')
        if download_version != version:
            raise SystemExit('The download-server ZIP copy does not match package.json.')
    os.replace(temporary_download, download_destination)
finally:
    temporary_download.unlink(missing_ok=True)

print(f'Release verified: {destination.relative_to(root)} ({len(names)} files; {destination.stat().st_size} bytes; extracted manifest/assets checked)')
print(f'Download server updated: {download_destination.relative_to(root)}')
