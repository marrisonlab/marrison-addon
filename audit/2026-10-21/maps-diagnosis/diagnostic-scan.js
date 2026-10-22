// Executed by Browserless Function API, never in a WordPress visitor's browser.
export default async ({ page, context }) => {

    const diagnosis = {responses:0, response_cookie_names:[], blocked_cookies:[], cdp_events:0, targets:[], errors:[]};
    const sessions=[];
    const attach = async target => {
        try {
            const session=await target.createCDPSession(); sessions.push(session);
            session.on('Network.responseReceivedExtraInfo', event => {
                diagnosis.cdp_events++;
                for (const cookie of event.blockedCookies || []) diagnosis.blocked_cookies.push({name:cookie.cookie?.name || cookie.cookieLine?.split('=')[0],domain:cookie.cookie?.domain || '',reasons:cookie.blockedReasons});
            });
            await session.send('Network.enable');
            diagnosis.targets.push(target.type());
        } catch { diagnosis.errors.push('CDP attachment unavailable'); }
    };
    await attach(page.target());
    const targetCreated=target=>{attach(target);};
    page.browser().on('targetcreated',targetCreated);
    page.on('response', async response=>{
        diagnosis.responses++;
        const url=new URL(response.url());
        const headers=await response.headers();
        for(const [key,value] of Object.entries(headers)) if(key.toLowerCase()==='set-cookie') {
            for(const line of String(value).split('\n')) diagnosis.response_cookie_names.push({name:line.split('=')[0],host:url.hostname,status:response.status()});
        }
    });

    const target = new URL(context.url);
    const origin = target.origin;
    const browserContext = page.browserContext();
    const resources = new Map(), observed = new Map(), issues = [];
    let phase = 'before', accepted = false, blocked = 0;
    const addresses = new Map();
    const publicIP = ip => {
        if (/^\d+\.\d+\.\d+\.\d+$/.test(ip)) {
            const [a, b] = ip.split('.').map(Number);
            return !(a === 0 || a === 10 || a === 127 || a >= 224 || (a === 169 && b === 254) ||
                (a === 172 && b >= 16 && b <= 31) || (a === 192 && (b === 168 || b === 0)) ||
                (a === 100 && b >= 64 && b <= 127) || (a === 198 && (b === 18 || b === 19)));
        }
        // Global unicast only; explicitly excludes mapped IPv4 and local IPv6.
        return ip.includes(':') && /^[23]/i.test(ip) && !/^2001:db8:/i.test(ip);
    };
    const safeHost = async host => {
        host = host.replace(/^\[|\]$/g, '').toLowerCase();
        if (addresses.has(host)) return addresses.get(host);
        const pending = (async () => {
            if (!host.includes('.') || host.endsWith('.local') || host.endsWith('.internal') || host === 'localhost') return false;
            try {
                if (/^[\d.]+$/.test(host) || host.includes(':')) return publicIP(host);
                // Function API has browser-compatible imports, not Node's DNS module.
                // Public DNS JSON API requires no credential; fail closed on lookup errors.
                const answers = await Promise.all(['A', 'AAAA'].map(async type => {
                    const response = await fetch('https://dns.google/resolve?name=' + encodeURIComponent(host) + '&type=' + type);
                    if (!response.ok) throw new Error('DNS non disponibile');
                    const data = await response.json();
                    if (data.Status !== 0 && data.Status !== 3) throw new Error('DNS non valido');
                    return (data.Answer || []).filter(a => a.type === 1 || a.type === 28).map(a => a.data);
                }));
                const ips = answers.flat(); return ips.length > 0 && ips.every(publicIP);
            }
            catch { return false; }
        })();
        addresses.set(host, pending);
        return pending;
    };
    if (target.protocol !== 'https:' || target.username || target.password || target.port || !(await safeHost(target.hostname))) throw new Error('URL pubblico non valido.');
    await page.setViewport({width: 1366, height: 900});
    await page.setRequestInterception(true);
    const onRequest = async request => {
        try {
            const url = new URL(request.url());
            if (url.protocol === 'data:' || url.protocol === 'blob:') { await request.continue(); return; }
            if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password || url.port || !(await safeHost(url.hostname))) { blocked++; await request.abort(); return; }
            if (request.isNavigationRequest() && request.frame() === page.mainFrame() && url.origin !== origin) { blocked++; await request.abort(); return; }
            const kind = request.resourceType();
            if (url.origin !== origin && resources.size < 300) resources.set(url.origin + url.pathname, {url: url.origin + url.pathname, kind: {document:'iframe', image:'img', stylesheet:'style', xhr:'request', fetch:'request'}[kind] || kind});
            await request.continue();
        } catch { try { await request.abort(); } catch {} }
    };
    page.on('request', onRequest);
    const sample = async () => {
        const cookies = await browserContext.cookies();
        for (const c of cookies) {
            const domain = c.domain.replace(/^\./, '').toLowerCase();
            const hosts = [target.hostname, ...Array.from(resources.values(), r => new URL(r.url).hostname)];
            if (!hosts.some(host => host === domain || host.endsWith('.' + domain))) continue;
            const key = JSON.stringify([c.name, domain, c.path, phase]);
            if (observed.size >= 1000 && !observed.has(key)) { if (!issues.includes('Limite di 1000 osservazioni raggiunto.')) issues.push('Limite di 1000 osservazioni raggiunto.'); continue; }
            // Whitelist fields. In particular, NEVER return c.value.
            observed.set(key, {cookie_name:c.name, cookie_domain:domain, cookie_path:c.path, expires:c.expires > 0 ? c.expires : null,
                secure:!!c.secure, http_only:!!c.httpOnly, same_site:(c.sameSite || 'unknown').toLowerCase(), phase,
                partitioned:!!c.partitionKey, observed_at:Date.now()});
        }
    };
    const settle = async milliseconds => {
        const end = Date.now() + milliseconds;
        while (Date.now() < end) { await sample(); await new Promise(resolve => setTimeout(resolve, 500)); }
        await sample();
    };
    const scroll = async () => {
        await page.evaluate(async () => {
            const height = Math.min(document.documentElement.scrollHeight, 18000);
            for (let y = 0; y < height; y += 800) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 70)); }
            window.scrollTo(0, 0);
        });
    };
    try {
        const response = await page.goto(target.href, {waitUntil:'domcontentloaded', timeout:12000});
        if (!response || response.status() >= 400) throw new Error('La pagina non ha risposto correttamente.');
        await scroll();
        await settle(4000);
        // Use only Marrison's explicit consent control, never submit site forms.
        const button = await page.$('#marrison-accept-all');
        if (button) {
            await button.click();
            const end = Date.now() + 5000;
            while (Date.now() < end) {
                accepted = await page.evaluate(() => document.cookie.split(';').some(c => c.trim() === 'marrison_cookie_consent=accept_all'));
                if (accepted) break;
                await new Promise(r => setTimeout(r, 300));
            }
        }
        if (!accepted) issues.push('Consenso Marrison non confermato: fase successiva al consenso non verificata.');
        else {
            phase = 'after';
            const responseAfter = await page.reload({waitUntil:'domcontentloaded', timeout:12000});
            if (!responseAfter || responseAfter.status() >= 400) throw new Error('Ricaricamento dopo il consenso non riuscito.');
            await scroll();
            // Start only embedded YouTube videos already present in the page.
            for (const frame of page.frames()) {
                if (!/^https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com)\/embed\//i.test(frame.url())) continue;
                try { const play = await frame.$('.ytp-large-play-button'); if (play) await play.click(); }
                catch { issues.push('Riproduzione del video YouTube non confermata.'); }
            }
            await settle(5000);
        }
        if (blocked) issues.push('Richieste non pubbliche o navigazioni fuori sito bloccate: ' + blocked + '.');
        diagnosis.frames=page.frames().map(frame=>{const url=new URL(frame.url());return url.origin+url.pathname;});
        try { const all=await sessions[0].send('Network.getAllCookies'); diagnosis.cdp_cookie_names=all.cookies.map(cookie=>({name:cookie.name,domain:cookie.domain})); } catch { diagnosis.errors.push('CDP cookie query unavailable'); }
        return {data:{diagnosis,protocol:1, engine:'commander_browserless', url:target.href, consent_verified:accepted,
            cookies:Array.from(observed.values()), resources:Array.from(resources.values()), issues}, type:'application/json'};
    } finally {
        page.browser().off('targetcreated',targetCreated);
        for(const session of sessions) await session.detach().catch(()=>{});
        page.off('request', onRequest);
        // Browserless closes the request browser. Explicitly close this page too.
        await page.close().catch(() => {});
    }
};
