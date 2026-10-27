// PHP-rendered scanner/wizard sections and real jQuery/coordinator; AJAX is isolated.
// NODE_PATH=<runtime modules> PLAYWRIGHT_BROWSER_PATH=<Chrome> node <test> <WordPress root>
'use strict';
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const wp = process.argv[2], plugin = path.join(__dirname, '../marrison-addon');
assert(wp, 'Pass a WordPress root for the native jQuery/buttons assets');
const directory = fs.mkdtempSync(path.join(__dirname, '.scanner-controls-'));
let browser;
(async () => {
    const templates = path.join(plugin, 'includes/modules/cookie-manager/templates');
    const admin = fs.readFileSync(path.join(templates, 'admin-settings.php'), 'utf8')
        .split("<?php elseif ($active_tab === 'scanner'): ?>")[1].split('<?php if (!in_array($active_tab')[0]
        .replace(/\s*<\?php endif; \?>\s*$/, '');
    const wizard = fs.readFileSync(path.join(templates, 'setup-wizard.php'), 'utf8')
        .split('<!-- Step 3: Cookie -->')[1].split('<!-- Step 4: Aspetto -->')[0];
    const file = path.join(directory, 'sections.php');
    fs.writeFileSync(file, admin + wizard);
    const php = 'function _e($s){echo $s;} function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");} function esc_attr($s){return esc_html($s);} function esc_html_e($s){echo esc_html($s);} function get_option($k,$d=false){return $d;} function marrison_cookie_site_text($it,$en){return $it;} $categories=[]; $current_step=3; require $argv[1];';
    const html = execFileSync(process.env.PHP_BINARY || 'php', ['-r', php, file], {encoding:'utf8'});
    assert(!html.includes('Warning:') && !html.includes('Fatal error:'), html);
    browser = await chromium.launch({headless:true, executablePath:process.env.PLAYWRIGHT_BROWSER_PATH || undefined});
    const page = await browser.newPage(); const errors=[]; page.on('pageerror',error=>errors.push(error.message));
    await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'+html+'</body></html>');
    await page.addStyleTag({path:path.join(wp,'wp-includes/css/buttons.min.css')});
    await page.addStyleTag({path:path.join(plugin,'includes/modules/cookie-manager/assets/css/admin.css')});
    await page.addScriptTag({path:path.join(wp,'wp-includes/js/jquery/jquery.min.js')});
    await page.evaluate(() => {
        window.scanRequests=[];
        window.marrisonCookieScanner={protocol:2,revision:'20261024.2',nonce:'fixture',ajaxUrl:'/fixture'};
        jQuery.ajax=options=>{
            const action=options.data.action.split('_').pop(); window.scanRequests.push(options.data);
            const job=action==='start'?{id:'fixture',mode:'local',status:'http',background:true,static_posts:0,http_done:0,http_failed:0,queued:0,browser_done:0,browser_total:0,cookie_count:0,services:[],storage:[],issues:[],local_iframe:options.data.browser===1}:null;
            options.success({success:true,data:{runtime:{protocol:2,revision:'20261024.2',compatible:true,scanner_hash:'fixture',plugin_version:'1.3.86'},job,cookies:[]}});
        };
    });
    await page.addScriptTag({path:path.join(plugin,'includes/modules/cookie-manager/assets/js/scanner.js')});
    await page.waitForFunction(()=>window.scanRequests.length>0);
    assert.equal(await page.evaluate(()=>window.scanRequests.filter(r=>r.action.endsWith('_start')).length),0,'Opening the screen started a scan');
    for (const width of [1280,390]) {
        await page.setViewportSize({width,height:1000});
        assert(await page.locator('#marrison-scan-browser').isVisible());
        assert(await page.locator('#wizard_scan_browser_button').isVisible());
        assert.equal(await page.locator('#marrison-scan-cookies').innerText(),'Avvia analisi statica');
        assert.equal(await page.locator('#wizard_scan_button').innerText(),'Avvia analisi statica');
        for (const selector of ['#marrison-scan-limit','#marrison-scan-seconds','#wizard_scan_limit','#wizard_scan_seconds']) {
            assert.equal(await page.inputValue(selector),'0','Normal scan must cover all discovered groups without the former total cutoff');
        }
    }
    const limited={id:'fixture',mode:'local',status:'partial',local_iframe:true,static_pending:false,static_posts:16,http_done:16,http_failed:0,
        queued:0,browser_done:6,browser_total:6,browser_failed:0,cookie_count:3,documented_cookie_count:2,limit:3,limit_reached:true,
        services:[],storage:[],sampling:{selected:3,unselected:5,candidates:8,equivalents:0,groups:[]},issues_total:1,
        issues:[{url:'',message:'Limite di 3 campioni raggiunto: altri gruppi non sono stati visitati.'}]};
    for(const width of [1280,390]) {
        await page.setViewportSize({width,height:1000});
        await page.evaluate(job=>{window.MarrisonCookieScanner.running=false;window.MarrisonCookieScanner.render(job);},limited);
        assert((await page.locator('#marrison-engine-summary').innerText()).includes('3/8 gruppi selezionati'));
        assert((await page.locator('#marrison-engine-summary').innerText()).includes('6/6 verifiche browser (prima e dopo il consenso)'));
        for(const selector of ['#marrison-scan-status','#wizard_scan_status']) {
            assert((await page.locator(selector).innerText()).includes('Copertura browser limitata'));
            assert.equal(await page.locator(selector).evaluate(el=>el.classList.contains('marrison-scan-status-error')),false);
        }
        const timeout={...limited,issues:[...limited.issues,{url:'',message:'Tempo massimo della verifica locale raggiunto.'}],issues_total:2};
        await page.evaluate(job=>window.MarrisonCookieScanner.render(job),timeout);
        assert((await page.locator('#marrison-scan-status').innerText()).includes('Tempo massimo'));
        assert.equal(await page.locator('#marrison-scan-status').evaluate(el=>el.classList.contains('marrison-scan-status-error')),true);
    }
    for (const [button,limit,seconds] of [['#marrison-scan-browser','#marrison-scan-limit','#marrison-scan-seconds'],['#wizard_scan_browser_button','#wizard_scan_limit','#wizard_scan_seconds']]) {
        await page.fill(limit,'2'); await page.fill(seconds,'20'); await page.click(button);
        const data=await page.evaluate(()=>window.scanRequests.filter(r=>r.action.endsWith('_start')).at(-1));
        assert.equal(data.browser,1); assert.equal(data.limit,'2'); assert.equal(data.max_seconds,'20');
        assert(await page.locator(button).isDisabled());
        await page.evaluate(()=>{const e=window.MarrisonCookieScanner;e.removeFrame();e.running=false;e.render(null);});
    }
    assert.deepEqual(errors,[]);
    console.log('PASS scanner commands: PHP admin/wizard buttons visible at 1280/390, explicit browser request with per-run limits, disabled while running, no startup scan');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(browser)await browser.close();
    const target=path.resolve(directory);assert(target.startsWith(path.resolve(__dirname)+path.sep+'.scanner-controls-'));
    fs.rmSync(target,{recursive:true,force:true});
});
