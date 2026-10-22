from pathlib import Path
import hashlib,json,re,shutil,zipfile

root=Path(__file__).resolve().parents[3]
audit=Path(__file__).resolve().parent
commander=Path(r'C:\Users\Angelo\Documents\GitHub\marrison-commander\commander')
stage=audit/'commander'
baseline={}

def stage_existing(relative, transform):
    source=commander/relative
    baseline[relative]=hashlib.sha256(source.read_bytes()).hexdigest()
    text=source.read_text(encoding='utf-8')
    revised=transform(text)
    assert revised != text,relative
    destination=stage/relative
    destination.parent.mkdir(parents=True,exist_ok=True)
    destination.write_text(revised,encoding='utf-8',newline='\n')

stage_existing('public/admin/settings.php', lambda s:s.replace('    <div class="mc-card">', '    <div class="mc-card"><h3>Scanner cookie Browserless</h3><p>Servizio pubblico per Cookie Manager, senza licenze. <a href="cookie-scanner.php">Configura scanner e quota gratuita</a>.</p></div>\n\n    <div class="mc-card">',1))
stage_existing('README.md',lambda s:s.replace('Versione corrente: `1.5.12`.', 'Versione corrente: `1.5.13`.').replace('### Verifica programmata MCU', '### Scanner cookie Browserless (1.5.13)\n\nServizio pubblico per Marrison Addon 1.3.72, senza licenze o registrazione dei siti.\nApri Impostazioni > Scanner cookie Browserless dopo avere caricato la patch.\nLa chiave viene cifrata solo su Commander. Guida: `docs/COOKIE-BROWSERLESS-1.5.13.md`.\n\n### Verifica programmata MCU',1))
stage_existing('CHANGELOG.md',lambda s:s.replace('## [1.5.12]', '## [1.5.13] - 2026-10-20\n\n- Servizio pubblico Browserless per Cookie Manager: verifica automatica della scansione attiva, nessuna licenza, chiave cifrata centralmente e solo cookie osservati, senza valori.\n- Una pagina per chiamata, prima/dopo consenso, video YouTube incorporati, timeout 45 secondi, riserva massima di due unità per pagina, budget mensile e limiti giornalieri/concorrenza.\n- Retry della stessa pagina dalla cache senza una seconda chiamata al fornitore, URL pubblici, DNS/TLS verificati, redirect bloccati e JSON protetto. Nessuna migrazione database.\n\n## [1.5.12]',1))
(audit/'commander-baseline.json').write_text(json.dumps(baseline,indent=2)+'\n')

version='1.3.72'
for relative in ['marrison-addon/marrison-addon.php','marrison-addon/README.md','README.md','marrison-addon/readme.txt']:
    path=root/relative
    text=path.read_text(encoding='utf-8')
    if relative.endswith('.php'):
        text=text.replace('Version: 1.3.71','Version: '+version).replace("const VERSION = '1.3.71'", "const VERSION = '"+version+"'")
    elif relative.endswith('readme.txt'):
        text=text.replace('Stable tag: 1.3.71','Stable tag: '+version)
        text=text.replace('Third-party HttpOnly cookies and interactions that require filling forms or signing in require separate verification.', 'Remote scanning can observe third-party and HttpOnly cookies; local scanning cannot. Form submissions, sign-ins and conditional cookies require separate verification.')
        external='Cookie scanner: when Cookie Manager is enabled and a remote scan is requested, public sample URLs and an expiring scan proof go to www.marrisonlab.com/commander. Commander uses production-sfo.browserless.io to browse before/after consent and start existing YouTube embeds. Only cookie metadata is returned; WordPress credentials and cookie values are excluded. DNS checks use dns.google. The Browserless key stays on Commander. Remote automatic scans use the same service if enabled. [Browserless terms](https://www.browserless.io/terms-of-service), [privacy](https://www.browserless.io/privacy-policy); Google terms/privacy above apply to DNS. Local scanning remains selectable.\n\n'
        text=text.replace('Cookie scans request public same-site pages;',external+'Cookie scans request public same-site pages;')
        text=text.replace('== Changelog ==\n','== Changelog ==\n\n= '+version+' =\n* Add remote browser scans through Commander/Browserless, including third-party and HttpOnly observations, real before/after consent checks and existing YouTube playback. Keep documented cookies separate.\n* Add expiring site proof, quota/concurrency controls, safe retries and explicit provider errors. No license or provider key on WordPress.\n',1)
        text=text.replace('== Upgrade Notice ==\n','== Upgrade Notice ==\n\n= '+version+' =\nInstall and enable Commander scanner patch 1.5.13, then run a remote cookie scan. Local scanning remains available with limited coverage.\n',1)
        # The WordPress readme keeps current changes; the complete history is on GitHub.
        text=re.sub(r'= 1\.3\.67 =\n.*?(?=\n== Upgrade Notice ==)', '', text, flags=re.S)
        before,upgrade=text.split('== Upgrade Notice ==',1)
        upgrade=upgrade.split('\n= 1.3.71 =',1)[0]
        text=before+'== Upgrade Notice =='+upgrade+'\n'
    else:
        text=text.replace('**Stable tag:** 1.3.71','**Stable tag:** '+version).replace('**Version:** 1.3.71','**Version:** '+version)
        heading='### 1.3.71'
        assert heading in text,relative
        text=text.replace(heading,'### '+version+'\n* **Cookie Manager:** Remote browser scanning through Commander and Browserless observes first-party, third-party and HttpOnly cookies without collecting values. Checks real consent before/after and starts existing YouTube embeds. No Commander license or Browserless key is required on WordPress.\n* **Reliability:** Expiring site proof, provider preflight, independent page requests, bounded quota/concurrency, idempotent retries and explicit errors. Local scanning remains available with limited coverage; disabled Cookie Manager loads no scanner or network hooks.\n\n'+heading,1)
    path.write_text(text,encoding='utf-8',newline='\n')

shutil.copyfile(root/'audit/2026-10-20/ceris-cookie-fix/package.py',audit/'package-addon.py')
path=audit/'package-addon.py'
path.write_text(path.read_text().replace("version = '1.3.71'", "version = '1.3.72'"))
print(json.dumps({'commander_existing_files':list(baseline),'plugin_version':version,'wordpress_readme_bytes':(root/'marrison-addon/readme.txt').stat().st_size}))
