"""Compare actual cookie and storage keys on existing public content, no test content."""
from pathlib import Path
import json,re,urllib.request,urllib.error,time
root=Path(__file__).resolve().parent
attachment=Path(r'C:\Users\Angelo\.codex\attachments\26a1103b-7f21-40da-8224-f231c2a4e7ec\Testo incollato.txt')
token=re.search(r'```\s*\n([A-Za-z0-9]{35,80})\s*\n```',attachment.read_text(encoding='utf-8-sig')).group(1)
with urllib.request.urlopen('https://urbevigilanza.it/',timeout=15) as response: target=response.geturl()
body={'code':(root/'commander/app/Services/cookie-scan.js').read_text(),'context':{'url':target}}
request=urllib.request.Request('https://production-sfo.browserless.io/function?timeout=45000&token='+token,data=json.dumps(body).encode(),headers={'Content-Type':'application/json'},method='POST')
start=time.time()
try:
 with urllib.request.urlopen(request,timeout=55) as response:
  raw=json.loads(response.read());data=raw.get('data',raw)
  assert all('value' not in entry for entry in data['cookies']+data['storage'])
  (root/'urbe-storage-observations.json').write_text(json.dumps(raw,indent=2))
  print(json.dumps({'http':response.status,'seconds':round(time.time()-start,1),'cookies':[(c['cookie_name'],c['cookie_domain']) for c in data['cookies']],'storage':data['storage'],'issues':data['issues']},indent=2))
except urllib.error.HTTPError as error:
 print(json.dumps({'http':error.code,'diagnostic':error.read().decode(errors='replace').replace(token,'[redacted]')[:1000]}))
