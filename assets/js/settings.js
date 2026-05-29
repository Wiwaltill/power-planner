const apiSettings = (window.APP_BASE_PATH || '') + '/api/settings';
const tagColorLabels = {secondary:'Grau', primary:'Blau', success:'Grün', warning:'Gelb', danger:'Rot', info:'Info', dark:'Dunkel'};
const escSettings = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function settingsJson(url, opts={}) {
  const r = await fetch(url, {credentials:'same-origin', ...opts});
  const text = await r.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) { throw new Error('Ungültige Serverantwort.'); }
  if (!r.ok) throw new Error(data.error || 'Serverfehler');
  return data;
}
async function loadSettings() {
  const data = await settingsJson(apiSettings);
  renderSettings('brands', data.brands || [], document.getElementById('brandRows'));
  renderSettings('categories', data.categories || [], document.getElementById('categoryRows'));
  renderSettings('connectors', data.connectors || [], document.getElementById('connectorRows'));
  renderSettings('tags', data.tags || [], document.getElementById('tagRows'));
}
function renderSettings(type, rows, el) {
  if (!el) return;
  el.innerHTML = rows.length ? rows.map(row => {
    const colorSelect = type === 'tags' ? `<select class="form-select form-select-sm" data-color-edit style="max-width:120px">
      ${['secondary','primary','success','warning','danger','info','dark'].map(c=>`<option value="${c}" ${row.color===c?'selected':''}>${tagColorLabels[c] || c}</option>`).join('')}
    </select>` : '';
    return `<div class="list-group-item d-flex gap-2 align-items-center"><input class="form-control form-control-sm" value="${escSettings(row.name)}" data-id="${row.id}" data-type="${type}">${colorSelect}<button class="btn btn-sm btn-outline-secondary" onclick="saveSetting('${type}', ${row.id}, this)">Speichern</button><button class="btn btn-sm btn-outline-danger" onclick="deleteSetting('${type}', ${row.id})">Löschen</button></div>`;
  }).join('') : '<div class="text-muted small">Noch keine Einträge.</div>';
}
async function saveSetting(type, id, btn) {
  const input = btn.parentElement.querySelector('input');
  await settingsJson(`${apiSettings}?type=${type}&id=${id}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:input.value, color: btn.parentElement.querySelector('[data-color-edit]')?.value || 'secondary'})});
  await loadSettings();
}
async function deleteSetting(type, id) {
  if (!(await AppUI.confirm('Eintrag löschen? Bereits gespeicherte Geräte behalten ihren Textwert.', {title:'Eintrag löschen', confirmText:'Löschen'}))) return;
  await settingsJson(`${apiSettings}?type=${type}&id=${id}`, {method:'DELETE'});
  await loadSettings();
}
document.querySelectorAll('[data-form]').forEach(form => form.addEventListener('submit', async e => {
  e.preventDefault();
  const type = form.dataset.form;
  const input = form.querySelector('input');
  try {
    await settingsJson(`${apiSettings}?type=${type}`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:input.value, color: form.querySelector('[data-color]')?.value || 'secondary'})});
    input.value = '';
    await loadSettings();
  } catch(err) { AppUI.error(err.message); }
}));
window.saveSetting = saveSetting;
window.deleteSetting = deleteSetting;
loadSettings().catch(err => AppUI.error(err.message));
