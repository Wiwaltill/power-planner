<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
require_login();
$pageTitle = 'Einstellungen';
$activePage = 'settings';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Einstellungen</h1>
      <div class="small-muted">Marken und Kategorien für die Geräteverwaltung pflegen.</div>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Marken</h2>
        <form class="input-group mb-3" data-form="brands">
          <input class="form-control" placeholder="Neue Marke" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="brandRows" class="list-group"></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Kategorien</h2>
        <form class="input-group mb-3" data-form="categories">
          <input class="form-control" placeholder="Neue Kategorie" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="categoryRows" class="list-group"></div>
      </div>
    </div>
  </div>
</main>
<script>
const apiSettings = 'api/settings.php';
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
}
function renderSettings(type, rows, el) {
  el.innerHTML = rows.length ? rows.map(row => `<div class="list-group-item d-flex gap-2 align-items-center"><input class="form-control form-control-sm" value="${escSettings(row.name)}" data-id="${row.id}" data-type="${type}"><button class="btn btn-sm btn-outline-secondary" onclick="saveSetting('${type}', ${row.id}, this)">Speichern</button><button class="btn btn-sm btn-outline-danger" onclick="deleteSetting('${type}', ${row.id})">Löschen</button></div>`).join('') : '<div class="text-muted small">Noch keine Einträge.</div>';
}
async function saveSetting(type, id, btn) {
  const input = btn.parentElement.querySelector('input');
  await settingsJson(`${apiSettings}?type=${type}&id=${id}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:input.value})});
  await loadSettings();
}
async function deleteSetting(type, id) {
  if (!confirm('Eintrag löschen? Bereits gespeicherte Geräte behalten ihren Textwert.')) return;
  await settingsJson(`${apiSettings}?type=${type}&id=${id}`, {method:'DELETE'});
  await loadSettings();
}
document.querySelectorAll('[data-form]').forEach(form => form.addEventListener('submit', async e => {
  e.preventDefault();
  const type = form.dataset.form;
  const input = form.querySelector('input');
  try {
    await settingsJson(`${apiSettings}?type=${type}`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name:input.value})});
    input.value = '';
    await loadSettings();
  } catch(err) { alert(err.message); }
}));
window.saveSetting = saveSetting;
window.deleteSetting = deleteSetting;
loadSettings().catch(err => alert(err.message));
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
