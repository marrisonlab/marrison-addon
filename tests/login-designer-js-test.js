const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const defaults = JSON.parse(require('node:child_process').execFileSync('php', ['-r', "define('ABSPATH',1); require 'marrison-addon/includes/modules/class-marrison-addon-login-designer.php'; echo json_encode(['settings'=>Marrison_Addon_Login_Designer::defaults(),'schema'=>Marrison_Addon_Login_Designer::schema(),'backgroundOpacities'=>Marrison_Addon_Login_Designer::background_opacities(),'option'=>Marrison_Addon_Login_Designer::OPTION]);"], {encoding:'utf8'}));
const callbacks = {}, vars = {}, classes = {}, custom = {}, logo = {}, languageSwitcher = {};
const document = {body:{style:{setProperty:(k,v)=>vars[k]=v},classList:{toggle:(k,v)=>classes[k]=v}},querySelector:selector=>selector === '.language-switcher' ? languageSwitcher : logo,getElementById:()=>custom,head:{appendChild:()=>{}}};
function customize(id, callback) { const key = id.match(/\[([^\]]+)\]/)[1]; callback({get:()=>defaults.settings[key],bind:fn=>callbacks[key]=fn}); }
vm.runInNewContext(fs.readFileSync('marrison-addon/assets/js/login-designer-preview.js','utf8'), {window:{marrisonLoginPreview:defaults},document,wp:{customize}});
callbacks.layout('split'); assert.equal(classes['mld-layout-split'],true); assert.equal(classes['mld-layout-center'],false);
callbacks.form_width(450); assert.equal(vars['--mld-form-width'],450);
callbacks.logo_enabled(true); callbacks.logo_image('https://example.test/logo.png'); assert.equal(classes['mld-logo'],true);
callbacks.logo_image(''); assert.equal(classes['mld-logo'],false);
callbacks.custom_css('body.login{color:red}'); assert.equal(custom.textContent,'body.login{color:red}');
callbacks.custom_css(''); assert.equal(custom.textContent,'');
callbacks.button_full(true); assert.equal(vars['--mld-button-width'],'100%');
console.log('Login Designer: live layout, sizing, logo fallback, custom CSS removal and button width PASS');

callbacks.input_size(18); assert.equal(vars['--mld-input-size'],18);
callbacks.input_padding(10); assert.equal(vars['--mld-input-padding'],10);
callbacks.input_padding_x(16); assert.equal(vars['--mld-input-padding-x'],16);
callbacks.input_text('#fafafa'); assert.equal(vars['--mld-input-text'],'#fafafa');
callbacks.input_bg('#111122'); assert.equal(vars['--mld-input-bg'],'#111122');
console.log('Login Designer: live input typography, spacing and colors PASS');

callbacks.input_opacity(50); assert.equal(vars['--mld-input-bg-alpha'],'rgba(17,17,34,0.5)');
callbacks.input_opacity(0); assert.equal(vars['--mld-input-bg-alpha'],'rgba(17,17,34,0)');
callbacks.form_bg('#abc'); callbacks.form_opacity(25); assert.equal(vars['--mld-form-bg-alpha'],'rgba(170,187,204,0.25)');
console.log('Login Designer: live alpha, transparent backgrounds and shorthand colors PASS');

callbacks.hide_language_dropdown(true); assert.equal(languageSwitcher.hidden,true);
callbacks.hide_language_dropdown(false); assert.equal(languageSwitcher.hidden,false);
console.log('Login Designer: live language selector visibility PASS');

callbacks.shadow_color('#ff00ff'); callbacks.shadow_opacity(75);
assert.equal(vars['--mld-shadow-color-alpha'],'rgba(255,0,255,0.75)');
callbacks.shadow_spread(4); assert.equal(vars['--mld-shadow-spread'],4);
console.log('Login Designer: live colored glow and spread PASS');
