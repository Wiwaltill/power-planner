const apiSettings = (window.APP_BASE_PATH || '') + '/api/settings';
const tagColorLabels = {secondary:'Grau', primary:'Blau', success:'Grün', warning:'Gelb', danger:'Rot', info:'Hellblau', dark:'Dunkel'};
const tagColors = ['secondary','primary','success','warning','danger','info','dark'];
const escSettings = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function settingsJson(url, opts={}) {
  const r = await fetch(url, {credentials:'same-origin', ...opts});
  const text = await r.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) { throw new Error('Ungültige Serverantwort.'); }
  if (!r.ok) throw new Error(data.error || 'Serverfehler');
  return data;
}
function tagColorOptions(selected) {
  return tagColors.map(c=>`<option value="${c}" ${selected===c?'selected':''}>${tagColorLabels[c] || c}</option>`).join('');
}
function tagColorBadge(color, extraClass='') {
  const safeColor = tagColors.includes(color) ? color : 'secondary';
  return `<span class="badge text-bg-${safeColor} ${extraClass}">${tagColorLabels[safeColor] || safeColor}</span>`;
}
function updateTagPreview(select) {
  const preview = select.closest('form, .list-group-item')?.querySelector('.tag-color-preview');
  if (!preview) return;
  const color = tagColors.includes(select.value) ? select.value : 'secondary';
  preview.className = `badge text-bg-${color} tag-color-preview`;
  preview.textContent = tagColorLabels[color] || color;
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
    const color = tagColors.includes(row.color) ? row.color : 'secondary';
    const colorSelect = type === 'tags' ? `<div class="d-flex align-items-center gap-2 tag-color-editor">${tagColorBadge(color, 'tag-color-preview')}<select class="form-select form-select-sm" data-color-edit>${tagColorOptions(color)}</select></div>` : '';
    return `<div class="list-group-item d-flex flex-column flex-lg-row gap-2 align-items-lg-center"><input class="form-control form-control-sm" value="${escSettings(row.name)}" data-id="${row.id}" data-type="${type}">${colorSelect}<div class="d-flex gap-2 ms-lg-auto"><button class="btn btn-sm btn-outline-secondary" onclick="saveSetting('${type}', ${row.id}, this)">Speichern</button><button class="btn btn-sm btn-outline-danger" onclick="deleteSetting('${type}', ${row.id})">Löschen</button></div></div>`;
  }).join('') : '<div class="text-muted small">Noch keine Einträge.</div>';
}
async function saveSetting(type, id, btn) {
  const item = btn.closest('.list-group-item');
  const input = item.querySelector('input');
  await settingsJson(`${apiSettings}?type=${type}&id=${id}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:input.value, color: item.querySelector('[data-color-edit]')?.value || 'secondary'})});
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
document.addEventListener('change', event => {
  const select = event.target.closest('[data-color], [data-color-edit]');
  if (select) updateTagPreview(select);
});
document.querySelectorAll('[data-color]').forEach(updateTagPreview);
window.saveSetting = saveSetting;
window.deleteSetting = deleteSetting;
loadSettings().catch(err => AppUI.error(err.message));
