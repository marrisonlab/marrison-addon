"""One actual scan of existing CERIS content. Reads supplied credential in memory only."""
import re,json,urllib.request,urllib.error,time
from pathlib import Path
root=Path(__file__).resolve().parent
attachment=Path(r'C:\Users\Angelo\.codex\attachments\26a1103b-7f21-40da-8224-f231c2a4e7ec\Testo incollato.txt')
token=re.search(r'```\s*\n([A-Za-z0-9]{35,80})\s*\n```',attachment.read_text(encoding='utf-8-sig')).group(1)
body={'code':(root/'commander/app/Services/cookie-scan.js').read_text(), 'context':{'url':'https://demo.ceris-srl.it/contatti/'}}
request=urllib.request.Request('https://production-sfo.browserless.io/function?timeout=45000&token='+token,data=json.dumps(body).encode(),headers={'Content-Type':'application/json'},method='POST')
start=time.time()
try:
 with urllib.request.urlopen(request,timeout=55) as response:
  result=json.loads(response.read()); (root/'browserless-ceris-direct.json').write_text(json.dumps(result,indent=2))
  data=result.get('data',result)
  print(json.dumps({'status':response.status,'seconds':round(time.time()-start,1),'cookies':[{'name':c['cookie_name'],'domain':c['cookie_domain'],'phase':c['phase']} for c in data.get('cookies',[])],'issues':data.get('issues'),'consent_verified':data.get('consent_verified')}))
except urllib.error.HTTPError as error:
 diagnostic=error.read().decode(errors='replace').replace(token,'[redacted]')
 print(json.dumps({'status':error.code,'diagnostic':diagnostic[:1500]}))
except Exception as error:
 print(type(error).__name__+': remote verification failed')
