from pathlib import Path
import re,json,hashlib,shutil,zipfile
audit=Path(__file__).resolve().parent
root=audit.parents[2]
plugin=root/'marrison-addon'
commander=Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander')
version='1.3.73'
for relative in ['marrison-addon.php','README.md','readme.txt']:
    path=plugin/relative;s=path.read_text(encoding='utf8')
    if '### '+version in s or 'Stable tag: '+version in s or "const VERSION = '"+version+"'" in s: continue
    if relative.endswith('.php'):
        s=s.replace('Version: 1.3.72','Version: '+version).replace("const VERSION = '1.3.72'", "const VERSION = '"+version+"'")
    elif relative=='README.md':
        s=s.replace('**Stable tag:** 1.3.72','**Stable tag:** '+version)
        lines=s.splitlines()
        for i,line in enumerate(lines):
            if line.startswith('    *   HTTP response cookies are observed'):
                lines[i]='    *   Remote scans run in bounded server batches through signed asynchronous loopbacks, with persistent leases and a WP-Cron watchdog. The administrator may close the scanner, return to the dashboard or continue the wizard. Reopening either view restores the current server state without sessionStorage. Failed dispatch/provider requests pause visibly and can be resumed. Local iframe measurements still require an open panel.'
            if line.startswith('    *   The observed inventory records actual cookies'):
                lines[i]='    *   The remote browser observes cookie metadata across origins, including HttpOnly cookies, before and after real consent. Separate localStorage/sessionStorage inventories contain key names and origin only, never values, and do not increase cookie counts. Known service declarations remain separate from actual observations. Page/resource/storage caps and interactions not executed limit coverage; a completed sample plan is not an exhaustive browser audit.'
            if line.startswith('    *   Automatic scans use WP-Cron'):
                lines[i]='    *   Automatic scans respect the configured interval (1–30 days, default 7). Remote jobs continue through chained server requests after their initial scheduled start; WP-Cron repairs stalled work. The host must allow same-site loopback requests; failures are shown explicitly. The local method leaves browser work pending. Disabling the module removes cron and excludes worker handlers/assets.'
        s='\n'.join(lines)+'\n'
        s=s.replace('### 1.3.72','### '+version+'\n* **Cookie Manager:** Server background processing continues after closing the scanner or wizard. Persistent leases, signed loopbacks, watchdog recovery, pause/resume and dashboard progress notice; wizard may advance immediately without reloading.\n* **Scan coverage:** Observe localStorage/sessionStorage key names and origins separately from cookies, with no values; recover both inventories in new tabs. Requires the small Commander storage patch.\n\n### 1.3.72',1)
    else:
        s=s.replace('Stable tag: 1.3.72','Stable tag: '+version).replace('and keep the panel open for the browser measurements.','and start the remote scan; you may then leave the panel or continue the wizard.')
        s=s.replace('Only cookie metadata is returned;', 'Cookie metadata and localStorage/sessionStorage key names and origins are returned;')
        s=s.replace('== Changelog ==','== Changelog ==\n\n= '+version+' =\n* Run remote scans in signed server background batches with leases and a cron watchdog. Continue the wizard or leave the scanner after starting; reopen to see saved progress. Show errors and support resume.\n* Add a separate observed browser-storage inventory, including Elementor and WordPress emoji keys, without reading values or counting them as cookies. Requires the Commander storage patch.\n',1)
        s=re.sub(r'= 1\.3\.68 =\n.*?(?=\n== Upgrade Notice ==)', '',s,flags=re.S)
        s=s.replace('== Upgrade Notice ==','== Upgrade Notice ==\n\n= '+version+' =\nRemote scans continue in the background. Upload the Commander storage patch to also list localStorage/sessionStorage keys separately from cookies.\n',1)
        before,upgrade=s.split('== Upgrade Notice ==',1);upgrade=upgrade.split('\n= 1.3.72 =',1)[0];s=before+'== Upgrade Notice =='+upgrade+'\n'
    path.write_text(s,encoding='utf8',newline='\n')
path=root/'README.md';s=path.read_text(encoding='utf8').replace('**Version:** 1.3.72','**Version:** '+version)
if '### '+version not in s: s=s.replace('### 1.3.72','### '+version+'\n* Remote Cookie Manager scans continue in server background batches after closing the page or continuing the wizard. Persistent progress, signed workers, pause/resume and a cron watchdog.\n* Browser-storage key names/origins are inventoried separately from cookies; values are excluded. Requires the Commander storage patch.\n\n### 1.3.72',1)
path.write_text(s,encoding='utf8',newline='\n')

baseline={}
for relative in ['app/Services/cookie-scan.js','README.md','CHANGELOG.md']:
    source=commander/relative;baseline[relative]=hashlib.sha256(source.read_bytes()).hexdigest()
    if relative.endswith('.js'): continue
    s=source.read_text(encoding='utf8')
    if relative=='README.md':
        s=s.replace('Versione corrente: `1.5.13`.','Versione corrente: `1.5.14`.').replace('### Scanner cookie Browserless','### Archivio browser scanner (1.5.14)\n\nLa patch aggiunge le chiavi localStorage/sessionStorage, senza valori, ai risultati\nCookie Manager 1.3.73. Guida: `docs/COOKIE-STORAGE-1.5.14.md`. Nessuna migrazione.\n\n### Scanner cookie Browserless',1)
    else:
        s=s.replace('## [1.5.13]','## [1.5.14] - 2026-10-21\n\n- Il worker Browserless restituisce le chiavi localStorage/sessionStorage osservate nei frame, prima/dopo consenso. Solo nomi, tipo e origine: nessun valore. Inventario distinto dai cookie, senza ampliare i tempi di attesa.\n- Compatibile con l’endpoint scanner 1.5.13, nessuna migrazione o nuova chiave. Il background è gestito da Addon 1.3.73 sul sito WordPress.\n\n## [1.5.13]',1)
    target=audit/'commander'/relative;target.parent.mkdir(parents=True,exist_ok=True);target.write_text(s,encoding='utf8',newline='\n')
(audit/'commander-baseline.json').write_text(json.dumps(baseline,indent=2))
shutil.copyfile(root/'audit/2026-10-20/browserless-commander/package-addon.py',audit/'package-addon.py')
path=audit/'package-addon.py';path.write_text(path.read_text().replace("version = '1.3.72'", "version = '1.3.73'"))
print(json.dumps({'version':version,'wordpress_readme_bytes':(plugin/'readme.txt').stat().st_size}))
