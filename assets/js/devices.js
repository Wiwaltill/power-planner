let devices = [];
const apiUrl = 'devices?api=1';
const amp = device => Number(device.power_w) / Number(device.voltage_v || 230);
const slug = text => text.toLowerCase().trim().replace(/[^a-z0-9äöüß]+/gi, '-').replace(/^-|-$/g, '');

async function loadDevices() {
  const response = await fetch(apiUrl);
  devices = await response.json();
  renderDevices();
}

function renderDevices() {
  const rows = document.getElementById('deviceRows');
  if (!devices.length) {
    rows.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Noch keine Geräte angelegt.</td></tr>';
    return;
  }
  rows.innerHTML = devices.map(device => `<tr>
    <td><strong>${device.name}</strong><div class="small-muted">${device.category || '-'} ${device.notes ? ' · ' + device.notes : ''}</div></td>
    <td>${device.brand || '-'}</td>
    <td>${Number(device.power_w).toLocaleString('de-DE')} W</td>
    <td>${amp(device).toFixed(2).replace('.', ',')} A</td>
    <td>${device.connector || '-'}</td>
    <td class="text-end">
      <button class="btn btn-sm btn-outline-secondary me-1" onclick="editDevice('${device.id}')">Bearbeiten</button>
      <button class="btn btn-sm btn-outline-danger" onclick="deleteDevice('${device.id}')">Löschen</button>
    </td>
  </tr>`).join('');
}

function resetForm() {
  document.getElementById('deviceForm').reset();
  document.getElementById('deviceId').value = '';
  document.getElementById('voltage').value = 230;
}

function editDevice(id) {
  const d = devices.find(device => device.id === id);
  if (!d) return;
  document.getElementById('deviceId').value = d.id;
  document.getElementById('name').value = d.name;
  document.getElementById('brand').value = d.brand || '';
  document.getElementById('category').value = d.category || '';
  document.getElementById('power').value = d.power_w;
  document.getElementById('voltage').value = d.voltage_v || 230;
  document.getElementById('connector').value = d.connector || '';
  document.getElementById('notes').value = d.notes || '';
}
window.editDevice = editDevice;

async function deleteDevice(id) {
  if (!confirm('Gerät wirklich löschen?')) return;
  await fetch(`${apiUrl}&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
  await loadDevices();
}
window.deleteDevice = deleteDevice;

document.getElementById('deviceForm').addEventListener('submit', async event => {
  event.preventDefault();
  const id = document.getElementById('deviceId').value || slug(document.getElementById('name').value);
  const payload = {
    id,
    name: document.getElementById('name').value,
    brand: document.getElementById('brand').value,
    category: document.getElementById('category').value,
    power_w: Number(document.getElementById('power').value),
    voltage_v: Number(document.getElementById('voltage').value || 230),
    connector: document.getElementById('connector').value,
    notes: document.getElementById('notes').value
  };
  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  if (!response.ok) {
    const error = await response.json();
    alert(error.error || 'Speichern fehlgeschlagen.');
    return;
  }
  resetForm();
  await loadDevices();
});

document.getElementById('resetForm').addEventListener('click', resetForm);

const exportDevicesButton = document.getElementById('exportDevices');
if (exportDevicesButton) {
  exportDevicesButton.addEventListener('click', () => {
    window.location.href = `${apiUrl}&export=1`;
  });
}

const importDevicesButton = document.getElementById('importDevices');
const importDevicesFile = document.getElementById('importDevicesFile');
if (importDevicesButton && importDevicesFile) {
  importDevicesButton.addEventListener('click', () => importDevicesFile.click());
  importDevicesFile.addEventListener('change', async event => {
    const file = event.target.files[0];
    if (!file) return;
    try {
      const text = await file.text();
      const json = JSON.parse(text);
      const response = await fetch(`${apiUrl}&import=1`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(json)
      });
      const result = await response.json();
      if (!response.ok) {
        alert(result.error || 'Geräte-Import fehlgeschlagen.');
        return;
      }
      await loadDevices();
      alert(`${result.imported} Gerät(e) importiert. Insgesamt sind jetzt ${result.total} Gerät(e) gespeichert.`);
    } catch (error) {
      alert('Die ausgewählte Datei konnte nicht als Geräte-JSON importiert werden.');
    } finally {
      importDevicesFile.value = '';
    }
  });
}

loadDevices();
