const phases = ['L1', 'L2', 'L3'];
let devices = [];
let plan = JSON.parse(localStorage.getItem('stromplan.plan') || '[]');
plan = plan.map(item => ({ remarks: '', ...item }));

const fmtW = value => `${Math.round(value).toLocaleString('de-DE')} W`;
const fmtA = value => `${value.toFixed(2).replace('.', ',')} A`;
const calcAmp = (watts, voltage) => watts / voltage;
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

function deviceLabel(device) {
  return `${device.brand ? device.brand + ' - ' : ''}${device.name} (${device.power_w} W)`;
}

function getDeviceSearchText(device) {
  return [deviceLabel(device), device.brand, device.name, device.category, device.power_w].filter(Boolean).join(' ');
}

function findDeviceBySearchValue(value) {
  const cleanValue = String(value || '').trim().toLowerCase();
  if (!cleanValue) return null;

  return devices.find(device => deviceLabel(device).toLowerCase() === cleanValue)
    || devices.find(device => device.id === value)
    || devices.find(device => getDeviceSearchText(device).toLowerCase().includes(cleanValue))
    || null;
}

function renderDeviceSelect() {
  const hiddenSelect = document.getElementById('deviceSelect');
  const search = document.getElementById('deviceSearch');
  const datalist = document.getElementById('deviceOptions');
  const info = document.getElementById('deviceSearchInfo');
  const term = (search?.value || '').trim().toLowerCase();

  const filteredDevices = devices.filter(device => {
    const haystack = getDeviceSearchText(device).toLowerCase();
    return !term || haystack.includes(term);
  });

  if (!devices.length) {
    datalist.innerHTML = '';
    hiddenSelect.value = '';
    if (info) info.textContent = 'Keine Geräte vorhanden.';
    return;
  }

  datalist.innerHTML = filteredDevices
    .map(device => `<option value="${esc(deviceLabel(device))}"></option>`)
    .join('');

  const selectedDevice = findDeviceBySearchValue(search.value);
  hiddenSelect.value = selectedDevice ? selectedDevice.id : '';

  if (info) info.textContent = term
    ? `${filteredDevices.length} von ${devices.length} Geräten gefunden.`
    : 'Direkt in diesem Feld suchen oder Gerät aus der Liste wählen.';
}

async function loadDevices() {
  const response = await fetch('devices.php?api=1');
  devices = await response.json();
  renderDeviceSelect();
}

function savePlan() {
  localStorage.setItem('stromplan.plan', JSON.stringify(plan));
}

function phaseTotals() {
  const totals = { L1: { watts: 0, amps: 0 }, L2: { watts: 0, amps: 0 }, L3: { watts: 0, amps: 0 } };
  plan.forEach(item => {
    if (!totals[item.phase]) item.phase = 'L1';
    totals[item.phase].watts += Number(item.total_w || 0);
    totals[item.phase].amps += Number(item.total_a || 0);
  });
  return totals;
}

function statusClass(amps) {
  if (amps >= 16) return 'danger';
  if (amps >= 13) return 'warning';
  return '';
}

