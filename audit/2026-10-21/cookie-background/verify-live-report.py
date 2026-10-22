"""Verify exported live evidence; no network or browser interactions."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib, json, zipfile

root = Path(__file__).resolve().parents[3]
audit = Path(__file__).resolve().parent
export = json.loads((audit / 'ceris-background-final.json').read_text(encoding='utf8'))
timeline = json.loads((audit / 'ceris-background-timeline.json').read_text(encoding='utf8'))
job = export['report']
runtime = export['runtime']
engine = root / 'marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php'
assert runtime['plugin_version'] == '1.3.73'
assert runtime['revision'] == job['revision'] == '20261021.1'
assert runtime['engine_hash'] == hashlib.sha256(engine.read_bytes()).hexdigest()
scanner = engine.parent / 'class-cookie-scanner.php'
assert runtime['scanner_hash'] == hashlib.sha256(scanner.read_bytes()).hexdigest()
assert job['status'] == 'completed'
assert job['worker']['state'] == 'finished'
assert job['worker']['lease_until'] == 0
assert not job['worker']['error']
assert job['browser_cursor'] == len(job['pages']) * 2 > 0
assert job['storage_supported'] is True
assert 'remote_proof' not in job and 'remote_claim' not in job
assert any(entry['key'] == 'wpEmojiSettingsSupports' and entry['type'] == 'sessionStorage' for entry in job['storage'].values())
assert all('value' not in entry for entry in job['storage'].values())
closed = datetime.fromisoformat(timeline['closed_at'].replace('Z', '+00:00')).timestamp()
reopened = datetime.fromisoformat(timeline['reopened_at'].replace('Z', '+00:00')).timestamp()
assert closed < job['updated'] < reopened - 5
assert job['worker']['heartbeat'] < reopened - 5
with zipfile.ZipFile(root / 'marrison-addon.zip') as archive:
    assert archive.testzip() is None
    for name in archive.namelist():
        if not name.endswith('/'):
            assert archive.read(name) == (root / name).read_bytes()
result = {
    'site': 'https://demo.ceris-srl.it/',
    'version': runtime['plugin_version'],
    'engine_revision': runtime['revision'],
    'deployed_engine_matches_local_sha256': True,
    'completed_before_new_tab_opened': True,
    'completed_utc': datetime.fromtimestamp(job['updated'], timezone.utc).isoformat(),
    'tab_closed_utc': timeline['closed_at'],
    'new_tab_opened_utc': timeline['reopened_at'],
    'background_worker_finished': True,
    'browser_checks': job['browser_cursor'],
    'observed_cookies': len(job.get('cookies', {})),
    'observed_storage_keys': len(job['storage']),
    'storage_supported_by_deployed_commander': True,
    'issues': len(job.get('issues', [])),
    'archive_source_parity': True,
}
(audit / 'live-verification.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf8')
print(json.dumps(result, indent=2))
