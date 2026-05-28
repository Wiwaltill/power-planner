const apiBase = (window.APP_BASE_PATH || '') + '/api';
const apiUrl = apiBase + '/devices';
const settingsUrl = apiBase + '/settings';
let devices = [];
let deviceSettings = {brands: [], categories: [], connectors: []};
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const byId = id => document.getElementById(id);
const amp = d => Number(d.power_w || 0) / Number(d.voltage_v || 230);

async function fetchJson(url, opts = {}) {
  const r = await fetch(url, {credentials:'same-origin', ...opts});
  const text = await r.text();
  let data = {};
  try { data = text ? JSON.parse(text) : {}; } catch(e) { throw new Error('Ungültige Serverantwort: ' + text.slice(0,160)); }
  if (!r.ok) throw new Error(data.error || 'Serverfehler');
  return data;
}

async function loadSettings() {
  deviceSettings = await fetchJson(settingsUrl);
  renderSelect('brand', deviceSettings.brands || [], 'Marke auswählen');
  renderSelect('category', deviceSettings.categories || [], 'Kategorie auswählen');
  renderSelect('connector', deviceSettings.connectors || [], 'Anschluss auswählen');
}

function renderSelect(id, rows, placeholder) {
  const el = byId(id);
  const current = el.value;
  el.innerHTML = `<option value="">${esc(placeholder)}</option>` + rows.map(r => `<option value="${esc(r.name)}">${esc(r.name)}</option>`).join('');
  if (current && [...el.options].some(o => o.value === current)) el.value = current;
}

async function loadDevices(){
  devices = await fetchJson(apiUrl);
  renderDevices();
}

function renderDevices(){
  const rows = byId('deviceRows');
  rows.innerHTML = devices.length ? devices.map(d => `<tr><td><strong>${esc(d.name)}</strong><div class="small-muted">${esc(d.category || '-')} ${d.notes ? ' · '+esc(d.notes) : ''}</div></td><td>${esc(d.brand || '-')}</td><td>${Number(d.power_w || 0).toLocaleString('de-DE')} W</td><td>${amp(d).toFixed(2).replace('.', ',')} A</td><td>${esc(d.connector || '-')}</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" onclick="editDevice(${Number(d.id)})">Bearbeiten</button><button class="btn btn-sm btn-outline-danger" onclick="deleteDevice(${Number(d.id)})">Löschen</button></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">Noch keine Geräte angelegt.</td></tr>';
}

function resetForm(){
  byId('deviceForm').reset();
  byId('deviceId').value='';
  byId('voltage').value=230;
}

function editDevice(id){
  const d=devices.find(x=>Number(x.id)===Number(id));
  if(!d) return;
  byId('deviceId').value=d.id;
  byId('name').value=d.name || '';
  ensureOption(byId('brand'), d.brand || '');
  ensureOption(byId('category'), d.category || '');
  byId('brand').value=d.brand || '';
  byId('category').value=d.category || '';
  ensureOption(byId('connector'), d.connector || '');
  byId('connector').value=d.connector || '';
  byId('power').value=d.power_w || 0;
  byId('voltage').value=d.voltage_v || 230;
  byId('connector').value=d.connector || '';
  byId('notes').value=d.notes || '';
}
window.editDevice = editDevice;

function ensureOption(select, value) {
  if (!value) return;
  if (![...select.options].some(o => o.value === value)) {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value + ' (nicht in Einstellungen)';
    select.appendChild(option);
  }
}

async function deleteDevice(id){
  if(!confirm('Gerät wirklich löschen?')) return;
  try { await fetchJson(`${apiUrl}?id=${id}`,{method:'DELETE'}); await loadDevices(); }
  catch(err) { alert(err.message); }
}
window.deleteDevice = deleteDevice;

byId('deviceForm').addEventListener('submit', async e => {
  e.preventDefault();
  const payload={
    id: Number(byId('deviceId').value || 0),
    name: byId('name').value,
    brand: byId('brand').value,
    category: byId('category').value,
    power_w: Number(byId('power').value || 0),
    voltage_v: Number(byId('voltage').value || 230),
    connector: byId('connector').value,
    notes: byId('notes').value
  };
  try {
    await fetchJson(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    resetForm();
    await loadDevices();
  } catch(err) { alert('Gerät konnte nicht gespeichert werden: ' + err.message); }
});

byId('resetForm').addEventListener('click', resetForm);
byId('exportDevices').addEventListener('click', () => { location.href = `${apiUrl}?export=1`; });
byId('importDevices').addEventListener('click', () => byId('importDevicesFile').click());
byId('importDevicesFile').addEventListener('change', async e => {
  const f=e.target.files[0];
  if(!f) return;
  try{
    const json=JSON.parse(await f.text());
    const result=await fetchJson(`${apiUrl}?import=1`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(json)});
    await loadDevices();
    alert(`${result.imported} Gerät(e) importiert.`);
  } catch(err){ alert(err.message || 'Import fehlgeschlagen.'); }
  finally{ e.target.value=''; }
});

(async function init(){
  try { await loadSettings(); await loadDevices(); }
  catch(err) { alert(err.message); }
})();
