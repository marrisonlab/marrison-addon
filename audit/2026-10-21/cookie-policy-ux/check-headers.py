from pathlib import Path
import html, json, re, urllib.request

results = []
for url in ['https://demo.ceris-srl.it/', 'https://darkblue-mongoose-141713.hostingersite.com/']:
    request = urllib.request.Request(url, headers={'User-Agent': 'Marrison Cookie Manager diagnostic', 'Cache-Control': 'no-cache'})
    with urllib.request.urlopen(request, timeout=20) as response:
        body = response.read(3_000_000).decode('utf8', errors='replace')
        results.append({'url': url, 'status': response.status, 'permissions_policy': response.headers.get_all('Permissions-Policy', []),
                        'iframes': re.findall(r'<iframe\b[^>]*\bsrc=["\']([^"\']+)', body, re.I)[:10]})
for item in list(results):
    for frame in item['iframes'][:1]:
        url = html.unescape(frame)
        with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=20) as response:
            results.append({'url': url, 'final_url': response.geturl(), 'status': response.status, 'permissions_policy': response.headers.get_all('Permissions-Policy', []), 'source': 'iframe embedded by ' + item['url']})
Path(__file__).with_name('headers.json').write_text(json.dumps(results, indent=2), encoding='utf8')
print(json.dumps(results, indent=2))
