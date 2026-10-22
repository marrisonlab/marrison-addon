from pathlib import Path
import hashlib, json, shutil

audit = Path(__file__).resolve().parent
target = Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander\public\admin\cookie-scanner.php')
baseline = json.loads((audit / 'baseline.json').read_text())['sha256']
assert hashlib.sha256(target.read_bytes()).hexdigest() == baseline, 'The source changed: inspect before overwriting.'
shutil.copyfile(audit / 'cookie-scanner.php', target)
assert target.read_bytes() == (audit / 'cookie-scanner.php').read_bytes()
print('PASS: only public/admin/cookie-scanner.php updated in local Commander checkout.')
