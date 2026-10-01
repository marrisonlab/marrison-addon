"""Create a separate installable package and verify every member against the source."""
from pathlib import Path
import hashlib
import json
import zipfile

workspace = Path(__file__).resolve().parents[3]
plugin = workspace / 'marrison-addon'
output = workspace / 'marrison-addon-1.3.44.zip'
existing = workspace / 'marrison-addon.zip'
if output.exists():
    raise SystemExit('The separate package already exists; refusing to overwrite it.')
original_sha = hashlib.sha256(existing.read_bytes()).hexdigest() if existing.exists() else None
files = sorted(p for p in plugin.rglob('*') if p.is_file() and p.name != 'AGENTS.md')
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for file in files:
        archive.write(file, 'marrison-addon/' + file.relative_to(plugin).as_posix())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert 'marrison-addon/marrison-addon.php' in archive.namelist()
    assert len(archive.namelist()) == len(files)
    for file in files:
        name = 'marrison-addon/' + file.relative_to(plugin).as_posix()
        assert archive.read(name) == file.read_bytes(), name
assert not existing.exists() or hashlib.sha256(existing.read_bytes()).hexdigest() == original_sha
result = {'version': '1.3.44', 'path': str(output), 'files': len(files), 'bytes': output.stat().st_size,
          'sha256': hashlib.sha256(output.read_bytes()).hexdigest(), 'main_entry_verified': True,
          'all_members_match_source': True, 'existing_zip_unchanged': True, 'existing_zip_sha256': original_sha}
Path(__file__).with_name('package-results.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result, indent=2))
