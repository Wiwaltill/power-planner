const phases = ['L1', 'L2', 'L3'];
let devices = [];
let activeProject = localStorage.getItem('stromplan.activeProject') || 'Standard';
let projectMeta = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.meta`) || '{}');
let circuits = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.circuits`) || '[{"id":"default","name":"Standard-Stromkreis"}]');
if (!Array.isArray(circuits) || !circuits.length) circuits = [{ id: 'default', name: 'Standard-Stromkreis' }];
let activeCircuit = localStorage.getItem(`stromplan.project.${activeProject}.activeCircuit`) || circuits[0].id;
let plan = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.plan`) || localStorage.getItem('stromplan.plan') || '[]');
plan = plan.map(item => ({ remarks: '', circuit_id: item.circuit_id || item.circuitId || 'default', circuit_name: item.circuit_name || item.circuit || 'Standard-Stromkreis', ...item }));
let undoStack = [];
let redoStack = [];
const ampLimit = 16;

const fmtW = value => `${Math.round(value).toLocaleString('de-DE')} W`;
const fmtA = value => `${Number(value || 0).toFixed(2).replace('.', ',')} A`;
const csvEsc = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
const calcAmp = (watts, voltage) => watts / voltage;
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

function circuitName(id) {
  return circuits.find(circuit => circuit.id === id)?.name || 'Standard-Stromkreis';
}
function ensureActiveCircuit() {
  if (!circuits.some(circuit => circuit.id === activeCircuit)) activeCircuit = circuits[0]?.id || 'default';
}
function saveCircuits() {
  ensureActiveCircuit();
  localStorage.setItem(`stromplan.project.${activeProject}.circuits`, JSON.stringify(circuits));
  localStorage.setItem(`stromplan.project.${activeProject}.activeCircuit`, activeCircuit);
}
function renderCircuitSelects() {
  ensureActiveCircuit();
  const html = circuits.map(circuit => `<option value="${esc(circuit.id)}" ${circuit.id === activeCircuit ? 'selected' : ''}>${esc(circuit.name)}</option>`).join('');
  ['activeCircuitSelect', 'circuitSelect'].forEach(id => {
    const select = document.getElementById(id);
    if (select) select.innerHTML = html;
    if (select) select.value = activeCircuit;
  });
}
function getOrCreateCircuitByName(name) {
  const clean = String(name || '').trim() || 'Standard-Stromkreis';
  let existing = circuits.find(circuit => circuit.name === clean);
  if (existing) return existing.id;
  const id = `circuit-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  circuits.push({ id, name: clean });
  return id;
}
function migratePlanCircuits() {
  let changed = false;
  plan.forEach(item => {
    if ((!item.circuit_id || item.circuit_id === 'default') && item.circuit_name && item.circuit_name !== 'Standard-Stromkreis') {
      item.circuit_id = getOrCreateCircuitByName(item.circuit_name);
      changed = true;
    }
    item.circuit_name = circuitName(item.circuit_id || 'default');
  });
  if (changed) savePlan();
}

function deviceLabel(device) {
  return `${device.brand ? device.brand + ' - ' : ''}${device.name} (${device.power_w} W)`;
}

function getDeviceSearchText(device) {
  return [deviceLabel(device), device.brand, device.name, device.category, device.power_w].filter(Boolean).join(' ');
}

function findDeviceBySearchValue(value) {
  const cleanValue = String(value || '').trim().toLowerCase();
  if (!cleanValue) return null;

  return devices.find(device => device.id === value)
    || devices.find(device => deviceLabel(device).toLowerCase() === cleanValue)
    || devices.find(device => getDeviceSearchText(device).toLowerCase().includes(cleanValue))
    || null;
}

function selectDevice(deviceId) {
  const device = devices.find(device => device.id === deviceId);
  if (!device) return;

  document.getElementById('deviceSelect').value = device.id;
  document.getElementById('deviceDropdownLabel').textContent = deviceLabel(device);
  document.getElementById('deviceSearch').value = '';
  renderDeviceSelect();

  const dropdownButton = document.getElementById('deviceDropdownButton');
  const dropdown = bootstrap.Dropdown.getOrCreateInstance(dropdownButton);
  dropdown.hide();
}
window.selectDevice = selectDevice;

