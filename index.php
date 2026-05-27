<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stromplaner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="index.php">⚡ Stromplaner</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="index.php">Planung</a></li>
        <li class="nav-item"><a class="nav-link" href="devices.php">Geräte</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4 sticky-lg-top planner-form-card">
        <h1 class="h3 mb-1">Stromplan erstellen</h1>
        <p class="small-muted mb-4">Wähle Gerät, Anzahl und Startphase. Danach kannst du Geräte per Drag & Drop zwischen L1, L2 und L3 verschieben.</p>

        <form id="loadForm" class="row g-3">
          <div class="col-12">
            <label class="form-label">Gerät</label>
            <select class="form-select" id="deviceSelect" required></select>
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
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Zum Plan hinzufügen</button>
          </div>
        </form>

        <hr class="my-4">
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm flex-fill" id="exportJson">JSON exportieren</button>
          <button class="btn btn-outline-danger btn-sm flex-fill" id="clearPlan">Plan leeren</button>
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
        <div class="small-muted">Tabellarische Übersicht. Verschieben erfolgt oben per Drag & Drop.</div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Gerät</th><th>Marke</th><th>Kategorie</th><th>Anzahl</th><th>Phase</th><th>Leistung</th><th>Strom</th><th></th>
          </tr>
        </thead>
        <tbody id="planRows"></tbody>
      </table>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
