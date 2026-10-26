// Optional live integration: visitor consent only; candidate assets overridden in this browser.
// NODE_PATH=<modules> PLAYWRIGHT_BROWSER_PATH=<Chrome> node <test> <site URL> [screenshots]
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const url = process.argv[2];
assert(url && /^https:\/\//.test(url), 'Pass the test site HTTPS URL');
const scripts = path.join(__dirname, '../marrison-addon/includes/modules/cookie-manager/assets/js');
let browser, debugPage;
const diagnostics=[];
const visibleWidget = () => {
    const widget=document.querySelector('.ti-widget.ti-goog');
    return !!widget && widget.getBoundingClientRect().height>20 && getComputedStyle(widget).opacity!=='0';
};
async function waitForWidget(page) {
    await page.waitForFunction(()=>typeof window.TrustindexWidget==='function',null,{timeout:20000});
    await page.evaluate(()=>document.querySelector('.ti-widget.ti-goog')?.scrollIntoView({block:'center'}));
    await page.mouse.wheel(0,30); // Trustindex initializes when the visitor reaches the widget.
    await page.waitForFunction(visibleWidget,null,{timeout:20000});
}
async function setCategory(page, category, enabled) {
    const field=page.locator('.marrison-category-checkbox[data-category="'+category+'"]');
    if (await field.isChecked() !== enabled) await page.locator('.marrison-cookie-category[data-category="'+category+'"] .marrison-switch').click();
}
(async () => {
    browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_BROWSER_PATH || undefined});
    const original=await browser.newContext();const before=await original.newPage();
    await before.goto(url,{waitUntil:'domcontentloaded'});
    await before.locator('#marrison-accept-all').click();
    await before.locator('#marrison-cookie-banner').waitFor({state:'hidden'});
    console.log('Live original after Accept all:', JSON.stringify(await before.evaluate(() => ({
        widgetVisible:!!document.querySelector('.ti-widget.ti-goog') && document.querySelector('.ti-widget.ti-goog').getBoundingClientRect().height>20,
        blockedLoader:!!document.querySelector('script[data-marrison-blocked-src*="cdn.trustindex.io/loader.js"]')
    }))));
    await original.close();
    const context=await browser.newContext();let overrides=0,loaderRequests=0;
    context.on('response',response=>{if(/trustindex|widget.*css/.test(response.url()))diagnostics.push(String(response.status())+' '+response.url());});
    await context.route('**/includes/modules/cookie-manager/assets/js/*.js*',async route=>{
        const name=new URL(route.request().url()).pathname.split('/').pop();
        if(name==='consent-runtime.js' || name==='frontend.js') {overrides++;await route.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(scripts,name),'utf8')});}
        else await route.continue();
    });
    context.on('request',request=>{if(request.url().startsWith('https://cdn.trustindex.io/loader.js'))loaderRequests++;});
    const page=await context.newPage();debugPage=page;page.on('pageerror',error=>diagnostics.push(error.message));page.on('requestfailed',request=>diagnostics.push(request.url()+' '+JSON.stringify(request.failure())));await page.goto(url,{waitUntil:'domcontentloaded'});
    await page.locator('#marrison-accept-all').waitFor({state:'visible'});
    assert.equal(loaderRequests,0,'Trustindex loaded before verified consent');
    assert.equal(await page.evaluate(visibleWidget),false);
    await page.locator('#marrison-accept-all').click();
    await waitForWidget(page);
    console.log('PASS live widget after verified Accept all');
    assert(overrides>=2,'Both candidate assets must be tested');
    assert(loaderRequests>0,'The real Trustindex loader did not run');
    if(process.argv[3])await page.screenshot({path:path.join(process.argv[3],'cookie-reviews-accept-all.png'),fullPage:true});
    await page.reload({waitUntil:'domcontentloaded'});
    await waitForWidget(page);
    console.log('PASS live widget with persisted full consent');
    await page.locator('#marrison-widget-button').click();
    await page.locator('#marrison-customize').click();
    await setCategory(page,'analytics',false);
    const beforePartial=loaderRequests;
    await Promise.all([page.waitForEvent('domcontentloaded'),page.locator('#marrison-save-preferences').click()]);
    await page.waitForFunction(()=>window.MarrisonConsentRuntime && window.MarrisonConsentRuntime.allowed().includes('marketing'));
    assert.equal(await page.evaluate(visibleWidget),false,'Unclassified widget loaded with partial consent');
    assert.equal(loaderRequests,beforePartial,'Trustindex restarted after partial revocation');
    await page.locator('#marrison-widget-button').click();
    await page.locator('#marrison-customize').click();
    for(const category of ['functional','analytics','marketing'])await setCategory(page,category,true);
    await page.locator('#marrison-save-preferences').click();
    await waitForWidget(page);
    await page.locator('#marrison-widget-button').click();
    const beforeRefusal=loaderRequests;
    await Promise.all([page.waitForEvent('domcontentloaded'),page.locator('#marrison-reject-all').click()]);
    await page.waitForFunction(()=>window.MarrisonConsentRuntime && window.MarrisonConsentRuntime.allowed().length===1);
    assert.equal(await page.evaluate(visibleWidget),false);
    assert.equal(loaderRequests,beforeRefusal,'Trustindex restarted after refusal');
    await context.close();
    console.log('PASS LIVE candidate browser override: real Trustindex widget after Accept all and custom-all, persisted choice after reload, blocked before/partial/refusal; site files unchanged');
})().catch(async error=>{console.error(error);if(debugPage && !debugPage.isClosed())console.error(JSON.stringify(await debugPage.evaluate(()=>({allowed:window.MarrisonConsentRuntime && window.MarrisonConsentRuntime.allowed(),permits:window.MarrisonConsentRuntime && window.MarrisonConsentRuntime.permits('unclassified'),trustindex:typeof window.TrustindexWidget,loader:[...document.scripts].filter(s=>(s.src||s.getAttribute('data-marrison-blocked-src')||'').includes('trustindex')).map(s=>s.outerHTML.slice(0,600)),links:[...document.querySelectorAll('link')].filter(l=>l.href.includes('trustindex')).map(l=>({href:l.href,rel:l.rel,disabled:l.disabled})),widget:document.querySelector('.ti-widget.ti-goog')?.outerHTML.slice(0,900)})),null,2));console.error(diagnostics.slice(-15));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
