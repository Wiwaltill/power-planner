<?php
$sampleFile = __DIR__ . '/data/devices.json';
$dataFile = __DIR__ . '/data/devices.user.json';

function ensureDeviceDataFile($dataFile, $sampleFile) {
    if (!file_exists(dirname($dataFile))) {
        mkdir(dirname($dataFile), 0775, true);
    }
    if (!file_exists($dataFile)) {
        if (file_exists($sampleFile)) {
            copy($sampleFile, $dataFile);
        } else {
            file_put_contents($dataFile, '[]');
        }
    }
}

function readDevices($file) {
    if (!file_exists($file)) { return []; }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}
function writeDevices($file, $devices) {
    file_put_contents($file, json_encode(array_values($devices), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    ensureDeviceDataFile($dataFile, $sampleFile);
    $devices = readDevices($dataFile);
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($method === 'GET' && (($_GET['export'] ?? '') === '1')) {
        $filename = 'stromplaner-geraete-' . date('Y-m-d') . '.json';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && (($_GET['import'] ?? '') === '1')) {
        $importDevices = [];
        if (isset($input['devices']) && is_array($input['devices'])) {
            $importDevices = $input['devices'];
        } elseif (is_array($input)) {
            $importDevices = $input;
        }

        if (!is_array($importDevices) || count($importDevices) === 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Die Import-Datei enthält keine Geräte.']);
            exit;
        }

        $normalized = [];
        foreach ($importDevices as $item) {
            if (!is_array($item)) { continue; }
            $name = trim($item['name'] ?? '');
            $power = (float)($item['power_w'] ?? 0);
            if ($name === '' || $power <= 0) { continue; }
            $id = trim($item['id'] ?? '');
            if ($id === '') {
                $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            }
            $normalized[] = [
                'id' => trim($id, '-'),
                'name' => $name,
                'brand' => trim($item['brand'] ?? ''),
                'category' => trim($item['category'] ?? ''),
                'power_w' => $power,
                'voltage_v' => (float)($item['voltage_v'] ?? 230),
                'connector' => trim($item['connector'] ?? ''),
                'notes' => trim($item['notes'] ?? '')
            ];
        }

        if (count($normalized) === 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Es wurden keine gültigen Geräte gefunden. Name und Leistung sind erforderlich.']);
            exit;
        }

        $byId = [];
        foreach ($devices as $device) {
            if (isset($device['id'])) { $byId[$device['id']] = $device; }
        }
        foreach ($normalized as $device) {
            $byId[$device['id']] = $device;
        }
        $devices = array_values($byId);
        usort($devices, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        writeDevices($dataFile, $devices);
        echo json_encode(['ok' => true, 'imported' => count($normalized), 'total' => count($devices)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET') {
        echo json_encode($devices, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($method === 'POST') {
        $id = $input['id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $input['name'] ?? uniqid('device')));
        $device = [
            'id' => trim($id, '-'),
            'name' => trim($input['name'] ?? ''),
            'brand' => trim($input['brand'] ?? ''),
            'category' => trim($input['category'] ?? ''),
            'power_w' => (float)($input['power_w'] ?? 0),
            'voltage_v' => (float)($input['voltage_v'] ?? 230),
            'connector' => trim($input['connector'] ?? ''),
            'notes' => trim($input['notes'] ?? '')
        ];
        if ($device['name'] === '' || $device['power_w'] <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Name und Leistung sind erforderlich.']);
            exit;
        }
        $devices = array_values(array_filter($devices, fn($d) => $d['id'] !== $device['id']));
        $devices[] = $device;
        writeDevices($dataFile, $devices);
        echo json_encode($device, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        $devices = array_values(array_filter($devices, fn($d) => $d['id'] !== $id));
        writeDevices($dataFile, $devices);
        echo json_encode(['ok' => true]);
        exit;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Geräte verwalten</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="./">⚡ Stromplaner</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="./">Planung</a></li>
        <li class="nav-item"><a class="nav-link active" href="devices">Geräte</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card p-4">
        <h1 class="h3 mb-1">Gerät vordefinieren</h1>
        <p class="small-muted mb-4">Speichere Stammdaten, die später im Stromplan auswählbar sind.</p>
        <form id="deviceForm" class="row g-3">
          <input type="hidden" id="deviceId">
          <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" id="name" required></div>
          <div class="col-md-6"><label class="form-label">Marke</label><input class="form-control" id="brand" placeholder="z.B. Cameo, Eurolite..."></div>
          <div class="col-md-6"><label class="form-label">Kategorie</label><input class="form-control" id="category" placeholder="z.B. Scheinwerfer"></div>
          <div class="col-md-6"><label class="form-label">Leistung in W</label><input type="number" class="form-control" id="power" min="1" required></div>
          <div class="col-md-6"><label class="form-label">Spannung in V</label><input type="number" class="form-control" id="voltage" min="1" value="230"></div>
          <div class="col-md-6"><label class="form-label">Anschluss</label><input class="form-control" id="connector" placeholder="Schuko, PowerCON..."></div>
          <div class="col-12"><label class="form-label">Notizen</label><textarea class="form-control" id="notes" rows="3"></textarea></div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Speichern</button>
            <button class="btn btn-outline-secondary" type="button" id="resetForm">Neu</button>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card p-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
          <h2 class="h4 mb-0">Geräteliste</h2>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary btn-sm" id="exportDevices" type="button">Geräte exportieren</button>
            <button class="btn btn-outline-success btn-sm" id="importDevices" type="button">Geräte importieren</button>
            <input type="file" id="importDevicesFile" accept="application/json,.json" class="d-none">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>Name</th><th>Marke</th><th>W</th><th>A bei 230 V</th><th>Anschluss</th><th></th></tr></thead>
            <tbody id="deviceRows"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/devices.js"></script>
</body>
</html>
