from pathlib import Path
import hashlib, json, zipfile

audit = Path(__file__).resolve().parent
before = (audit / 'cookie-scanner.before.php').read_bytes()
patched = (audit / 'cookie-scanner.php').read_bytes()
assert before.split(b"$activePage = 'settings';")[0] == patched.split(b"$activePage = 'settings';")[0]
assert b'width: 16px; height: 16px' in patched
assert b'max-width: 220px' in patched
assert b'overflow-wrap: anywhere' in patched
assert b'initial-scale=1' in patched
assert b'aria-describedby="monthly-units-help"' in patched
archive_path = audit / 'commander-cookie-settings-layout.zip'
with zipfile.ZipFile(archive_path, 'w', zipfile.ZIP_DEFLATED) as archive:
    archive.writestr('public/admin/cookie-scanner.php', patched)
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None
    assert archive.namelist() == ['public/admin/cookie-scanner.php']
    assert archive.read(archive.namelist()[0]) == patched
result = {'files': 1, 'php_config_handler_unchanged': True, 'sha256': hashlib.sha256(patched).hexdigest(), 'zip_sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest(), 'archive_integrity_and_source_parity': True}
(audit / 'verification.json').write_text(json.dumps(result, indent=2) + '\n')
print(json.dumps(result, indent=2))
