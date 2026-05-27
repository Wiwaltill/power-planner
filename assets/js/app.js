const phases = ['L1', 'L2', 'L3'];
let devices = [];
let plan = JSON.parse(localStorage.getItem('stromplan.plan') || '[]');

const fmtW = value => `${Math.round(value).toLocaleString('de-DE')} W`;
const fmtA = value => `${value.toFixed(2).replace('.', ',')} A`;
const calcAmp = (watts, voltage) => watts / voltage;

async function loadDevices() {
  const response = await fetch('devices.php?api=1');
  devices = await response.json();
  const select = document.getElementById('deviceSelect');
  select.innerHTML = devices.map(device => `<option value="${device.id}">${device.brand ? device.brand + ' - ' : ''}${device.name} (${device.power_w} W)</option>`).join('');
  if (!devices.length) select.innerHTML = '<option value="">Bitte zuerst Geräte anlegen</option>';
}

function savePlan() {
  localStorage.setItem('stromplan.plan', JSON.stringify(plan));
}

function phaseTotals() {
  const totals = { L1: { watts: 0, amps: 0 }, L2: { watts: 0, amps: 0 }, L3: { watts: 0, amps: 0 } };
  plan.forEach(item => {
    totals[item.phase].watts += item.total_w;
    totals[item.phase].amps += item.total_a;
  });
  return totals;
}

function renderSummary() {
  const totals = phaseTotals();
  document.getElementById('phaseSummary').innerHTML = phases.map(phase => {
    const amps = totals[phase].amps;
    const status = amps >= 16 ? 'danger' : amps >= 13 ? 'warning' : '';
    const progress = Math.min((amps / 16) * 100, 100);
    return `<div class="col-md-4">
      <div class="card p-3 phase-card ${status}">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">${phase}</h2><span class="badge badge-soft">max. 16 A</span>
        </div>
        <div class="phase-value">${fmtA(amps)}</div>
        <div class="small-muted mb-2">${fmtW(totals[phase].watts)}</div>
        <div class="progress"><div class="progress-bar" style="width:${progress}%"></div></div>
      </div>
    </div>`;
  }).join('');
}

function renderPlan() {
  const body = document.getElementById('planRows');
  if (!plan.length) {
    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Noch keine Geräte im Plan.</td></tr>';
  } else {
    body.innerHTML = plan.map((item, index) => `<tr>
      <td>${item.name}</td>
      <td>${item.brand || '-'}</td>
      <td>${item.category || '-'}</td>
      <td>${item.quantity}</td>
      <td><span class="badge text-bg-dark">${item.phase}</span></td>
      <td>${fmtW(item.total_w)}</td>
      <td>${fmtA(item.total_a)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})">Entfernen</button></td>
    </tr>`).join('');
  }
  renderSummary();
}

function removeItem(index) {
  plan.splice(index, 1);
  savePlan();
  renderPlan();
}
window.removeItem = removeItem;

document.getElementById('loadForm').addEventListener('submit', event => {
  event.preventDefault();
  const device = devices.find(d => d.id === document.getElementById('deviceSelect').value);
  if (!device) return;
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
    voltage_v: voltage,
    total_w: totalW,
    total_a: calcAmp(totalW, voltage)
  });
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
