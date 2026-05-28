<?php
$pageTitle = 'Stromplaner';
$activePage = 'planner';
$pageScript = 'assets/js/app.js';
require __DIR__ . '/inc/header.php';
?>

<main class="container py-4 flex-grow-1">
  <div class="card p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Projekt</label>
        <input class="form-control" id="projectName" placeholder="z. B. Festival Hauptbühne">
      </div>
      <div class="col-md-2">
        <label class="form-label">Kunde</label>
        <input class="form-control" id="projectClient" placeholder="Kunde">
      </div>
      <div class="col-md-2">
        <label class="form-label">Techniker</label>
        <input class="form-control" id="projectTechnician" placeholder="Name">
      </div>
      <div class="col-md-2">
        <label class="form-label">Logo URL</label>
        <input class="form-control" id="projectLogo" placeholder="optional">
      </div>
      <div class="col-md-3">
        <label class="form-label">Gespeicherte Projekte</label>
        <div class="input-group">
          <select class="form-select" id="projectSelect"></select>
          <button class="btn btn-outline-primary" id="saveProject" type="button">Speichern</button>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4 sticky-lg-top planner-form-card">
        <h1 class="h3 mb-1">Stromplan erstellen</h1>
        <p class="small-muted mb-4">Wähle Gerät, Anzahl und Startphase. Danach kannst du Geräte per Drag & Drop zwischen L1, L2 und L3 verschieben.</p>

        <form id="loadForm" class="row g-3">
          <div class="col-12">
            <label class="form-label">Gerät</label>
            <div class="dropdown w-100 device-dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" id="deviceDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span id="deviceDropdownLabel">Gerät suchen oder auswählen...</span>
              </button>
              <div class="dropdown-menu w-100 p-2 device-dropdown-menu" aria-labelledby="deviceDropdownButton">
                <input type="search" class="form-control form-control-sm mb-2" id="deviceSearch" placeholder="Gerät filtern..." autocomplete="off">
                <div id="deviceOptions" class="device-options list-group list-group-flush"></div>
              </div>
            </div>
            <input type="hidden" id="deviceSelect" required>
            <div class="form-text" id="deviceSearchInfo">Gerät per Bootstrap-Dropdown öffnen und direkt filtern.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Kategorie-Filter</label>
            <select class="form-select" id="categoryFilter"><option value="">Alle Kategorien</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Anzahl</label>
            <input type="number" class="form-control" id="quantity" min="1" value="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Phase</label>
            <select class="form-select" id="phase" required>
              <option value="L1">L1</option>
              <option value="L2">L2</option>
              <option value="L3">L3</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Spannung</label>
            <input type="number" class="form-control" id="voltage" value="230" min="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">DMX-Adresse</label>
            <input type="number" class="form-control" id="dmxAddress" min="1" max="512" placeholder="z. B. 001">
          </div>
          <div class="col-md-4">
            <label class="form-label">Universum</label>
            <input type="number" class="form-control" id="dmxUniverse" min="1" placeholder="z. B. 1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Kanalmodus</label>
            <input class="form-control" id="channelMode" placeholder="z. B. 14ch">
          </div>
          <div class="col-md-6">
            <label class="form-label">Stromkreis</label>
            <input class="form-control" id="circuit" placeholder="z. B. CEE16 Bühne L">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sicherung</label>
            <input class="form-control" id="fuse" placeholder="z. B. B16 / C16 / C32">
          </div>
          <div class="col-12">
            <label class="form-label">Bemerkungen</label>
            <textarea class="form-control" id="remarks" rows="2" placeholder="z. B. Position, Sicherung, Besonderheiten"></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Zum Plan hinzufügen</button>
          </div>
        </form>

        <hr class="my-4">
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-secondary btn-sm flex-fill" id="exportJson" type="button">JSON exportieren</button>
          <button class="btn btn-outline-success btn-sm flex-fill" id="importJson" type="button">JSON importieren</button>
          <button class="btn btn-outline-primary btn-sm flex-fill" id="exportPdf" type="button">PDF exportieren</button>
          <button class="btn btn-outline-secondary btn-sm flex-fill" id="exportCsv" type="button">CSV exportieren</button>
          <button class="btn btn-outline-warning btn-sm flex-fill" id="autoDistribute" type="button">Automatisch verteilen</button>
          <button class="btn btn-outline-secondary btn-sm flex-fill" id="undoPlan" type="button">Undo</button>
          <button class="btn btn-outline-secondary btn-sm flex-fill" id="redoPlan" type="button">Redo</button>
          <button class="btn btn-outline-dark btn-sm flex-fill" id="toggleDarkMode" type="button">Dark Mode</button>
          <button class="btn btn-outline-dark btn-sm flex-fill" id="toggleFullscreen" type="button">Vollbild</button>
          <button class="btn btn-outline-danger btn-sm flex-fill" id="clearPlan" type="button">Plan leeren</button>
          <input type="file" id="importJsonFile" accept="application/json,.json" class="d-none">
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
        <div>
          <h2 class="h4 mb-0">Phasenplaner</h2>
          <div class="small-muted">Ziehe angelegte Geräte in die gewünschte Phase.</div>
        </div>
        <span class="badge text-bg-light border">Richtwert je Phase: 16 A</span>
      </div>
      <div class="row g-3" id="phaseBoards"></div>
    </div>
  </div>

  <div class="card p-4 mt-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
      <div>
        <h2 class="h4 mb-0">Aktueller Stromplan</h2>
        <div class="small-muted">Tabellarische Übersicht. Anzahl und Bemerkungen kannst du direkt bearbeiten; Verschieben erfolgt oben per Drag & Drop.</div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Gerät</th><th>Marke</th><th>Kategorie</th><th>Anzahl</th><th>Phase</th><th>DMX</th><th>Stromkreis</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th><th></th>
          </tr>
        </thead>
        <tbody id="planRows"></tbody>
      </table>
    </div>
  </div>


  <section id="printArea" class="print-area" aria-hidden="true">
    <div class="print-header">
      <div>
        <h1>Stromplan Übersicht</h1>
        <p id="printDate"></p>
      </div>
      <div class="print-meta">Richtwert je Phase: 16 A</div>
    </div>

    <h2>Phasenübersicht</h2>
    <div id="printSummary" class="print-summary"></div>

    <h2>Geräte nach Phase</h2>
    <div id="printPhaseTables"></div>

    <h2>Gesamtliste</h2>
    <table class="print-table">
      <thead>
        <tr><th>Gerät</th><th>Marke</th><th>Kategorie</th><th>Anzahl</th><th>Phase</th><th>DMX</th><th>Stromkreis</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th></tr>
      </thead>
      <tbody id="printRows"></tbody>
    </table>

    <p class="print-note">Hinweis: Die Berechnung basiert auf den im Plan eingetragenen Leistungs- und Spannungswerten und ersetzt keine elektrotechnische Prüfung.</p>
  </section>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
