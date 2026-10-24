// Coordinator state machine fixtures, no browser automation.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const code=fs.readFileSync(path.join(__dirname,'../marrison-addon/includes/modules/cookie-manager/assets/js/scanner.js'),'utf8');
function fixture() {
    const timers=[],delays=[],cleared=[],actions=[],events=[],statuses=[],frames=[];let reloads=0,storageWrites=0;
    const document={getElementById(){return {appendChild(){}};},createElement(){
        const frame={listeners:{},setAttribute(){},addEventListener(name,fn){this.listeners[name]=fn;},remove(){this.removed=true;},style:{}};
        frames.push(frame);return frame;
    }},states=new Map();
    const query=selector=>{
        if(!states.has(selector))states.set(selector,{props:{},classes:{},text:''});
        const state=states.get(selector);
        return {length:typeof selector==='string'&&(selector.includes('wizard_cookie_table_body')||selector.includes('marrison-cookie-table-body'))?1:0,
            on(){return this;},trigger(event,args){events.push([event,args]);return this;},
            val(value){if(arguments.length){state.value=value;return this;}return selector==='#marrison-scan-mode'?'commander':'';},
            prop(key,value){state.props[key]=value;return this;},toggleClass(key,value){state.classes[key]=value;return this;},
            text(value){state.text=value;return this;},empty(){return this;},children(){return {length:0};},show(){return this;},remove(){return this;}};
    };
    const sandbox={jQuery:query,document,window:{marrisonCookieScanner:{},location:{reload(){reloads++;}},addEventListener(){}},
        sessionStorage:{getItem(){return null;},setItem(){storageWrites++;},removeItem(){}},setTimeout(fn,delay){timers.push(fn);delays.push(delay);return timers.length;},clearTimeout(id){cleared.push(id);},console};
    vm.createContext(sandbox);new vm.Script(code).runInContext(sandbox);
    const engine=sandbox.window.MarrisonCookieScanner;
    const render=engine.render;
    engine.render=function(job){this.job=job;};engine.status=(message,failed)=>statuses.push([message,failed]);engine.loadInventory=async()=>actions.push('inventory');
    return {engine,actions,events,statuses,timers,delays,cleared,frames,states,render:job=>render.call(engine,job),reloads:()=>reloads,storageWrites:()=>storageWrites};
}
const job={id:'server-job',background:true,mode:'commander',status:'http',worker:{state:'queued'}};
(async()=>{
    const f=fixture();
    f.engine.request=async(action)=>{f.actions.push(action);return {job:{...job}};};
    await f.engine.start(true);
    assert.equal(f.actions.join(','),'start');assert.equal(f.reloads(),0);assert.equal(f.storageWrites(),0);
    assert.equal(f.engine.running,true);assert.equal(f.events[0][0],'marrison-cookie-scan-started');assert.equal(f.events[0][1][1],true);
    assert.ok(f.statuses.at(-1)[0].includes('puoi proseguire nel wizard'));
    await f.engine.pollBackground();
    assert.equal(f.actions.join(','),'start,state'); // No step/remote calls drive progress from the UI.
    const reopened=fixture();reopened.engine.request=async action=>{reopened.actions.push(action);return {job:{...job,status:'browser',worker:{state:'running'}}};};
    await reopened.engine.init();assert.equal(reopened.engine.running,true);assert.equal(reopened.actions.join(','),'state');
    reopened.engine.request=async action=>{reopened.actions.push(action);return {job:{...job,status:'completed',worker:{state:'finished'},cookie_count:2}};};
    await reopened.engine.pollBackground();assert.equal(reopened.engine.running,false);assert.equal(reopened.actions.at(-1),'inventory');
    const paused=fixture();paused.engine.request=async action=>{paused.actions.push(action);return {job:{...job,worker:{state:'paused',error:'Quota terminata'}}};};
    await paused.engine.init();assert.equal(paused.engine.running,false);assert.equal(paused.statuses.at(-1)[0],'Quota terminata');
    paused.engine.request=async action=>{paused.actions.push(action);return {job:{...job}};};
    await paused.engine.resume();assert.equal(paused.actions.slice(-2).join(','),'state,resume');assert.equal(paused.engine.running,true);
    const idle=fixture();idle.engine.request=async action=>{idle.actions.push(action);return {job:null};};
    await idle.engine.init();assert.equal(idle.actions.join(','),'state');assert.equal(idle.engine.running,false);assert.equal(idle.timers.length,0);
    const changed=fixture();changed.engine.job={...job};changed.engine.running=true;
    changed.engine.request=async(action,data)=>{changed.actions.push(action);assert.equal(data.mode,'local');return {job:{...job,status:'cancelled'},interrupted:true};};
    await changed.engine.changeMode('local');
    assert.equal(changed.actions.join(','),'mode');assert.equal(changed.engine.running,false);assert.equal(changed.engine.job.status,'cancelled');
    assert.ok(changed.statuses.at(-1)[0].includes('motore locale'));
    assert.equal(changed.timers.length,0);
    const race=fixture();let deliverState;
    race.engine.request=async action=>action==='state'?await new Promise(resolve=>{deliverState=resolve;}):{job:{...job,status:'cancelled'},interrupted:true};
    const opening=race.engine.init();await race.engine.changeMode('local');deliverState({job:{...job}});await opening;
    assert.equal(race.engine.job.status,'cancelled');assert.equal(race.engine.running,false);assert.equal(race.timers.length,0);
    const failedChange=fixture();failedChange.engine.job={...job};failedChange.engine.request=async()=>{throw new Error('Connessione assente');};
    await failedChange.engine.changeMode('local');assert.ok(failedChange.statuses.at(-1)[0].includes('potrebbe essere ancora attiva'));
    // A slow navigation gets its own budget; load grants a full agent window.
    const local=fixture();local.engine.running=true;local.engine.job={id:'local',mode:'local',status:'browser'};
    local.engine.measure({url:'https://example.test/',frame_url:'https://example.test/?signed=fixture'});
    assert.equal(local.delays.at(-1),8000);
    const navigationTimer=local.engine.timer;
    local.frames[0].listeners.load();assert.ok(local.cleared.includes(navigationTimer));assert.equal(local.delays.at(-1),5000);
    let timeoutResult;local.engine.browserResult=result=>{timeoutResult=result;};local.timers.at(-1)();
    assert.ok(timeoutResult.error.includes('verifica locale entro 5 secondi'));
    local.engine.removeFrame();const timersBefore=local.timers.length;local.frames[0].listeners.load();assert.equal(local.timers.length,timersBefore);
    const visual=fixture();
    visual.render({...job,status:'completed',cookie_count:3,http_done:10,browser_done:20,browser_total:20,services:[],storage:[]});
    assert.equal(visual.states.get('#marrison-engine-progress').props.hidden,true);
    assert.equal(visual.states.get('#marrison-engine-summary').classes['marrison-scan-complete'],true);
    assert.ok(visual.statuses.at(-1)[0].includes('3 cookie osservati'));
    visual.render({...job,status:'http',services:[],storage:[]});
    assert.equal(visual.states.get('#marrison-engine-progress').props.hidden,false);
    assert.equal(visual.states.get('#marrison-engine-summary').classes['marrison-scan-complete'],false);
    const historical = fixture();
    const stale = {...job, stale:true, status:'partial', cookie_count:4, http_done:1, browser_done:2, browser_total:2, services:[], storage:[], static_pending:true,
        remote_error:'Errore remoto precedente', issues:[{message:'Richieste non pubbliche o navigazioni fuori sito bloccate: 2.',url:'https://example.test/hello-world/'}]};
    assert.ok(historical.engine.resultText(stale).includes('nuova scansione locale'));
    assert.ok(!historical.engine.resultText(stale).includes('Scansione incompleta'));
    historical.render(stale);
    assert.equal(historical.states.get('#marrison-scan-resume').props.hidden,true);
    assert.equal(historical.states.get('#marrison-scan-cancel').props.hidden,true);
    assert.equal(historical.states.get('#marrison-engine-progress').props.hidden,true);
    assert.ok(historical.statuses.at(-1)[0].includes('nuova scansione locale'));
    assert.ok(historical.states.get('#marrison-engine-summary').text.includes('Rapporto precedente'));
    historical.render({...stale,status:'completed'});
    assert.equal(historical.states.get('#marrison-engine-summary').classes['marrison-scan-complete'],false);
    historical.engine.request=async action=>{historical.actions.push(action);return {job:stale};};
    await historical.engine.init();
    await historical.engine.resume();
    assert.equal(historical.actions.join(','),'state,state'); // Reads only: no resume/step/remote dispatch.
    assert.equal(historical.engine.running,false);
    assert.equal(historical.timers.length,0);
    assert.ok(historical.engine.resultText({...stale,stale:false,mode:'local'}).includes('Scansione incompleta'), 'Current partial errors hidden');
    console.log('PASS background UI: wizard can continue, no reload or sessionStorage dependency, server-only progress, new-tab recovery, terminal inventory refresh and paused-job resume.');
})().catch(error=>{console.error(error);process.exitCode=1;});
