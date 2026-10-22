"""Build the installable 1.3.67 archive and verify every member against source."""
from pathlib import Path
import hashlib
import json
import zipfile

workspace = Path(__file__).resolve().parents[2]
plugin = workspace / 'marrison-addon'
output = workspace / 'marrison-addon.zip'
assert "const VERSION = '1.3.67'" in (plugin / 'marrison-addon.php').read_text(encoding='utf-8')
assert '**Stable tag:** 1.3.67' in (plugin / 'README.md').read_text(encoding='utf-8')
files = sorted(p for p in plugin.rglob('*') if p.is_file() and p.name != 'AGENTS.md')
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for file in files:
        archive.write(file, 'marrison-addon/' + file.relative_to(plugin).as_posix())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(files)
    for file in files:
        member = 'marrison-addon/' + file.relative_to(plugin).as_posix()
        assert archive.read(member) == file.read_bytes(), member
result = {
    'version': '1.3.67', 'files': len(files), 'bytes': output.stat().st_size,
    'sha256': hashlib.sha256(output.read_bytes()).hexdigest(),
    'all_members_match_source': True, 'archive_integrity_verified': True,
}
Path(__file__).with_name('package-results.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
print(json.dumps(result, indent=2))
