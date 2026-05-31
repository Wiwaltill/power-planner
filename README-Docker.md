# Power Planner mit Docker installieren

Diese Docker-Installation ist zusätzlich zur normalen Webspace-Installation gedacht. Der vorhandene Browser-Installer bleibt erhalten.

## Voraussetzungen

- Docker
- Docker Compose

## Installation

```bash
cp .env.example .env
docker compose up -d --build
```

Danach im Browser öffnen:

```text
http://localhost:8080/install
```

Im Installer diese Daten verwenden:

```text
Datenbank-Host: db
Datenbank-Name: powerplanner
Datenbank-Benutzer: powerplanner
Datenbank-Passwort: powerplanner
```

Wenn du die Werte in `.env` geändert hast, verwende im Installer entsprechend deine eigenen Werte.

## Anwendung öffnen

```text
http://localhost:8080
```

## Optional: phpMyAdmin starten

```bash
docker compose --profile tools up -d
```

Danach:

```text
http://localhost:8081
```

## Daten bleiben erhalten

Die Datenbank liegt im Docker-Volume `powerplanner_db`.

Zusätzlich werden diese Ordner lokal eingebunden:

```text
config/
storage/
uploads/
```

Dadurch bleiben Konfiguration, Backups, Exporte und Uploads auch bei einem Container-Neustart erhalten.

## Update

```bash
git pull
docker compose up -d --build
```

Danach wie gewohnt den vorhandenen Updater bzw. die interne Migrationslogik verwenden, falls die neue Version Datenbankänderungen enthält.

## Stoppen

```bash
docker compose down
```

## Komplett zurücksetzen

Achtung: Löscht die Datenbankdaten.

```bash
docker compose down -v
rm -f config/config.php
```