function renderPhaseBoards() {
  const totals = phaseTotals();
  const boards = document.getElementById('phaseBoards');

  boards.innerHTML = phases.map(phase => {
    const items = plan.filter(item => item.phase === phase);
    const amps = totals[phase].amps;
    const progress = Math.min((amps / 16) * 100, 100);
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

function moveItemToPhase(id, phase) {
  const item = plan.find(entry => entry.id === id);
  if (!item || !phases.includes(phase)) return;
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
  item.quantity = Math.max(1, Number(value || 1));
  recalculateItem(item);
  savePlan();
  renderPlan();
}

function updateItemRemarks(id, value) {
  const item = plan.find(entry => entry.id === id);
  if (!item) return;
  item.remarks = String(value || '').trim();
  savePlan();
  renderPlan();
}

window.updateItemQuantity = updateItemQuantity;
window.updateItemRemarks = updateItemRemarks;

function renderPrintExport() {
  const totals = phaseTotals();
  const now = new Date();
  document.getElementById('printDate').textContent = `Exportiert am ${now.toLocaleDateString('de-DE')} um ${now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`;

  document.getElementById('printSummary').innerHTML = phases.map(phase => {
    const amps = totals[phase].amps;
    const statusText = amps >= 16 ? 'Über 16 A' : amps >= 13 ? 'Nahe Grenze' : 'OK';
    return `<div class="print-summary-card">
      <div class="print-phase-name">${phase}</div>
      <div><strong>${fmtA(amps)}</strong></div>
      <div>${fmtW(totals[phase].watts)}</div>
      <div>${statusText}</div>
    </div>`;
  }).join('');

  document.getElementById('printPhaseTables').innerHTML = phases.map(phase => {
    const rows = plan.filter(item => item.phase === phase);
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
    <td>${item.phase}</td>
    <td>${fmtW(item.total_w)}</td>
    <td>${fmtA(item.total_a)}</td>
    <td>${esc(item.remarks || '-')}</td>
  </tr>`).join('') : '<tr><td colspan="8">Noch keine Geräte im Plan.</td></tr>';
}

function exportPdf() {
  renderPrintExport();
  window.print();
}

function renderPlan() {
  const body = document.getElementById('planRows');
  if (!plan.length) {
    body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Noch keine Geräte im Plan.</td></tr>';
  } else {
    body.innerHTML = plan.map((item, index) => `<tr>
      <td>${esc(item.name)}</td>
      <td>${esc(item.brand || '-')}</td>
      <td>${esc(item.category || '-')}</td>
      <td style="min-width: 95px;"><input type="number" class="form-control form-control-sm plan-quantity-input" min="1" value="${item.quantity}" onchange="updateItemQuantity('${item.id}', this.value)"></td>
      <td><span class="badge text-bg-dark">${item.phase}</span></td>
      <td>${fmtW(item.total_w)}</td>
      <td>${fmtA(item.total_a)}</td>
      <td style="min-width: 220px;"><textarea class="form-control form-control-sm plan-remarks-input" rows="1" placeholder="Bemerkung" onchange="updateItemRemarks('${item.id}', this.value)">${esc(item.remarks || '')}</textarea></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})">Entfernen</button></td>
    </tr>`).join('');
  }
  renderPhaseBoards();
}

function removeItem(index) {
  plan.splice(index, 1);
  savePlan();
  renderPlan();
}
window.removeItem = removeItem;

document.getElementById('loadForm').addEventListener('submit', event => {
  event.preventDefault();
  const device = findDeviceBySearchValue(document.getElementById('deviceSearch').value)
    || devices.find(d => d.id === document.getElementById('deviceSelect').value);
  if (!device) {
    alert('Bitte ein gültiges Gerät aus der Auswahl wählen.');
    return;
  }
  const quantity = Number(document.getElementById('quantity').value || 1);
  const voltage = Number(document.getElementById('voltage').value || device.voltage_v || 230);
  const totalW = quantity * Number(device.power_w);
  plan.push({
    id: crypto.randomUUID(),
    device_id: device.id,
    name: device.name,
    brand: device.brand || '',
    category: device.category,
    quantity,
    phase: document.getElementById('phase').value,
    remarks: document.getElementById('remarks').value.trim(),
    voltage_v: voltage,
    power_w: Number(device.power_w),
    total_w: totalW,
    total_a: calcAmp(totalW, voltage)
  });
  document.getElementById('remarks').value = '';
  document.getElementById('deviceSearch').value = '';
  document.getElementById('deviceSelect').value = '';
  renderDeviceSelect();
  savePlan();
  renderPlan();
});

document.getElementById('clearPlan').addEventListener('click', () => {
  if (confirm('Stromplan wirklich leeren?')) {
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
      const nextPlan = normalizeImportedPlan(importedPlan);
      if (!confirm(`Importierten Plan mit ${nextPlan.length} Einträgen laden? Der aktuelle Plan wird ersetzt.`)) return;
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
  const blob = new Blob([JSON.stringify({ exported_at: new Date().toISOString(), plan, totals: phaseTotals() }, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'stromplan-export.json';
  a.click();
  URL.revokeObjectURL(url);
});

loadDevices().then(renderPlan);
