from pathlib import Path
import re

base = Path(__file__).parent
html = (base / 'live-page.html').read_text(encoding='utf-8')
html = re.sub(r'<script\b[^>]*>.*?</script>', lambda m: '' if
              (re.search(r'/plugins/marrison-addon/|id=[\"\']marrison|marrisonCookie', m.group(0), re.I)
               and 'horizontal-scroll.js' not in m.group(0)) else m.group(0), html, flags=re.S | re.I)
html = re.sub(r'https://www\.marrisonlab\.com/wp-content/plugins/marrison-addon/assets/(css|js)/horizontal-scroll\.(css|js)\?[^\"\']+',
              r'/wp-content/plugins/marrison-addon/assets/\1/horizontal-scroll.\2?followup=1', html)
html = html.replace('</head>', '<style>html{scroll-behavior:auto!important}</style></head>')
controls = '''<div style="position:fixed;bottom:0;left:0;z-index:99999;background:white;color:black">
<button id="audit-entry">Entry 1px</button><button id="audit-aligned">Parent aligned</button><button id="audit-end">End</button></div>
<script>function auditStage(step){var root=document.querySelector('[data-marrison-horizontal-scroll]'),scene=root.closest('.marrison-horizontal-scroll-scene')||root,top=scene.getBoundingClientRect().top+scrollY;window.scrollTo(0,top+(step==='entry'?-innerHeight+1:step==='end'?scene.offsetHeight:0));} ['entry','aligned','end'].forEach(function(step){document.getElementById('audit-'+step).onclick=function(){auditStage(step);};});window.addEventListener('load',function(){requestAnimationFrame(function(){requestAnimationFrame(function(){auditStage(new URLSearchParams(location.search).get('auditStage')||'aligned');});});});</script>'''
html = html.replace('</body>', controls + '</body>')
(base / 'continuous.html').write_text(html, encoding='utf-8')
(base / 'snap.html').write_text(html.replace('&quot;snap&quot;:false','&quot;snap&quot;:true'), encoding='utf-8')
wrapper='''<!doctype html><html><head><meta charset="utf-8"><title>Horizontal wide viewport follow-up</title><style>body{font:16px Arial;margin:0}.frame{height:380px}iframe{border:0;transform:scale(.49);transform-origin:top left}</style></head><body>
<h2>Continuous 2540 x 737</h2><div class="frame"><iframe id="continuous" title="Continuous wide" width="2540" height="737" src="/marrison-hs-followup-continuous.html"></iframe></div>
<h2>Snap 2540 x 737</h2><div class="frame"><iframe id="snap" title="Snap wide" width="2540" height="737" src="/marrison-hs-followup-snap.html"></iframe></div>
<h2>Snap 1280 x 900</h2><div style="height:460px"><iframe id="fit" title="Snap fit" width="1280" height="900" src="/marrison-hs-followup-snap.html"></iframe></div>
</body></html>'''
(base / 'frames.html').write_text(wrapper, encoding='utf-8')
(base / 'entry-frames.html').write_text(wrapper.replace('followup-continuous.html','followup-continuous.html?auditStage=entry').replace('followup-snap.html','followup-snap.html?auditStage=entry'), encoding='utf-8')
(base / 'proof.html').write_text('''<!doctype html><html><head><meta charset="utf-8"><title>Snap wide viewport verification</title><style>body{font:16px Arial;margin:0}iframe{border:0;transform:scale(.49);transform-origin:top left}</style></head><body>
<button onclick="document.getElementById('proof').width=390;document.getElementById('proof').height=844">Mobile 390</button>
<button onclick="document.getElementById('proof').width=2540;document.getElementById('proof').height=737">Wide 2540</button>
<button onclick="var frame=document.getElementById('proof');frame.width=2540;frame.height=1200;frame.src='/marrison-hs-followup-continuous.html';document.getElementById('audit-caption').textContent='Continuous: viewport 2540 x 1200 (scaled view), boxed slide at rest'">Continuous boxed</button>
<p id="audit-caption">Snap: viewport 2540 x 737 (scaled view), first slide at parent alignment</p>
<iframe id="proof" title="Snap wide proof" width="2540" height="737" src="/marrison-hs-followup-snap.html"></iframe></body></html>''', encoding='utf-8')
print('Three follow-up fixtures generated')
