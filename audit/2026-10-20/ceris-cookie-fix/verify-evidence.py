"""Check deployed evidence against the final local scanner and installable archive."""
from pathlib import Path
import hashlib
import json
import zipfile

directory = Path(__file__).resolve().parent
workspace = directory.parents[2]
final = json.loads((directory / 'live-final-report.json').read_text(encoding='utf-8-sig'))
previous = json.loads((directory / 'live-second-report.json').read_text(encoding='utf-8-sig'))
expected = {'marrison_cookie_state', 'marrison_cookie_consent', 'marrison_cookie_categories'}
for evidence in (previous, final):
    report = evidence['report']
    cookies = list(report['cookies'].values())
    assert report['status'] == 'completed' and report['http_done'] == 10
    assert report['browser_cursor'] == 20 and len(report['response_pages']) == 20
    assert report['issues_total'] == 0 and report['browser_failed'] == 0
    assert len(cookies) == 3 and {item['cookie_name'] for item in cookies} == expected
    assert all('value' not in item for item in cookies)
    assert all(observation['phase'] == 'after' for item in cookies for observation in item['observations'])
    assert next(item for item in cookies if item['cookie_name'] == 'marrison_cookie_state')['http_only'] is True
    maps = report['services']['maps.google.com']
    assert maps['cookies_verified'] is False
    declaration = maps['profiles']['google-maps']['cookies'][0]
    assert declaration['name'] == 'NID' and declaration['observed'] is False
    assert declaration['evidence'] == 'provider_documentation'

engine = workspace / 'marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php'
assert final['runtime']['revision'] == '20261020.6'
assert final['runtime']['plugin_version'] == '1.3.71'
assert final['runtime']['engine_hash'] == hashlib.sha256(engine.read_bytes()).hexdigest()
policy = (directory / 'live-policy.txt').read_text(encoding='utf-8')
assert all(name in policy for name in expected)
assert 'Servizi rilevati e cookie documentati' in policy and 'NID' in policy
archive_path = workspace / 'marrison-addon.zip'
manifest = json.loads((directory / 'package-results.json').read_text(encoding='utf-8'))
assert manifest['sha256'] == hashlib.sha256(archive_path.read_bytes()).hexdigest()
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None and len(archive.namelist()) == manifest['files']
    for name in archive.namelist():
        assert archive.read(name) == (workspace / name).read_bytes()

result = {'result': 'PASS', 'deployed_version': '1.3.71', 'deployed_revision': '20261020.6',
          'completed_real_runs_with_three_cookies': 2, 'http_pages_per_run': 10,
          'browser_checks_per_run': 20, 'captured_response_pages_per_run': 20,
          'cookie_names': sorted(expected), 'third_party_cookie_observations': 0,
          'maps_declaration_is_not_an_observation': True, 'duplicates': 0,
          'cookie_values_stored': False, 'live_engine_matches_local_source': True,
          'archive_matches_source': True, 'archive_sha256': manifest['sha256']}
(directory / 'verification-results.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
print(json.dumps(result, indent=2))
