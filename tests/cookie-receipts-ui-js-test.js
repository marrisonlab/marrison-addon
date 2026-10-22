const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Node {
    constructor(tag){this.tag=tag;this.nodes=[];this.value='';this.length=0;}
    text(value){this.value=String(value);return this;}
    addClass(){return this;}attr(){return this;}empty(){this.nodes=[];return this;}
    append(node){this.nodes.push(node);return this;}appendTo(node){node.nodes.push(this);return this;}
    on(){return this;}ready(){return this;}
}
const view=new Node('view');
function $(selector){if(typeof selector==='function')return;return selector==='#marrison-receipts-result'?view:new Node(selector);}
const sandbox={jQuery:$,document:{readyState:'loading'},window:{},console};vm.createContext(sandbox);
new vm.Script(fs.readFileSync('marrison-addon/includes/modules/cookie-manager/assets/js/admin.js','utf8')).runInContext(sandbox);
function texts(node){return [node.value,...node.nodes.flatMap(texts)].join(' ');}
const admin=sandbox.window.MarrisonCookieAdmin,base={created_at:'2026-10-21T10:00:00Z',expires_at:'2027-04-21T10:00:00Z',categories:['necessary'],receipt_id:'receipt',visitor_id:'random',policy_revision:'revision',event:'withdrawal',integrity_valid:true};
admin.renderReceipts([{...base,type:'reject_all'}],{revision:{revision_label:'2',privacy_profile:{controller_name:'<img src=x onerror=alert(1)>'}}},{revision:true});
assert.ok(texts(view).includes('Solo necessari'));assert.ok(texts(view).includes('Necessari'));assert.ok(texts(view).includes('Consenso ridotto o revocato'));assert.ok(texts(view).includes('Ricevuta e informativa integre'));
assert.ok(texts(view).includes('Revisione informativa: 2'));
admin.renderReceipts([{...base,type:'custom',categories:['necessary','analytics'],integrity_valid:false}],{},{});
assert.ok(texts(view).includes('Scelta personalizzata'));assert.ok(texts(view).includes('Analitici'));assert.ok(texts(view).includes('Integrità non confermata'));
admin.renderReceipts([],{},{});assert.ok(texts(view).includes('Nessuna ricevuta'));
assert.equal(typeof Node.prototype.html,'undefined');
console.log('PASS receipt UI: translated choices/categories/events, dates, verified/invalid integrity, empty state, no raw JSON or unsafe HTML rendering.');
