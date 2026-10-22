// Node VM fixture only. This does not control a browser or claim live YouTube proof.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, 'commander/app/Services/cookie-scan.js'), 'utf8').replace('export default', 'scan =');
async function run(injectPrivate) {
    let clock = 10000, phase = 'before', consent = false, played = false, closed = false;
    let requestListener, evaluations = 0;
    const seenRequests = [];
    const frame = {url: () => 'https://www.youtube.com/embed/existing', $: async selector => ({click: async () => {assert.equal(selector, '.ytp-large-play-button'); played = true;}})};
    const mainFrame = {};
    const page = {
        browserContext: () => ({cookies: async () => consent ? [
            {name:'marrison_cookie_state', domain:'.site.example', path:'/', httpOnly:true, value:'SECRET_RECEIPT', expires:9999999999, secure:true, sameSite:'Lax'},
            ...(played ? [{name:'YSC', domain:'.youtube.com', path:'/', httpOnly:true, value:'SECRET_YOUTUBE', expires:-1, secure:true, sameSite:'None'}] : [])
        ] : [{name:'early', domain:'site.example', path:'/', expires:-1, value:'SECRET_EARLY'}]}),
        setViewport: async () => {}, setRequestInterception: async value => assert.equal(value,true),
        on: (event, listener) => {assert.equal(event,'request');requestListener=listener;},
        off: (event, listener) => assert.equal(listener,requestListener),
        mainFrame: () => mainFrame,
        goto: async () => {
            await request('https://site.example/',true);
            await request('https://www.youtube.com/embed/existing?private=SECRET_QUERY',false);
            if (injectPrivate) await request('http://169.254.169.254/latest/meta-data/',false);
            return {status: () => 200};
        },
        reload: async () => {phase='after';return {status: () => 200};},
        evaluate: async fn => {evaluations++;return fn.toString().includes('document.cookie') ? consent : undefined;},
        $: async selector => {assert.equal(selector,'#marrison-accept-all');return {click:async () => {consent=true;}};},
        frames: () => [frame], close: async () => {closed=true;}
    };
    async function request(url,isMain) {
        const entry={url,aborted:false,continued:false};seenRequests.push(entry);
        await requestListener({url:()=>url, isNavigationRequest:()=>isMain, frame:()=>isMain?mainFrame:frame,
            resourceType:()=> 'document', abort:async()=>{entry.aborted=true;}, continue:async()=>{entry.continued=true;}});
    }
    class Clock extends Date { static now() {return clock;} }
    const sandbox={URL,Date:Clock,setTimeout:fn=>{clock+=500;fn();},fetch:async url=>({ok:true,json:async()=>({Status:0,Answer:url.includes('type=AAAA')?[]:[{type:1,data:'93.184.216.34'}]})})};
    vm.createContext(sandbox);new vm.Script(source).runInContext(sandbox);
    const result = await sandbox.scan({page,context:{url:'https://site.example/'}});
    assert.equal(result.data.consent_verified,true);
    assert.equal(played,true);assert.equal(closed,true);assert.ok(evaluations>=3);
    const ysc=result.data.cookies.find(c=>c.cookie_name==='YSC');
    assert.equal(ysc.cookie_domain,'youtube.com');assert.equal(ysc.http_only,true);assert.equal(ysc.phase,'after');
    assert.equal(result.data.cookies.find(c=>c.cookie_name==='early').phase,'before');
    assert.equal(JSON.stringify(result).includes('SECRET'),false);
    assert.equal(result.data.resources[0].url,'https://www.youtube.com/embed/existing');
    if(injectPrivate) {assert.equal(seenRequests.at(-1).aborted,true);assert.equal(result.data.issues.length,1);}
    else assert.equal(result.data.issues.length,0);
}
(async()=>{await run(false);await run(true);console.log('PASS Browserless function fixture: whole-context third-party HttpOnly cookies, consent phases, YouTube playback, value/query redaction, page cleanup and private-resource rejection.');})().catch(error=>{console.error(error);process.exitCode=1;});
