from pathlib import Path
import json, subprocess, sys

root=Path(__file__).resolve().parents[3]
wp='C:/Users/Angelo/Local Sites/tesy/app/public'
commands=[
    ['php','tests/cookie-engine-php-test.php'],
    ['php','tests/cookie-shortcodes-php-test.php',wp],
    ['php','tests/cookie-policy-php-test.php'],
    ['php','tests/cookie-consent-php-test.php'],
    ['php','tests/cookie-consent-store-php-test.php'],
    ['node','tests/cookie-runtime-js-test.js'],
    ['node','tests/cookie-background-ui-js-test.js'],
    ['node','tests/cookie-receipts-ui-js-test.js'],
    ['php','tests/cookie-auto-scan-php-test.php'],
    ['php','tests/cookie-update-notice-php-test.php',wp],
    ['php','tests/module-lifecycle-php-test.php',wp],
]
base=root/'marrison-addon/includes/modules/cookie-manager'
for folder in ['includes','templates']:
    for path in sorted((base/folder).glob('*.php')): commands.append(['php','-l',str(path)])
for name in ['admin.js','wizard.js','scanner.js','frontend.js']: commands.append(['node','--check',str(base/'assets/js'/name)])
results=[]
for cmd in commands:
    response=subprocess.run(cmd,cwd=root,capture_output=True,text=True,encoding='utf8',errors='replace')
    results.append({'command':cmd,'exit_code':response.returncode,'output':response.stdout+response.stderr})
    if response.returncode: print('FAIL: '+' '.join(cmd)+'\n'+response.stdout+response.stderr)
Path(__file__).with_name('local-verification.json').write_text(json.dumps(results,indent=2,ensure_ascii=False)+'\n',encoding='utf8')
failed=[r for r in results if r['exit_code']]
print(f'{len(results)-len(failed)}/{len(results)} focused checks passed; {len(failed)} failures.')
sys.exit(bool(failed))
