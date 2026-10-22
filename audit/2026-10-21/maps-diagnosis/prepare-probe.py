from pathlib import Path
root=Path(__file__).resolve().parents[3]
source=(root/'audit/2026-10-20/browserless-commander/commander/app/Services/cookie-scan.js').read_text()
instrument='''
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
            for(const line of String(value).split('\\n')) diagnosis.response_cookie_names.push({name:line.split('=')[0],host:url.hostname,status:response.status()});
        }
    });
'''
source=source.replace('    const target = new URL(context.url);',instrument+'\n    const target = new URL(context.url);',1)
source=source.replace('        return {data:{protocol:1,', '''        diagnosis.frames=page.frames().map(frame=>{const url=new URL(frame.url());return url.origin+url.pathname;});
        try { const all=await sessions[0].send('Network.getAllCookies'); diagnosis.cdp_cookie_names=all.cookies.map(cookie=>({name:cookie.name,domain:cookie.domain})); } catch { diagnosis.errors.push('CDP cookie query unavailable'); }
        return {data:{diagnosis,protocol:1,''',1)
source=source.replace("        page.off('request', onRequest);", "        page.browser().off('targetcreated',targetCreated);\n        for(const session of sessions) await session.detach().catch(()=>{});\n        page.off('request', onRequest);")
(Path(__file__).with_name('diagnostic-scan.js')).write_text(source)
