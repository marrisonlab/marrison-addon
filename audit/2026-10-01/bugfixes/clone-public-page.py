from pathlib import Path
import re

base = Path(__file__).parent
html = (base / 'live-page.html').read_text(encoding='utf-8')
# Keep the public Elementor document and its real runtime, but disable unrelated
# Marrison modules. This is a disposable local copy, never a published page.
html = re.sub(r'<script\b[^>]*>.*?</script>', lambda m: '' if
              (re.search(r'/plugins/marrison-addon/|id=[\"\']marrison|marrisonCookie', m.group(0), re.I)
               and 'horizontal-scroll.js' not in m.group(0))
              else m.group(0), html, flags=re.S | re.I)
html = re.sub(r'https://www\.marrisonlab\.com/wp-content/plugins/marrison-addon/assets/(css|js)/horizontal-scroll\.(css|js)\?[^\"\']+',
              r'/wp-content/plugins/marrison-addon/assets/\1/horizontal-scroll.\2?regression=1', html)
cookie_css = '/wp-content/plugins/marrison-addon/includes/modules/cookie-manager/assets/css/frontend.css?regression=1'
html = re.sub(r'https://www\.marrisonlab\.com/wp-content/plugins/marrison-addon/includes/modules/cookie-manager/assets/css/frontend\.css\?[^\"\']+', cookie_css, html)
html = html.replace('</head>', '<style>html{scroll-behavior:auto!important}</style></head>')
(base / 'public-clone.html').write_text(html, encoding='utf-8')
print('Local public document copy generated')
