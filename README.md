# Stromplaner App

Kompakte Webanwendung zur Planung und Verteilung von Stromlasten und Scheinwerfern auf die Phasen L1–L3.

## Funktionen

* Geräteverwaltung
* Markenverwaltung
* Geräte importieren/exportieren
* JSON-basierte Datenspeicherung
* Bootstrap Geräte-Dropdown mit Live-Filter
* Drag & Drop zwischen L1 / L2 / L3
* Live Berechnung von Watt & Ampere
* Bearbeitung von Anzahl & Bemerkungen direkt im Plan
* Druckfertiger PDF-Export
* Clean URLs ohne `.php`

---

## Installation

Projekt auf einen PHP-/Apache-Server kopieren.

Wichtig:

* `.htaccess` muss erlaubt sein (`mod_rewrite`)
* PHP Schreibrechte für `/data`

---

## Datenstruktur

### Beispielgeräte

`data/devices.json`

Wird nur als Vorlage genutzt.

### Eigene Geräte

`data/devices.user.json`

Wird automatisch erstellt sobald eigene Geräte gespeichert oder importiert werden.

---

## Geräte Import / Export

### Export

Geräteverwaltung → `Exportieren`

### Import

Geräteverwaltung → `Importieren`

Importierte Geräte ergänzen oder aktualisieren bestehende Einträge.

---

## PDF Export

Im Stromplan:
`PDF exportieren`

Anschließend im Browser:
`Als PDF speichern`

---

## URL Struktur

Beispiele:

* `/devices`
* `/planner`

statt:

* `/devices.php`
* `/planner.php`

---

## Technologien

* PHP
* Bootstrap
* JavaScript
* HTML5 Drag & Drop
* JSON Storage

---

## Hinweis

Die App wurde bewusst ohne komplexes Framework aufgebaut, damit Updates und Erweiterungen einfach möglich bleiben.