function renderDeviceSelect() {
  const hiddenSelect = document.getElementById('deviceSelect');
  const search = document.getElementById('deviceSearch');
  const options = document.getElementById('deviceOptions');
  const label = document.getElementById('deviceDropdownLabel');
  const info = document.getElementById('deviceSearchInfo');
  const term = (search?.value || '').trim().toLowerCase();
  const selectedCategory = document.getElementById('categoryFilter')?.value || '';

  const selectedDevice = devices.find(device => device.id === hiddenSelect.value);
  if (label) label.textContent = selectedDevice ? deviceLabel(selectedDevice) : 'Gerät suchen oder auswählen...';

  const filteredDevices = devices.filter(device => {
    const haystack = getDeviceSearchText(device).toLowerCase();
    return (!selectedCategory || device.category === selectedCategory) && (!term || haystack.includes(term));
  });

  if (!devices.length) {
    options.innerHTML = '<div class="dropdown-item text-muted small">Keine Geräte vorhanden.</div>';
    hiddenSelect.value = '';
    if (info) info.textContent = 'Keine Geräte vorhanden.';
    return;
  }

  options.innerHTML = filteredDevices.length
    ? filteredDevices.map(device => {
      const active = device.id === hiddenSelect.value ? ' active' : '';
      return `<button type="button" class="list-group-item list-group-item-action device-option${active}" onclick="selectDevice('${esc(device.id)}')">
        <span class="fw-semibold">${esc(device.brand ? device.brand + ' · ' + device.name : device.name)}</span>
        <span class="small text-muted d-block">${esc(device.category || '-')} · ${esc(device.power_w)} W</span>
      </button>`;
    }).join('')
    : '<div class="dropdown-item text-muted small">Kein Gerät gefunden.</div>';

  if (info) info.textContent = term
    ? `${filteredDevices.length} von ${devices.length} Geräten gefunden.`
    : 'Gerät per Bootstrap-Dropdown öffnen und direkt filtern.';
}

