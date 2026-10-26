(function () {
  'use strict';
  const config = window.marrisonBusinessInfoAdmin;
  const editor = document.getElementById('marrison-business-info-editor');
  if (!config || !editor) return;
  const data = config.data;
  ['company', 'privacy', 'hours'].forEach(k => { data[k] = data[k] && !Array.isArray(data[k]) ? data[k] : {}; });
  ['contacts', 'locations', 'social', 'custom'].forEach(k => { data[k] = Array.isArray(data[k]) ? data[k] : []; });
  data.hours.weekly = data.hours.weekly && !Array.isArray(data.hours.weekly) ? data.hours.weekly : {};
  data.hours.exceptions = Array.isArray(data.hours.exceptions) ? data.hours.exceptions : [];
  const uid = () => window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'record-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
  let sequence = 0;
  const automaticKeys = new WeakMap();
  function el(tag, text, className) { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; }
  function button(parent, label, action) { const b = el('button', label, 'button'); b.type = 'button'; b.addEventListener('click', action); parent.append(b); return b; }
  function shield(label) { const icon = el('span', '', 'dashicons dashicons-shield bi-privacy-icon'); icon.setAttribute('aria-hidden', 'true'); label.append(' ', icon, el('span', ' (Cookie Manager)', 'screen-reader-text')); }
  function field(parent, object, key, label, type = 'text', options, privacy = false, locked = false) {
    const wrap = el('div', undefined, 'bi-field'), caption = el('label', label);
    const input = el(type === 'textarea' ? 'textarea' : type === 'select' ? 'select' : 'input');
    input.id = 'bi-field-' + (++sequence); caption.htmlFor = input.id;
    if (privacy) shield(caption);
    if (type === 'select') Object.entries(options || {}).forEach(([value, name]) => { const option = el('option', name); option.value = value; input.append(option); });
    else if (type !== 'textarea') input.type = type;
    if (type === 'number') input.step = 'any';
    input.value = object[key] === undefined ? '' : object[key];
    if (locked) { input.readOnly = true; input.title = 'Chiave stabile: per cambiarla crea un nuovo elemento.'; }
    const update = () => { object[key] = input.value; completeness(); };
    input.addEventListener('input', update); input.addEventListener('change', update);
    wrap.append(caption, input); parent.append(wrap); return input;
  }
  function toggle(parent, object, key, label, action, privacy = false) {
    const wrap = el('label', undefined, 'bi-toggle'), input = el('input'); input.type = 'checkbox'; input.checked = !!object[key];
    input.addEventListener('change', () => { object[key] = input.checked; if (action) action(input.checked); completeness(); });
    wrap.append(input, document.createTextNode(label)); if (privacy) shield(wrap); parent.append(wrap); return input;
  }
  function completeness() {
    const p = data.privacy, missing = [];
    const selected = (group, key, type) => { const rows = data[group].filter(r => !type || r.type === type); return key ? rows.find(r => r.key === key) : rows.find(r => r.default) || rows[0]; };
    const name = (p.controller_name || '').trim() || data.company.legal_name || data.company.trade_name;
    const email = p.different_controller && (p.controller_email || '').trim() ? p.controller_email : (selected('contacts', p.email_key, 'email') || {}).value;
    const location = selected('locations', p.location_key);
    const address = p.different_controller && (p.controller_address || '').trim() ? p.controller_address : location && [location.street, location.postcode, location.city, location.country].filter(Boolean).join(' ');
    if (!name || !name.trim()) missing.push('Titolare / ragione sociale');
    if (!email || !email.trim()) missing.push('Email per la privacy');
    if (!address || !address.trim()) missing.push('Indirizzo del titolare');
    const box = document.getElementById('marrison-bi-completeness');
    box.className = missing.length ? 'bi-warning' : 'notice notice-info';
    box.textContent = missing.length ? 'Cookie Manager: dati essenziali mancanti: ' + missing.join(', ') + '. Puoi comunque salvare e pubblicare la policy.' : 'Cookie Manager: dati anagrafici essenziali presenti. Verifica anche i trattamenti e le impostazioni privacy nel Cookie Manager.';
  }
  const tabs = el('div', undefined, 'bi-tabs'); tabs.setAttribute('role', 'tablist'); tabs.setAttribute('aria-label', 'Info Azienda'); editor.append(tabs);
  const panels = {}, tabButtons = {};
  [['company','Azienda'],['contacts','Contatti'],['locations','Sedi'],['social','Social'],['custom','Campi personalizzati'],['hours','Orari di apertura'],['privacy','Privacy e titolare']].forEach(([key,label], index) => {
    const panel = el('section', undefined, 'bi-panel'); panel.id = 'bi-panel-' + key; panel.setAttribute('role','tabpanel'); panel.setAttribute('aria-labelledby','bi-tab-' + key); panel.hidden = index !== 0; panels[key] = panel;
    const b = button(tabs, label, () => {
      Object.keys(panels).forEach(k => { panels[k].hidden = k !== key; tabButtons[k].setAttribute('aria-selected', k === key ? 'true' : 'false'); tabButtons[k].tabIndex = k === key ? 0 : -1; });
      renderPanel(key);
    });
    b.id = 'bi-tab-' + key; b.setAttribute('role','tab'); b.setAttribute('aria-controls', panel.id); b.setAttribute('aria-selected', index === 0 ? 'true' : 'false'); b.tabIndex = index === 0 ? 0 : -1; tabButtons[key] = b; editor.append(panel);
  });
  tabs.addEventListener('keydown', e => { if (!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return; const buttons = Object.values(tabButtons), current = buttons.indexOf(document.activeElement); if (current < 0) return; e.preventDefault(); const next = e.key === 'Home' ? 0 : e.key === 'End' ? buttons.length - 1 : (current + (e.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length; buttons[next].click(); buttons[next].focus(); });
  function actions(parent, rows, row, render) {
    const bar = el('div', undefined, 'bi-actions'); parent.append(bar);
    [['Sposta su',-1],['Sposta giù',1]].forEach(([name,delta]) => { const index = rows.indexOf(row); const b = button(bar,name,() => { const i = rows.indexOf(row); [rows[i],rows[i + delta]] = [rows[i + delta],rows[i]]; render(); }); b.disabled = index + delta < 0 || index + delta >= rows.length; });
    button(bar,'Rimuovi',() => { if (!window.confirm('Rimuovere questo elemento? I collegamenti alla sua chiave resteranno vuoti.')) return; rows.splice(rows.indexOf(row),1); render(); completeness(); });
  }
  function records(group) {
    const panel = panels[group]; panel.replaceChildren();
    panel.append(el('p','Etichetta e chiave identificano ogni elemento. La chiave viene bloccata dopo il primo salvataggio; riordinare gli elementi non cambia i collegamenti.', 'bi-help'));
    data[group].forEach(row => {
      const card = el('div', undefined, 'bi-record'), grid = el('div', undefined, 'bi-fields'); card.append(grid); panel.append(card);
      const labelInput = field(grid,row,'label','Etichetta'), locked = !!(config.locked[group] || {})[row.id];
      const keyInput = field(grid,row,'key','Chiave univoca', 'text', null, false, locked); keyInput.pattern = '[a-z][a-z0-9_]*';
      keyInput.addEventListener('input',() => automaticKeys.delete(row));
      labelInput.addEventListener('input',() => {
        if (locked || (row.key && automaticKeys.get(row) !== row.key)) return;
        let key = row.label.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
        if (key && !/^[a-z]/.test(key)) key = 'campo_' + key;
        const baseKey = key; let suffix = 2;
        while (key && data[group].some(other => other !== row && other.key === key)) key = baseKey + '_' + suffix++;
        row.key = key; keyInput.value = key; automaticKeys.set(row,key);
      });
      if (group === 'contacts') { field(grid,row,'type','Categoria','select', config.contactTypes).addEventListener('change',() => records(group)); const type = row.type === 'email' ? 'email' : ['phone','mobile','fax','whatsapp'].includes(row.type) ? 'tel' : 'text'; field(grid,row,'value','Recapito',type, null, ['email','phone','mobile','whatsapp','fax'].includes(row.type)); }
      if (group === 'social') field(grid,row,'value','Indirizzo profilo','url');
      if (group === 'custom') { field(grid,row,'type','Tipo di dato','select',config.customTypes).addEventListener('change',() => records(group)); field(grid,row,'value','Valore', row.type === 'phone' ? 'tel' : row.type || 'text'); }
      if (group === 'locations') {
        field(grid,row,'type','Tipologia','select',{legal:'Legale',operational:'Operativa',shop:'Negozio',warehouse:'Magazzino',other:'Altra'});
        Object.entries({street:'Via e numero civico',postcode:'CAP',city:'Città',province:'Provincia',region:'Regione',country:'Nazione',lat:'Latitudine (facoltativa)',lng:'Longitudine (facoltativa)'}).forEach(([k,l]) => field(grid,row,k,l,['lat','lng'].includes(k) ? 'number' : 'text',null,!['lat','lng'].includes(k)));
      }
      if (['contacts','locations'].includes(group)) toggle(card,row,'default',group === 'locations' ? 'Sede principale' : 'Predefinito per questa categoria',() => { if (row.default) data[group].forEach(other => { if (other !== row && (group === 'locations' || other.type === row.type)) other.default = false; }); records(group); });
      actions(card,data[group],row,() => records(group));
      card.append(el('p','Shortcode: [marrison_info key="' + group + '.' + (row.key || 'chiave') + '"]','bi-help'));
    });
    button(panel,'Aggiungi ' + ({contacts:'recapito',locations:'sede',social:'profilo social',custom:'campo'}[group]),() => { data[group].push({id:uid(),label:'',key:'',type:group === 'contacts' ? 'email' : group === 'locations' ? 'legal' : group === 'social' ? 'url' : 'text',value:'',default:false}); records(group); panel.querySelector('.bi-record:last-of-type input')?.focus(); });
    if (group === 'social') {
      const bar = el('div', undefined, 'bi-actions'); panel.append(bar);
      ['Facebook','Instagram','LinkedIn','YouTube','TikTok','X','Pinterest'].forEach(name => { const key = name.toLowerCase(); if (!data.social.some(r => r.key === key)) button(bar,'Aggiungi ' + name,() => { data.social.push({id:uid(),label:name,key,type:'url',value:''}); records(group); }); });
    }
  }
  function dayEditor(parent, day, title, copy) {
    day.slots = Array.isArray(day.slots) ? day.slots : [];
    const card = el('div', undefined, 'bi-record'); if (title) card.append(el('h3',title)); parent.append(card);
    const slots = el('div');
    toggle(card,day,'open','Giorno aperto',() => { slots.hidden = !day.open; }); card.append(slots); slots.hidden = !day.open;
    function renderSlots() { slots.replaceChildren(); day.slots.forEach(slot => { const line = el('div',undefined,'bi-slot'); field(line,slot,'start','Apertura','time'); field(line,slot,'end','Chiusura','time'); button(line,'Rimuovi fascia',() => { day.slots.splice(day.slots.indexOf(slot),1); renderSlots(); }); slots.append(line); }); button(slots,'Aggiungi fascia',() => { day.slots.push({start:'',end:''}); renderSlots(); }); }
    renderSlots();
    if (copy) { const bar = el('div',undefined,'bi-actions'); card.append(bar); button(bar,'Applica a tutti',() => copy(false)); button(bar,'Applica ai feriali (Lun–Ven)',() => copy(true)); }
    return card;
  }
  function hoursPanel() {
    const panel = panels.hours; panel.replaceChildren(el('p','Fuso orario: quello configurato in WordPress. Gli intervalli devono terminare nello stesso giorno. Le eccezioni prevalgono sulla settimana.','bi-help'));
    Object.entries(config.days).forEach(([n,name]) => {
      const day = data.hours.weekly[n] = data.hours.weekly[n] || {open:false,slots:[]};
      dayEditor(panel,day,name, weekdays => { const keys = Object.keys(config.days).filter(k => (!weekdays || Number(k) <= 5) && k !== n); if (keys.some(k => data.hours.weekly[k] && (data.hours.weekly[k].open || data.hours.weekly[k].slots.length)) && !window.confirm('Sovrascrivere gli orari già configurati nei giorni di destinazione?')) return; keys.forEach(k => { data.hours.weekly[k] = JSON.parse(JSON.stringify(day)); }); hoursPanel(); });
    });
    panel.append(el('h2','Chiusure e aperture straordinarie'));
    data.hours.exceptions.forEach(row => { const card = dayEditor(panel,row,'Eccezione per data'); const grid = el('div',undefined,'bi-fields'); card.prepend(grid); field(grid,row,'date','Data','date'); field(grid,row,'label','Motivo (festività, ferie, apertura straordinaria…)'); actions(card,data.hours.exceptions,row,hoursPanel); });
    button(panel,'Aggiungi eccezione',() => { data.hours.exceptions.push({id:uid(),date:'',label:'',open:false,slots:[]}); hoursPanel(); });
  }
  function privacyPanel() {
    const panel = panels.privacy, p = data.privacy; panel.replaceChildren(el('p','Indica il titolare del trattamento e scegli i recapiti già presenti in Info Azienda. Cookie Manager li usa automaticamente nelle policy.','bi-help'));
    const identity = el('div',undefined,'bi-fields bi-privacy-fields'); panel.append(identity);
    field(identity,p,'controller_name','Titolare del trattamento — nome / ragione sociale','text',null,true);
    panel.append(el('p','Se lasci il nome vuoto viene usata la ragione sociale dell’azienda, oppure il nome commerciale. Non occorre reinserire indirizzo e contatti.','bi-help'));
    const ordinary = el('div',undefined,'bi-fields bi-privacy-fields'), alternate = el('div',undefined,'bi-fields bi-privacy-fields');
    const choices = (group,predicate) => Object.fromEntries([['','Predefinito'], ...data[group].filter(predicate).map(r => [r.key,r.label + ' (' + r.key + ')'])]);
    const select = (key,label,group,predicate) => { const options = choices(group,predicate); if (p[key] && !options[p[key]]) options[p[key]] = 'Elemento eliminato: ' + p[key]; field(ordinary,p,key,label,'select',options,true); };
    select('email_key','Email usata nella policy','contacts',r => r.type === 'email');
    select('phone_key','Telefono usato nella policy','contacts',r => ['phone','mobile','whatsapp','fax'].includes(r.type));
    select('location_key','Sede usata nella policy','locations',() => true);
    panel.append(ordinary);
    toggle(panel,p,'different_controller','Usa dati dedicati per il titolare',() => { alternate.hidden = !p.different_controller; },true);
    alternate.append(el('p','Compila solo i dati diversi da quelli selezionati sopra. I campi vuoti mantengono i dati aziendali.','bi-help bi-field-wide'));
    Object.entries({controller_address:'Indirizzo completo e Paese del titolare',controller_email:'Email del titolare',controller_phone:'Telefono del titolare',controller_vat:'Partita IVA del titolare',controller_tax_code:'Codice fiscale del titolare'}).forEach(([key,label]) => {
      const input = field(alternate,p,key,label,key === 'controller_email' ? 'email' : key === 'controller_address' ? 'textarea' : key === 'controller_phone' ? 'tel' : 'text',null,true);
      if (key === 'controller_address') input.parentElement.classList.add('bi-field-wide');
    });
    alternate.hidden = !p.different_controller; panel.append(alternate);
    panel.append(el('h3','DPO e rappresentante UE (se nominati)'));
    const grid = el('div',undefined,'bi-fields bi-privacy-fields'); panel.append(grid);
    field(grid,p,'representative','Rappresentante nell’UE: nome e contatti (se nominato)','textarea',null,true);
    field(grid,p,'dpo','Responsabile protezione dati (DPO): nome e contatti (se nominato)','textarea',null,true);
  }
  function renderPanel(key) {
    if (['contacts','locations','social','custom'].includes(key)) records(key);
    if (key === 'hours') hoursPanel();
    if (key === 'privacy') privacyPanel();
    if (key === 'company') {
      const grid = el('div',undefined,'bi-fields'); panels.company.replaceChildren(grid);
      Object.entries(config.company).filter(([key]) => key !== 'logo').forEach(([key,label]) => field(grid,data.company,key,label,key === 'description' ? 'textarea' : key === 'pec' ? 'email' : 'text',null,['trade_name','legal_name','vat','tax_code'].includes(key)));
      const logo = el('div',undefined,'bi-record'); panels.company.append(logo,el('p','Nome commerciale o ragione sociale, indirizzo e email sono i dati essenziali segnalati dal Cookie Manager. Partita IVA, codice fiscale e telefono vengono mostrati quando compilati.','bi-help'));
      logo.append(el('h3','Logo aziendale'));
      const preview = el('img',undefined,'bi-logo'); preview.alt = 'Logo aziendale'; preview.hidden = !config.logoUrl; if (config.logoUrl) preview.src = config.logoUrl; logo.append(preview);
      button(logo,'Scegli dalla libreria media',() => { const frame = wp.media({title:'Logo aziendale',library:{type:'image'},multiple:false,button:{text:'Usa questo logo'}}); frame.on('select',() => { const image = frame.state().get('selection').first().toJSON(); data.company.logo = image.id; config.logoUrl = image.sizes?.thumbnail?.url || image.url; renderPanel('company'); }); frame.open(); });
      button(logo,'Rimuovi logo',() => { data.company.logo = 0; config.logoUrl = ''; renderPanel('company'); });
    }
  }
  document.getElementById('marrison-business-info-form').addEventListener('submit',() => { document.getElementById('marrison-business-info-payload').value = JSON.stringify(data); });
  renderPanel('company'); completeness(); document.getElementById('marrison-business-info-submit').disabled = false;
})();
