const assert = require('node:assert/strict');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
(async () => {
 const css = execFileSync('php', ['-r', "define('ABSPATH',1); function wp_json_encode($v,$f=0){return json_encode($v,$f);} require 'marrison-addon/includes/modules/class-marrison-addon-login-designer.php'; $s=Marrison_Addon_Login_Designer::defaults(); $s['form_bg']='#111122'; $s['shadow_color']='#ff44dd'; $s['shadow_x']=0; $s['shadow_y']=0; $s['shadow_blur']=28; $s['shadow_spread']=4; $s['shadow_opacity']=85; echo Marrison_Addon_Login_Designer::variables($s);"], {encoding:'utf8'});
 const browser = await chromium.launch({channel:'chrome',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:700,height:500}});
  await page.setContent('<style>body{margin:0}body.login #login{margin:auto}form{margin:24px 0}input{display:block;width:100%;margin:10px 0}label{display:block}</style><style>'+fs.readFileSync('marrison-addon/assets/css/login-designer.css','utf8')+'</style><style>'+css+'body.login{background:#111122}</style><body class="login"><div id="login"><form><label>Nome utente<input class="input" value="utente.esempio"></label><label>Password<input class="input" type="password" value="esempio"></label><button class="button-primary">Accedi</button></form></div></body>');
  const shadow=await page.locator('form').evaluate(el=>getComputedStyle(el).boxShadow);
  assert.notEqual(shadow,'none'); assert.match(shadow,/255, 68, 221/); assert.match(shadow,/28px 4px/);
  await page.screenshot({path:'audit/login-designer-glow.png'});
  console.log('PASS Chrome: glow color, blur, spread computed correctly: '+shadow);
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
