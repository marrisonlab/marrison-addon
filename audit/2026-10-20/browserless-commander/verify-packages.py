from pathlib import Path
import json,hashlib,re,zipfile
audit=Path(__file__).resolve().parent
root=audit.parents[2]
attachment=Path(r'C:\Users\Angelo\.codex\attachments\26a1103b-7f21-40da-8224-f231c2a4e7ec\Testo incollato.txt')
secret=re.search(r'```\s*\n([A-Za-z0-9]{35,80})\s*\n```',attachment.read_text(encoding='utf-8-sig')).group(1).encode()
commander=Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander')
for name,base,prefix in [(audit/'commander-cookie-browserless-1.5.13.zip',audit/'commander',''),(root/'marrison-addon.zip',root/'marrison-addon','marrison-addon/')]:
    with zipfile.ZipFile(name) as archive:
        assert archive.testzip() is None
        for member in archive.namelist():
            content=archive.read(member)
            assert secret not in content,'Secret unexpectedly included'
            assert member.startswith(prefix)
            relative=member[len(prefix):]
            assert content==(base/relative).read_bytes()
            if not prefix: assert content==(commander/relative).read_bytes()
actual=json.loads((audit/'browserless-ceris-direct.json').read_text());actual=actual.get('data',actual)
assert actual['consent_verified'] and not actual['issues']
assert len(actual['cookies'])==3 and all('value' not in cookie for cookie in actual['cookies'])
assert any('google.com/maps' in r['url'] for r in actual['resources'])
result={'package_integrity_and_source_parity':'PASS','real_token_absent_from_both_packages':True,'local_commander_matches_package':True,
    'real_browserless_ceris_test':{'consent_verified':True,'observed_cookies':3,'observed_third_party_cookies':0,'issues':0,'maps_resources_loaded':True},
    'wordpress_commander_end_to_end':'pending'}
live=json.loads((audit/'ceris-remote-final-report.json').read_text())
job=live['report']; runtime=live['runtime']
assert runtime['plugin_version']=='1.3.72' and runtime['revision']=='20261020.7'
assert runtime['engine_hash']==hashlib.sha256((root/'marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php').read_bytes()).hexdigest()
assert runtime['scanner_hash']==hashlib.sha256((root/'marrison-addon/includes/modules/cookie-manager/includes/class-cookie-scanner.php').read_bytes()).hexdigest()
assert job['mode']=='commander' and job['status']=='completed' and job['http_done']==10
assert job['browser_cursor']==20 and job['browser_failed']==0 and job['issues_total']==0
assert len(job['remote_requests'])==10 and all(request['consent_verified'] for request in job['remote_requests'])
assert len(job['cookies'])==3 and all(cookie['cookie_domain']=='demo.ceris-srl.it' for cookie in job['cookies'].values())
assert any(cookie['http_only'] for cookie in job['cookies'].values())
assert all(all(observation['method']=='remote_browser' for observation in cookie['observations']) for cookie in job['cookies'].values())
assert all('value' not in cookie for cookie in job['cookies'].values())
assert 'remote_proof' not in job and 'remote_claim' not in job and secret not in (audit/'ceris-remote-final-report.json').read_bytes()
status=json.loads((audit/'commander-live-status.json').read_text())
assert status['status']['body']['enabled'] is True and status['invalid_proof']['http']==403
result['wordpress_commander_end_to_end']={'status':'PASS','http_samples':10,'browser_checks':20,'remote_requests':10,'issues':0,'consent_confirmed_every_page':True,'observed_cookies':3,'observed_third_party_cookies':0,'cookie_values_stored':False,'local_deployed_source_parity':True,'elapsed_seconds':job['updated']-job['created']}
(audit/'verification-results.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
