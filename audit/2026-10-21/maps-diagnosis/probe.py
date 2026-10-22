import re,json,urllib.request,urllib.error,time
from pathlib import Path
root=Path(__file__).resolve().parent
attachment=Path(r'C:\Users\Angelo\.codex\attachments\26a1103b-7f21-40da-8224-f231c2a4e7ec\Testo incollato.txt')
token=re.search(r'```\s*\n([A-Za-z0-9]{35,80})\s*\n```',attachment.read_text(encoding='utf-8-sig')).group(1)
body={'code':(root/'diagnostic-scan.js').read_text(), 'context':{'url':'https://demo.ceris-srl.it/contatti/'}}
request=urllib.request.Request('https://production-sfo.browserless.io/function?timeout=45000&token='+token,data=json.dumps(body).encode(),headers={'Content-Type':'application/json'},method='POST')
start=time.time()
try:
 with urllib.request.urlopen(request,timeout=55) as response:
  result=json.loads(response.read());(root/'maps-diagnosis.json').write_text(json.dumps(result,indent=2))
  data=result.get('data',result)
  print(json.dumps({'http':response.status,'seconds':round(time.time()-start,1),'diagnosis':data.get('diagnosis'),'cookie_names':[(c['cookie_name'],c['cookie_domain']) for c in data.get('cookies',[])],'issues':data.get('issues')},indent=2))
except urllib.error.HTTPError as error:
 diagnostic=error.read().decode(errors='replace').replace(token,'[redacted]');print(json.dumps({'status':error.code,'diagnostic':diagnostic[:1000]}))
except Exception as error: print(type(error).__name__+': diagnostic failed')