function renderCategoryFilter() {
  const select = document.getElementById('categoryFilter');
  if (!select) return;
  const current = select.value;
  const categories = [...new Set(devices.map(device => device.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'de'));
  select.innerHTML = '<option value="">Alle Kategorien</option>' + categories.map(category => `<option value="${esc(category)}">${esc(category)}</option>`).join('');
  select.value = categories.includes(current) ? current : '';
}

async function loadDevices() {
  const response = await fetch('devices?api=1');
  devices = await response.json();
  renderCategoryFilter();
  renderDeviceSelect();
}

function savePlan() {
  saveCircuits();
  localStorage.setItem(`stromplan.project.${activeProject}.plan`, JSON.stringify(plan));
  localStorage.setItem('stromplan.activeProject', activeProject);
  localStorage.setItem('stromplan.plan', JSON.stringify(plan));
}

function phaseTotals() {
  const totals = { L1: { watts: 0, amps: 0 }, L2: { watts: 0, amps: 0 }, L3: { watts: 0, amps: 0 } };
  plan.filter(item => (item.circuit_id || 'default') === activeCircuit).forEach(item => {
    if (!totals[item.phase]) item.phase = 'L1';
    totals[item.phase].watts += Number(item.total_w || 0);
    totals[item.phase].amps += Number(item.total_a || 0);
  });
  return totals;
}

function statusClass(amps) {
  if (amps >= ampLimit) return 'danger';
  if (amps >= ampLimit * 0.8) return 'warning';
  return '';
}

function renderPhaseBoards() {
  const totals = phaseTotals();
  const boards = document.getElementById('phaseBoards');

  boards.innerHTML = phases.map(phase => {
    const items = plan.filter(item => (item.circuit_id || 'default') === activeCircuit && item.phase === phase);
    const amps = totals[phase].amps;
    const progress = Math.min((amps / ampLimit) * 100, 100);
    const status = statusClass(amps);

    return `<div class="col-12 col-xl-4">
      <section class="card phase-dropzone ${status}" data-phase="${phase}">
        <div class="phase-board-header p-3 border-bottom">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h4 mb-0">${phase}</h3>
            <span class="badge badge-soft">${items.length} Gerät${items.length === 1 ? '' : 'e'}</span>
          </div>
          <div class="phase-value">${fmtA(amps)}</div>
          <div class="small-muted mb-2">${fmtW(totals[phase].watts)} gesamt</div>
          <div class="progress"><div class="progress-bar" style="width:${progress}%"></div></div>
        </div>
        <div class="phase-items p-3" data-phase="${phase}">
          ${items.length ? items.map(item => renderPlanCard(item)).join('') : '<div class="empty-phase">Geräte hier ablegen</div>'}
        </div>
      </section>
    </div>`;
  }).join('');

  registerDragAndDrop();
}

function renderPlanCard(item) {
  const index = plan.findIndex(entry => entry.id === item.id);
  return `<article class="plan-card" draggable="true" data-id="${item.id}">
    <div class="d-flex justify-content-between gap-2">
      <div>
        <strong>${item.brand ? item.brand + ' · ' : ''}${item.name}</strong>
        <div class="small-muted">${item.category || '-'} · Anzahl: ${item.quantity} · ${fmtW(item.total_w)}</div>
        <div class="small-muted">Stromkreis: ${esc(item.circuit_name || circuitName(item.circuit_id))}</div>
        ${item.remarks ? `<div class="plan-remarks mt-1">${esc(item.remarks)}</div>` : ''}
      </div>
      <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})" title="Entfernen">×</button>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2">
      <span class="badge text-bg-dark">${item.phase}</span>
      <span class="fw-semibold">${fmtA(item.total_a)}</span>
    </div>
  </article>`;
}

function registerDragAndDrop() {
  document.querySelectorAll('.plan-card').forEach(card => {
    card.addEventListener('dragstart', event => {
      event.dataTransfer.setData('text/plain', card.dataset.id);
      event.dataTransfer.effectAllowed = 'move';
      card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('dragging'));
  });

  document.querySelectorAll('.phase-dropzone, .phase-items').forEach(zone => {
    zone.addEventListener('dragover', event => {
      event.preventDefault();
      zone.closest('.phase-dropzone').classList.add('drag-over');
    });
    zone.addEventListener('dragleave', event => {
      if (!zone.contains(event.relatedTarget)) zone.closest('.phase-dropzone').classList.remove('drag-over');
    });
    zone.addEventListener('drop', event => {
      event.preventDefault();
      const id = event.dataTransfer.getData('text/plain');
      const phase = zone.dataset.phase || zone.closest('.phase-dropzone').dataset.phase;
      moveItemToPhase(id, phase);
      document.querySelectorAll('.drag-over').forEach(element => element.classList.remove('drag-over'));
    });
  });
}

function snapshotPlan() {
  undoStack.push(JSON.stringify(plan));
  if (undoStack.length > 30) undoStack.shift();
  redoStack = [];
}
function restorePlanFrom(serialized) {
  plan = JSON.parse(serialized || '[]');
  savePlan();
  renderPlan();
}
function undoPlan() {
  if (!undoStack.length) return;
  redoStack.push(JSON.stringify(plan));
  restorePlanFrom(undoStack.pop());
}
function redoPlan() {
  if (!redoStack.length) return;
  undoStack.push(JSON.stringify(plan));
  restorePlanFrom(redoStack.pop());
}

function moveItemToPhase(id, phase) {
  const item = plan.find(entry => entry.id === id);
  if (!item || !phases.includes(phase)) return;
  snapshotPlan();
  item.phase = phase;
  savePlan();
  renderPlan();
}

function recalculateItem(item) {
  const device = devices.find(device => device.id === item.device_id);
  const powerW = Number(device?.power_w || item.power_w || (Number(item.total_w || 0) / Math.max(Number(item.quantity || 1), 1)) || 0);
  const quantity = Math.max(1, Number(item.quantity || 1));
  const voltage = Math.max(1, Number(item.voltage_v || device?.voltage_v || 230));
  item.power_w = powerW;
  item.quantity = quantity;
  item.voltage_v = voltage;
  item.total_w = quantity * powerW;
  item.total_a = calcAmp(item.total_w, voltage);
}

function updateItemQuantity(id, value) {
  const item = plan.find(entry => entry.id === id);
  if (!item) return;
  snapshotPlan();
  item.quantity = Math.max(1, Number(value || 1));
  recalculateItem(item);
  savePlan();
  renderPlan();
}

function updateItemRemarks(id, value) {
  const item = plan.find(entry => entry.id === id);
  if (!item) return;
  snapshotPlan();
  item.remarks = String(value || '').trim();
  savePlan();
  renderPlan();
}
function updateItemPhase(id, value) {
  const item = plan.find(entry => entry.id === id);
  if (!item || !phases.includes(value)) return;
  snapshotPlan();
  item.phase = value;
  savePlan();
  renderPlan();
}

window.updateItemQuantity = updateItemQuantity;
window.updateItemRemarks = updateItemRemarks;
function updateItemCircuit(id, value) {
  const item = plan.find(entry => entry.id === id);
  if (!item || !circuits.some(circuit => circuit.id === value)) return;
  snapshotPlan();
  item.circuit_id = value;
  item.circuit_name = circuitName(value);
  savePlan();
  renderPlan();
}
window.updateItemPhase = updateItemPhase;
window.updateItemCircuit = updateItemCircuit;

function renderPrintExport() {
  const totals = phaseTotals();
  const now = new Date();
  document.getElementById('printDate').textContent = `Projekt: ${projectMeta.name || activeProject} · Stromkreis: ${circuitName(activeCircuit)} · Kunde: ${projectMeta.client || '-'} · Techniker: ${projectMeta.technician || '-'} · Exportiert am ${now.toLocaleDateString('de-DE')} um ${now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`;
  const header = document.querySelector('.print-header');
  if (header && projectMeta.logo) header.style.backgroundImage = `url(${projectMeta.logo})`;

  document.getElementById('printSummary').innerHTML = phases.map(phase => {
    const amps = totals[phase].amps;
    const statusText = amps >= ampLimit ? 'Über 16 A' : amps >= ampLimit * 0.8 ? 'Nahe Grenze' : 'OK';
    return `<div class="print-summary-card">
      <div class="print-phase-name">${phase}</div>
      <div><strong>${fmtA(amps)}</strong></div>
      <div>${fmtW(totals[phase].watts)}</div>
      <div>${statusText}</div>
    </div>`;
  }).join('');

  document.getElementById('printPhaseTables').innerHTML = phases.map(phase => {
    const rows = plan.filter(item => (item.circuit_id || 'default') === activeCircuit && item.phase === phase);
    const body = rows.length ? rows.map(item => `<tr>
      <td>${esc(item.brand ? item.brand + ' · ' + item.name : item.name)}</td>
      <td>${esc(item.category || '-')}</td>
      <td>${item.quantity}</td>
      <td>${fmtW(item.total_w)}</td>
      <td>${fmtA(item.total_a)}</td>
      <td>${esc(item.remarks || '-')}</td>
    </tr>`).join('') : '<tr><td colspan="6">Keine Geräte auf dieser Phase.</td></tr>';

    return `<div class="print-phase-block">
      <h3>${phase} · ${fmtA(totals[phase].amps)} · ${fmtW(totals[phase].watts)}</h3>
      <table class="print-table">
        <thead><tr><th>Gerät</th><th>Kategorie</th><th>Anzahl</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th></tr></thead>
        <tbody>${body}</tbody>
      </table>
    </div>`;
  }).join('');

  document.getElementById('printRows').innerHTML = plan.length ? plan.map(item => `<tr>
    <td>${esc(item.name)}</td>
    <td>${esc(item.brand || '-')}</td>
    <td>${esc(item.category || '-')}</td>
    <td>${item.quantity}</td>
    <td>${esc(item.circuit_name || circuitName(item.circuit_id))}</td>
    <td>${item.phase}</td>
    <td>${fmtW(item.total_w)}</td>
    <td>${fmtA(item.total_a)}</td>
    <td>${esc(item.remarks || '-')}</td>
  </tr>`).join('') : '<tr><td colspan="9">Noch keine Geräte im Plan.</td></tr>';
}

function exportPdf() {
  renderPrintExport();
  window.print();
}

function renderPlan() {
  const body = document.getElementById('planRows');
  if (!plan.length) {
    body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Noch keine Geräte im Plan.</td></tr>';
  } else {
    body.innerHTML = plan.map((item, index) => `<tr>
      <td>${esc(item.name)}</td>
      <td>${esc(item.brand || '-')}</td>
      <td>${esc(item.category || '-')}</td>
      <td style="min-width: 95px;"><input type="number" class="form-control form-control-sm plan-quantity-input" min="1" value="${item.quantity}" onchange="updateItemQuantity('${item.id}', this.value)"></td>
      <td style="min-width: 160px;"><select class="form-select form-select-sm" onchange="updateItemCircuit('${item.id}', this.value)">${circuits.map(circuit => `<option value="${esc(circuit.id)}" ${circuit.id === (item.circuit_id || 'default') ? 'selected' : ''}>${esc(circuit.name)}</option>`).join('')}</select></td>
      <td><select class="form-select form-select-sm" onchange="updateItemPhase('${item.id}', this.value)">${phases.map(phase => `<option value="${phase}" ${phase === item.phase ? 'selected' : ''}>${phase}</option>`).join('')}</select></td>
      <td>${fmtW(item.total_w)}</td>
      <td>${fmtA(item.total_a)}</td>
      <td style="min-width: 220px;"><textarea class="form-control form-control-sm plan-remarks-input" rows="1" placeholder="Bemerkung" onchange="updateItemRemarks('${item.id}', this.value)">${esc(item.remarks || '')}</textarea></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})">Entfernen</button></td>
    </tr>`).join('');
  }
  renderPhaseBoards();
}

function removeItem(index) {
  snapshotPlan();
  plan.splice(index, 1);
  savePlan();
  renderPlan();
}
window.removeItem = removeItem;

document.getElementById('loadForm').addEventListener('submit', event => {
  event.preventDefault();
  const device = devices.find(d => d.id === document.getElementById('deviceSelect').value)
    || findDeviceBySearchValue(document.getElementById('deviceSearch').value);
  if (!device) {
    alert('Bitte ein gültiges Gerät aus der Auswahl wählen.');
    return;
  }
  const quantity = Number(document.getElementById('quantity').value || 1);
  const voltage = Number(document.getElementById('voltage').value || device.voltage_v || 230);
  const totalW = quantity * Number(device.power_w);
  snapshotPlan();
  plan.push({
    id: crypto.randomUUID(),
    device_id: device.id,
    name: device.name,
    brand: device.brand || '',
    category: device.category,
    quantity,
    phase: document.getElementById('phase').value,
    remarks: document.getElementById('remarks').value.trim(),
    circuit_id: document.getElementById('circuitSelect').value || activeCircuit,
    circuit_name: circuitName(document.getElementById('circuitSelect').value || activeCircuit),
    voltage_v: voltage,
    power_w: Number(device.power_w),
    total_w: totalW,
    total_a: calcAmp(totalW, voltage)
  });
  ['remarks'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('deviceSearch').value = '';
  document.getElementById('deviceSelect').value = '';
  document.getElementById('deviceDropdownLabel').textContent = 'Gerät suchen oder auswählen...';
  renderDeviceSelect();
  savePlan();
  renderPlan();
});

document.getElementById('clearPlan').addEventListener('click', () => {
  if (confirm('Stromplan wirklich leeren?')) {
    snapshotPlan();
  plan = [];
    savePlan();
    renderPlan();
  }
});


function normalizeImportedPlan(importedPlan) {
  if (!Array.isArray(importedPlan)) throw new Error('Keine gültige Planliste gefunden.');
  return importedPlan.map(item => {
    const quantity = Number(item.quantity || 1);
    const voltage = Number(item.voltage_v || 230);
    const powerW = Number(item.power_w || (Number(item.total_w || 0) / Math.max(quantity, 1)) || 0);
    const totalW = Number(item.total_w || (quantity * powerW));
    return {
      id: item.id || crypto.randomUUID(),
      device_id: item.device_id || '',
      name: String(item.name || 'Unbenanntes Gerät'),
      brand: String(item.brand || ''),
      category: String(item.category || ''),
      quantity,
      phase: phases.includes(item.phase) ? item.phase : 'L1',
      remarks: String(item.remarks || ''),
      circuit_id: String(item.circuit_id || item.circuitId || getOrCreateCircuitByName(item.circuit_name || item.circuit || 'Standard-Stromkreis')),
      circuit_name: circuitName(String(item.circuit_id || item.circuitId || getOrCreateCircuitByName(item.circuit_name || item.circuit || 'Standard-Stromkreis'))),
      voltage_v: voltage,
      power_w: powerW,
      total_w: totalW,
      total_a: Number(item.total_a || calcAmp(totalW, voltage))
    };
  });
}

function importJsonFile(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const data = JSON.parse(reader.result);
      const importedPlan = Array.isArray(data) ? data : data.plan;
      if (Array.isArray(data.circuits) && data.circuits.length) {
        circuits = data.circuits.map(circuit => ({ id: String(circuit.id || crypto.randomUUID()), name: String(circuit.name || 'Stromkreis') }));
        activeCircuit = data.active_circuit || circuits[0].id;
      }
      const nextPlan = normalizeImportedPlan(importedPlan);
      if (!confirm(`Importierten Plan mit ${nextPlan.length} Einträgen laden? Der aktuelle Plan wird ersetzt.`)) return;
      snapshotPlan();
  plan = nextPlan;
      savePlan();
      renderPlan();
    } catch (error) {
      alert('JSON konnte nicht importiert werden: ' + error.message);
    } finally {
      document.getElementById('importJsonFile').value = '';
    }
  };
  reader.readAsText(file);
}

document.getElementById('exportPdf').addEventListener('click', exportPdf);
document.getElementById('importJson').addEventListener('click', () => document.getElementById('importJsonFile').click());
document.getElementById('importJsonFile').addEventListener('change', event => importJsonFile(event.target.files[0]));

document.getElementById('deviceSearch').addEventListener('input', renderDeviceSelect);

document.getElementById('exportJson').addEventListener('click', () => {
  const blob = new Blob([JSON.stringify({ exported_at: new Date().toISOString(), project: projectMeta, circuits, active_circuit: activeCircuit, plan, totals: phaseTotals() }, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'stromplan-export.json';
  a.click();
  URL.revokeObjectURL(url);
});


function loadProjectList() {
  const select = document.getElementById('projectSelect');
  if (!select) return;
  const names = Object.keys(localStorage).filter(key => key.startsWith('stromplan.project.') && key.endsWith('.plan')).map(key => key.replace('stromplan.project.', '').replace('.plan', ''));
  if (!names.includes(activeProject)) names.push(activeProject);
  select.innerHTML = [...new Set(names)].sort().map(name => `<option value="${esc(name)}">${esc(name)}</option>`).join('');
  select.value = activeProject;
}
function loadProjectMetaToForm() {
  document.getElementById('projectName').value = projectMeta.name || activeProject;
  document.getElementById('projectClient').value = projectMeta.client || '';
  document.getElementById('projectTechnician').value = projectMeta.technician || '';
  document.getElementById('projectLogo').value = projectMeta.logo || '';
}
function saveProjectMeta() {
  const name = document.getElementById('projectName').value.trim() || 'Standard';
  projectMeta = {
    name,
    client: document.getElementById('projectClient').value.trim(),
    technician: document.getElementById('projectTechnician').value.trim(),
    logo: document.getElementById('projectLogo').value.trim()
  };
  if (name !== activeProject) {
    localStorage.setItem(`stromplan.project.${name}.plan`, JSON.stringify(plan));
    localStorage.setItem(`stromplan.project.${name}.circuits`, JSON.stringify(circuits));
    localStorage.setItem(`stromplan.project.${name}.activeCircuit`, activeCircuit);
    activeProject = name;
  }
  localStorage.setItem(`stromplan.project.${activeProject}.meta`, JSON.stringify(projectMeta));
  savePlan();
  loadProjectList();
}
function switchProject(name) {
  activeProject = name || 'Standard';
  projectMeta = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.meta`) || '{}');
  circuits = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.circuits`) || '[{"id":"default","name":"Standard-Stromkreis"}]');
  if (!Array.isArray(circuits) || !circuits.length) circuits = [{ id: 'default', name: 'Standard-Stromkreis' }];
  activeCircuit = localStorage.getItem(`stromplan.project.${activeProject}.activeCircuit`) || circuits[0].id;
  plan = JSON.parse(localStorage.getItem(`stromplan.project.${activeProject}.plan`) || '[]');
  undoStack = [];
  redoStack = [];
  localStorage.setItem('stromplan.activeProject', activeProject);
  loadProjectMetaToForm();
  migratePlanCircuits();
  renderCircuitSelects();
  renderPlan();
}
function autoDistributePlan() {
  const circuitItems = plan.filter(item => (item.circuit_id || 'default') === activeCircuit);
  if (!circuitItems.length) return;
  snapshotPlan();
  const sorted = [...circuitItems].sort((a, b) => Number(b.total_a || 0) - Number(a.total_a || 0));
  const totals = { L1: 0, L2: 0, L3: 0 };
  sorted.forEach(item => {
    const target = phases.reduce((a, b) => totals[a] <= totals[b] ? a : b);
    item.phase = target;
    totals[target] += Number(item.total_a || 0);
  });
  savePlan();
  renderPlan();
}
function exportCsv() {
  const rows = [['Gerät','Marke','Kategorie','Anzahl','Stromkreis','Phase','Leistung W','Strom A','Bemerkungen']]
    .concat(plan.map(item => [item.name, item.brand, item.category, item.quantity, item.circuit_name || circuitName(item.circuit_id), item.phase, item.total_w, fmtA(item.total_a), item.remarks]));
  const blob = new Blob([rows.map(row => row.map(csvEsc).join(';')).join('\n')], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `stromplan-${activeProject}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

function addCircuit() {
  const input = document.getElementById('newCircuitName');
  const name = input.value.trim();
  if (!name) return;
  const id = `circuit-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  circuits.push({ id, name });
  activeCircuit = id;
  input.value = '';
  savePlan();
  renderCircuitSelects();
  renderPlan();
}
function deleteActiveCircuit() {
  if (circuits.length <= 1) {
    alert('Mindestens ein Stromkreis muss vorhanden bleiben.');
    return;
  }
  const name = circuitName(activeCircuit);
  const affected = plan.filter(item => (item.circuit_id || 'default') === activeCircuit).length;
  if (!confirm(`Stromkreis "${name}" löschen? ${affected} Planeinträge werden in den ersten verbleibenden Stromkreis verschoben.`)) return;
  const oldId = activeCircuit;
  circuits = circuits.filter(circuit => circuit.id !== oldId);
  activeCircuit = circuits[0].id;
  plan.forEach(item => {
    if ((item.circuit_id || 'default') === oldId) {
      item.circuit_id = activeCircuit;
      item.circuit_name = circuitName(activeCircuit);
    }
  });
  savePlan();
  renderCircuitSelects();
  renderPlan();
}
function changeActiveCircuit(value) {
  if (!circuits.some(circuit => circuit.id === value)) return;
  activeCircuit = value;
  saveCircuits();
  renderCircuitSelects();
  renderPlan();
}

function toggleDarkMode() {
  document.body.classList.toggle('dark-mode');
  localStorage.setItem('stromplan.darkMode', document.body.classList.contains('dark-mode') ? '1' : '0');
}
function toggleFullscreen() {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
  else document.exitFullscreen?.();
}
if (localStorage.getItem('stromplan.darkMode') === '1') document.body.classList.add('dark-mode');
loadProjectList();
loadProjectMetaToForm();
migratePlanCircuits();
renderCircuitSelects();
document.getElementById('saveProject').addEventListener('click', saveProjectMeta);
document.getElementById('projectSelect').addEventListener('change', event => switchProject(event.target.value));
document.getElementById('autoDistribute').addEventListener('click', autoDistributePlan);
document.getElementById('undoPlan').addEventListener('click', undoPlan);
document.getElementById('redoPlan').addEventListener('click', redoPlan);
document.getElementById('exportCsv').addEventListener('click', exportCsv);
document.getElementById('toggleDarkMode').addEventListener('click', toggleDarkMode);
document.getElementById('toggleFullscreen').addEventListener('click', toggleFullscreen);
document.getElementById('categoryFilter').addEventListener('change', renderDeviceSelect);
document.getElementById('activeCircuitSelect').addEventListener('change', event => changeActiveCircuit(event.target.value));
document.getElementById('circuitSelect').addEventListener('change', event => changeActiveCircuit(event.target.value));
document.getElementById('addCircuit').addEventListener('click', addCircuit);
document.getElementById('deleteCircuit').addEventListener('click', deleteActiveCircuit);

loadDevices().then(renderPlan);
