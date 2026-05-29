<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
$projectId = (int)($_GET['id'] ?? 0);
$project = user_project($projectId, (int)$user['id']);
if (!$project) { http_response_code(404); echo 'Projekt nicht gefunden.'; exit; }

$stmt = db()->prepare('SELECT pi.*, COALESCE(c.name, "Ohne Stromkreis") AS circuit_name FROM plan_items pi LEFT JOIN circuits c ON c.id = pi.circuit_id WHERE pi.project_id = ? ORDER BY c.name ASC, FIELD(pi.phase, "L1", "L2", "L3"), pi.name ASC');
$stmt->execute([$projectId]);
$items = $stmt->fetchAll();

function xlsx_watts(array $item): float {
    return (float)($item['power_w'] ?? 0) * max(1, (float)($item['quantity'] ?? 1));
}
function xlsx_amps(array $item): float {
    $voltage = max(1, (float)($item['voltage_v'] ?? 230));
    return xlsx_watts($item) / $voltage;
}
function xlsx_col(int $index): string {
    $name = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - $mod, 26);
    }
    return $name;
}
function xlsx_escape(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
function xlsx_cell(int $row, int $col, $value, string $type = 's', int $style = 0): string {
    $ref = xlsx_col($col) . $row;
    $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';
    if ($type === 'n') {
        $num = is_numeric($value) ? (float)$value : 0;
        $out = rtrim(rtrim(number_format($num, 4, '.', ''), '0'), '.');
        if ($out === '' || $out === '-') { $out = '0'; }
        return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $out . '</v></c>';
    }
    return '<c r="' . $ref . '" t="inlineStr"' . $styleAttr . '><is><t>' . xlsx_escape((string)$value) . '</t></is></c>';
}
function xlsx_row(int $row, array $cells): string {
    $xml = '<row r="' . $row . '">';
    $col = 1;
    foreach ($cells as $cell) {
        if (is_array($cell)) {
            $xml .= xlsx_cell($row, $col, $cell[0] ?? '', $cell[1] ?? 's', $cell[2] ?? 0);
        } else {
            $xml .= xlsx_cell($row, $col, $cell);
        }
        $col++;
    }
    return $xml . '</row>';
}

$circuitTotals = [];
foreach ($items as $item) {
    $circuit = trim((string)($item['circuit_name'] ?? '')) ?: 'Ohne Stromkreis';
    if (!isset($circuitTotals[$circuit])) {
        $circuitTotals[$circuit] = [
            'L1' => ['w' => 0.0, 'a' => 0.0],
            'L2' => ['w' => 0.0, 'a' => 0.0],
            'L3' => ['w' => 0.0, 'a' => 0.0],
        ];
    }
    $phase = in_array(($item['phase'] ?? 'L1'), ['L1','L2','L3'], true) ? $item['phase'] : 'L1';
    $circuitTotals[$circuit][$phase]['w'] += xlsx_watts($item);
    $circuitTotals[$circuit][$phase]['a'] += xlsx_amps($item);
}
if (!$circuitTotals) {
    $circuitTotals['Keine Einträge'] = [
        'L1' => ['w' => 0.0, 'a' => 0.0],
        'L2' => ['w' => 0.0, 'a' => 0.0],
        'L3' => ['w' => 0.0, 'a' => 0.0],
    ];
}

$row = 1;
$sheetRows = [];
$sheetRows[] = xlsx_row($row++, [['Stromplan Export', 's', 1]]);
$sheetRows[] = xlsx_row($row++, ['Projekt', (string)$project['name']]);
$sheetRows[] = xlsx_row($row++, ['Kunde', (string)($project['client'] ?? '')]);
$sheetRows[] = xlsx_row($row++, ['Techniker', (string)($project['technician'] ?? '')]);
$sheetRows[] = xlsx_row($row++, ['Export', date('d.m.Y H:i')]);
$row++;

$sheetRows[] = xlsx_row($row++, [['Phasenübersicht je Stromkreis', 's', 1]]);
$sheetRows[] = xlsx_row($row++, [
    ['Stromkreis', 's', 2], ['L1 Watt', 's', 2], ['L1 Ampere', 's', 2], ['L2 Watt', 's', 2], ['L2 Ampere', 's', 2], ['L3 Watt', 's', 2], ['L3 Ampere', 's', 2]
]);
foreach ($circuitTotals as $circuit => $phases) {
    $sheetRows[] = xlsx_row($row++, [
        $circuit,
        [$phases['L1']['w'], 'n'], [$phases['L1']['a'], 'n'],
        [$phases['L2']['w'], 'n'], [$phases['L2']['a'], 'n'],
        [$phases['L3']['w'], 'n'], [$phases['L3']['a'], 'n'],
    ]);
}
$row++;

$sheetRows[] = xlsx_row($row++, [['Geräte', 's', 1]]);
$sheetRows[] = xlsx_row($row++, [
    ['Gerät', 's', 2], ['Marke', 's', 2], ['Kategorie', 's', 2], ['Anzahl', 's', 2], ['Stromkreis', 's', 2], ['Phase', 's', 2], ['Leistung gesamt W', 's', 2], ['Spannung V', 's', 2], ['Strom A', 's', 2], ['Bemerkung', 's', 2]
]);
if (!$items) {
    $sheetRows[] = xlsx_row($row++, ['Keine Geräte im Plan.']);
} else {
    foreach ($items as $item) {
        $sheetRows[] = xlsx_row($row++, [
            (string)$item['name'],
            (string)($item['brand'] ?? ''),
            (string)($item['category'] ?? ''),
            [(int)$item['quantity'], 'n'],
            (string)($item['circuit_name'] ?? ''),
            (string)$item['phase'],
            [xlsx_watts($item), 'n'],
            [(float)($item['voltage_v'] ?? 230), 'n'],
            [xlsx_amps($item), 'n'],
            (string)($item['remarks'] ?? ''),
        ]);
    }
}

$worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<cols><col min="1" max="1" width="28" customWidth="1"/><col min="2" max="10" width="18" customWidth="1"/></cols>'
    . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Stromplan" sheetId="1" r:id="rId1"/></sheets></workbook>';
$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>';

$tmp = tempnam(sys_get_temp_dir(), 'stromplan-xlsx-');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Excel-Datei konnte nicht erstellt werden.';
    exit;
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
$zip->addFromString('xl/styles.xml', $styles);
$zip->close();

$filenameBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $project['name'] ?: 'stromplan');
$filenameBase = trim($filenameBase, '-') ?: 'stromplan';
$filename = $filenameBase . '-stromplan.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp);
unlink($tmp);
exit;
