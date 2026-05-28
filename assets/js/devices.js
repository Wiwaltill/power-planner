const apiUrl = 'api/devices.php';
let devices = [];
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const amp = d => Number(d.power_w || 0) / Number(d.voltage_v || 230);
async function loadDevices(){ const r = await fetch(apiUrl); devices = await r.json(); renderDevices(); }
function renderDevices(){
  const rows = document.getElementById('deviceRows');
  rows.innerHTML = devices.length ? devices.map(d => `<tr><td><strong>${esc(d.name)}</strong><div class="small-muted">${esc(d.category || '-')} ${d.notes ? ' · '+esc(d.notes) : ''}</div></td><td>${esc(d.brand || '-')}</td><td>${Number(d.power_w).toLocaleString('de-DE')} W</td><td>${amp(d).toFixed(2).replace('.', ',')} A</td><td>${esc(d.connector || '-')}</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" onclick="editDevice(${d.id})">Bearbeiten</button><button class="btn btn-sm btn-outline-danger" onclick="deleteDevice(${d.id})">Löschen</button></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">Noch keine Geräte angelegt.</td></tr>';
}
function resetForm(){ document.getElementById('deviceForm').reset(); document.getElementById('deviceId').value=''; document.getElementById('voltage').value=230; }
function editDevice(id){ const d=devices.find(x=>Number(x.id)===Number(id)); if(!d)return; deviceId.value=d.id; name.value=d.name; brand.value=d.brand||''; category.value=d.category||''; power.value=d.power_w; voltage.value=d.voltage_v||230; connector.value=d.connector||''; notes.value=d.notes||''; }
window.editDevice = editDevice;
async function deleteDevice(id){ if(!confirm('Gerät wirklich löschen?')) return; await fetch(`${apiUrl}?id=${id}`,{method:'DELETE'}); await loadDevices(); }
window.deleteDevice = deleteDevice;
document.getElementById('deviceForm').addEventListener('submit', async e => { e.preventDefault(); const payload={id:deviceId.value||0,name:name.value,brand:brand.value,category:category.value,power_w:Number(power.value),voltage_v:Number(voltage.value||230),connector:connector.value,notes:notes.value}; const r=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); if(!r.ok){ const err=await r.json(); alert(err.error||'Speichern fehlgeschlagen.'); return;} resetForm(); await loadDevices(); });
document.getElementById('resetForm').addEventListener('click', resetForm);
document.getElementById('exportDevices').addEventListener('click', () => { location.href = `${apiUrl}?export=1`; });
document.getElementById('importDevices').addEventListener('click', () => importDevicesFile.click());
document.getElementById('importDevicesFile').addEventListener('change', async e => { const f=e.target.files[0]; if(!f)return; try{ const json=JSON.parse(await f.text()); const r=await fetch(`${apiUrl}?import=1`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(json)}); const result=await r.json(); if(!r.ok) throw new Error(result.error||'Import fehlgeschlagen.'); await loadDevices(); alert(`${result.imported} Gerät(e) importiert.`);}catch(err){alert(err.message||'Import fehlgeschlagen.');} finally{e.target.value='';} });
loadDevices();
