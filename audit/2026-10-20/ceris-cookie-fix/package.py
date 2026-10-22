"""Validate release metadata/readmes and package plugin sources only."""
from pathlib import Path
import hashlib
import json
import re
import zipfile

workspace = Path(__file__).resolve().parents[3]
plugin = workspace / 'marrison-addon'
version = '1.3.71'
main = (plugin / 'marrison-addon.php').read_text(encoding='utf-8')
markdown = (plugin / 'README.md').read_text(encoding='utf-8')
overview = (workspace / 'README.md').read_text(encoding='utf-8')
readme = (plugin / 'readme.txt').read_text(encoding='utf-8')
updater = (plugin / 'includes/class-marrison-addon-updater.php').read_text(encoding='utf-8')
assert re.search(r'^\s*\* Version:\s*' + re.escape(version) + r'\s*$', main, re.M)
assert "const VERSION = '" + version + "'" in main
assert '**Stable tag:** ' + version in markdown
assert '**Version:** ' + version in overview
assert 'Stable tag: ' + version in readme
assert 'Author: Angelo Marra' in main
assert '**Author:** Angelo Marra' in markdown
assert '[Angelo Marra](https://marrisonlab.com)' in overview and '[Angelo Marra](https://marrisonlab.com)' in readme
for required in ('Requires at least: 6.0', 'Requires PHP: 7.4', 'License: GPL-3.0-or-later'):
    assert required in main and required in readme
assert '**Tested up to:** 7.1.3' in markdown
assert 'Tested up to: 7.1\n' in readme
assert updater.count("'7.1.3'") == 2
assert readme.startswith('=== Marrison Addon ===\n')
headers, body = readme.split('\n\n', 1)
assert len(re.search(r'^Tags: (.*)$', headers, re.M).group(1).split(',')) <= 5
assert len(body.split('\n', 1)[0]) <= 150
assert len(readme.encode('utf-8')) < 10_000
for section in ('Description', 'Installation', 'Frequently Asked Questions', 'Changelog', 'Upgrade Notice'):
    assert '\n== ' + section + ' ==\n' in readme
assert '= ' + version + ' =' in readme and '### ' + version in markdown and '### ' + version in overview
for documentation in (readme, markdown, overview):
    assert '8.5.11' in documentation and '7.1.3' in documentation
files = sorted(p for p in plugin.rglob('*') if p.is_file() and p.name != 'AGENTS.md')
output = workspace / 'marrison-addon.zip'
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for file in files:
        archive.write(file, 'marrison-addon/' + file.relative_to(plugin).as_posix())
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(files)
    for file in files:
        assert archive.read('marrison-addon/' + file.relative_to(plugin).as_posix()) == file.read_bytes()
result = {
    'version': version, 'files': len(files), 'bytes': output.stat().st_size,
    'sha256': hashlib.sha256(output.read_bytes()).hexdigest(),
    'all_members_match_source': True, 'archive_integrity_verified': True,
    'metadata_and_readme_checks': 'PASS', 'wordpress_readme_bytes': len(readme.encode('utf-8')),
    'wordpress_target': '7.1.3', 'php_target': '8.5.11', 'author': 'Angelo Marra',
}
Path(__file__).with_name('package-results.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
print(json.dumps(result, indent=2))
