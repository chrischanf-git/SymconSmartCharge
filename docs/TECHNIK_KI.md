# TECHNISCHE UEBERGABE - SmartCharge v1.2.0 (23.11.2026)

## Neue Architektur

- **Controller-Modul**: `SmartCharge/module.php` (`LunaSmartCharge`, GUID `{0F06F87B-A351-4B29-98A1-6DADE5C75CE0}`, Prefix `LUNAS`). Bestehende Instanz ID **14264**. `GetVisualizationTile()` liefert **nur die kompakte Smartphone-Start/Details/Ladungen-App** aus `SmartCharge/module.html`; alle alten IDs, JSON-Attribute, 60-s-Controller-Tick, `RequestAction`-Steuerung, Live-Freigaben, Sessions, Forecast und Lernmodell bleiben erhalten.
- **Dashboard-Modul**: neu `Dashboard/` mit `module.php`, `module.json`, `form.json`, `module.html` (`LunaSmartChargeDashboard`, Prefix `LUNAD`). Eigene Instanz, Property `ControllerInstanceID` = 14264. **Read-only**. Einmal pro Minute ruft es `LUNAS_GetDashboardData(14264)` auf, eine neue **oeffentliche** Methode im Controller, die den bisherigen `buildPayload()` als JSON-String zurueckgibt. Kein eigener Sensor-Cloudzugriff, keine `RequestAction` an die Wallbox; Tick sendet JSON-String per `UpdateVisualizationValue()`. Muss als separate Kachel in die Visualisierung aufgenommen werden.
- **Gemeinsames Designmodul**: `libs/UiTheme.php` (CSS-Sanitization, Farbkontrast, maximal 2-MB Bildmedium, fallback auf mitgeliefertes WebP, kleine SVG-Vorschau in den Konfigurationsformularen). `Ui*`-Properties getrennt je Instanz. `SelectColor` schreibt CSS-Hexstring, `SelectMedia` Bildmedium-ID. DesignCSS wird **einmalig** in der Kachel initial injiziert; nach Konfigurationsaenderung Kachel neu laden. **Keine dynamische JS-/CSS-Injektion aus unkontrollierten Werten**.
- **Mobile Navigation**: drei interne Panels `#screen-start`, `#screen-details`, `#screen-history` mit `.tab-link`. Start nur Fahrzeuguebersicht (SoC/km/Leistung/Energie), Smart/Jetzt, Abfahrt, `#home-manual`. Alle weiteren Infos inklusive Pause, Energie/Forecast und Korrekturen auf Deep-Level; modale Fahrzeugdatenerfassung hat weiterhin Ueberspringen, spaeteres Nachtragen, manuelle Ladesitzungen.
- **Vorschauen und Tests**: `docs/previews/SmartCharge_Mobil_Vorschau.html`, `SmartCharge_Desktop_Vorschau.html`, `SmartCharge_Designwerkstatt.html` (autark mit eingebetteten beiden Screens). `tests/test_ui.py` Playwright (inkl. Deep-Link-Navigation, manuelle Eingaben, Dashboard read-only), `tests/mock_module.php` (beide Instanztypen und CSS-Konfig), `tests/run_tests.php` Modell / Controller.

## Inbetriebnahme-Kontext

Aus Diagnose 22.11.2026: PV 26684, Netz 37583, Speicher-SoC 47921, Speicher-Watt 40466, Hausverbrauch 25505; Temperatur/Heizung noch nicht verknuepft; Wetter JSON noch offen. Controller 14264, Luna-Kategorie 25848, API-Skript 12864. Speicher-Watt wird seit 22.11.2026 laut Nutzer alle 120s aktualisiert, Datenalter-Maximum 210s. **Simulationsbetrieb nicht deaktivieren**, bevor Stromsteuerbefehle und Hardwaregrenzen getestet sind.

## Update-Regeln

- `module.json` GUID des Controllers **nicht** aendern (Instanz und persistente Fahrtdaten behalten!). `Dashboard` ist ein neuer eigenstaendiger Modultyp mit eigener GUID.
- Weitere Ordner in der Repository-Wurzel **nur mit `module.json`**, ausser den Symcon-Ausnahmen `docs`, `libs`, `imgs`, `tests`, `actions`; keinen Top-Level-Ordner `previews` anlegen.
- HTML-SDK ab 7.1: `SetVisualizationType(1)`, `UpdateVisualizationValue(JSON_STRING)`, nicht PHP-Array (alte Symcon-Versionen werfen Datentypfehler). `GetDashboardData()` darf niemals schalten.
- Kein Token/WebHook im HTML; UI uses `requestAction('UI',...)`; Dashboard kann nicht schreiben.

---

# TECHNISCHE PROJEKTÜBERGABE – Charge Amps Luna / SmartCharge

**Stand:** 22.11.2026 · **Codepaket:** 1.1 · **Qualität:** Quellcode + automatisierte Rechen-, Modul-Mock- und Browser-Vorschautests erfolgreich; **kein** Symcon-/Charge-Amps-Live-End-to-End-Test. Standard **Dry-Run**.

## Projekt / unveränderliche Bestandsinstallation

Anwendungsfall: Einfamilienhaus mit PV, Hausspeicher, Erdwärmepumpe (ungefähr 2 kW), Charge Amps Luna Wallbox, Renault Twingo E-Tech elektrisch 2026. Vorhanden sind die Symcon-Kategorie `25848`, das produktive API-/Polling-/Aktionsskript `12864`, `CA_LUNA_*`-Variablen und die bisherige HTMLBox für Desktop. Die alte Hauptlogik bleibt unverändert. `CA_LUNA_ENABLE` und `CA_LUNA_MAX_CURRENT` werden via `RequestAction` geschaltet. Das alte Hauptskript nutzt eine Charge-Amps-Cloud-API (`eapi.charge.space`) mit API-Key und Login und im Moment ein nicht vollständig nachgewiesenes PUT-Settings-Verfahren für Max Current. **Nicht** nochmals Credentials/alten Hook-Token in ein neues Frontend schreiben; HTML-SDK-Modul nutzt keinen alten WebHook.

Die **tatsächlichen Sensor-Objekt-IDs** der Hausinstallation und das reale Prognoseformat sind weiterhin unbekannt. Für eine spätere Live-Anbindung muss der Benutzer diese liefern. Die Symcon-Version muss mindestens 7.1 sein.

## Dateien und Module (Trennung nach Zuständigkeit)

- `SmartCharge/module.json`, `SmartCharge/module.php`: `LunaSmartCharge extends IPSModule`, Prefix `LUNAS`, Symcon-Lebenszyklus, Felder, Takt, Dauerzustand, HTML-SDK-RequestAction, Stellbefehl-Adapter. 60-s-Timer; 0 Cloud-GETs pro Tick.
- `SmartCharge/form.json`: Auswahl der tatsächlichen Sensor-IDs, Vorzeichen, Phasen, Reserve-/Anschlussparameter, Live-Gating.
- `SmartCharge/module.html`: responsive Kachel, CSS + minimales JS ohne externe Bibliotheken; `requestAction('UI', JSON.stringify(...))`, `handleMessage(next)`. Symcon-HTML-SDK (ab 7.1); im Paket als eine Kachel für Smartphone/Tablet/Desktop. Farbe Grün auf Anthrazit, User-Foto im PNG-Upload als in `imgs/renault-twingo-2026.webp` konvertierter optimierter Zuschnitt.
- `libs/VehicleModel.php`: 50 Sitzungen im JSON-Attribut, 12 im Payload, 8 in der UI; Plug-/Ende-Erkennung, kWh-Deltas, Start-/Jetzt-/End-SoC-Referenz, SoC und km, Korrekturen + historische neue Sitzungen, datenqualitätssicheres Lernen.
- `libs/ChargePlanner.php`: Zeitreihen-Prognose normalisieren, verfügbare AC-Energie bis Deadline schätzen. Kein Netz.
- `libs/ChargeRegulator.php`: PV, Netz, Speicher, SoC-Ziel, Fahrt, Deadline, Notladung, Strom- und Netzgrenzen. Keine Nebenwirkungen.
- `tests/run_tests.php`: reine PHP-Berechnungen, manuelle historische Ladung, Korrektur, Forecast, Sperrungen.
- `tests/mock_module.php`: PHP-Stubs und Modulinstanz, kein Symcon-/Hardwaretest.
- `tests/test_ui.py`: Chromium Playwright, Responsive, Buttons, Skip, Edit, Historie, manuelle Ladung, Screenshot.
- `tests/make_previews.py`: eigenständige Demo-HTMLs mit Beispielwerten, keine Kommunikation mit Wallbox.
- `README_Einrichtung.md`: ausführliche Endbenutzeranleitung.

## Datenfluss, Leistungsregelung

Altes Polling `12864` aktualisiert `CA_LUNA_CONNECTOR_STATUS`, `CA_LUNA_IS_CONNECTED`, `CA_LUNA_POWER_KW`, `CA_LUNA_TOTAL_KWH`, `CA_LUNA_RAW`, `CA_LUNA_SESSION_ID` usw. SmartCharge liest **nur** diese Werte und die Sensor-IDs in Symcon; jede Minute ggf. Variablenaktualisierung (UI push). Realer Befehl (gesperrt bis alle Sicherheitschecks erfüllt) geht per `RequestAction(CA_LUNA_ENABLE / CA_LUNA_MAX_CURRENT)` an das bestehende Skript. Stelle die vorherige periodische Abfrage weiterhin separat ein.

Konfiguration: `PvPowerID` (W, Erzeugung +), `GridPowerID` (W, Netzbezug + / Einspeisung −), `BatterySocID` (%), `BatteryPowerID` (W, Entladen + / Laden −), optional `HousePowerID`, `HeatPumpPowerID` (W), `ForecastJsonID` (String), jeweilige Vorzeichen invertierbar.

Stromvorschlag bei Smart:

1. Alle kritischen Messwerte inklusive Charge-Amps-Status müssen jünger als `MaxDataAgeS` (Standard 210 s) sein, sonst Soll = 0 A. Bei nicht verbundenem Fahrzeug = 0 A.
2. PV-Überschuss aus bereits laufender Wallboxleistung minus Netzbezug minus positive Speicherentladung minus Sicherheitspuffer. Auf aktuelle **PV-Erzeugung** begrenzen; Netzeinspeisung = negatives GridPower. Weder Haus- noch WP-Leistung nochmal abziehen: bereits in Netzleistung enthalten.
3. Hausspeicher unter `BatteryReservePct` → Smartpause. Bei neuem PV-Start zwei konsekutive gültige positive 60-s-Messungen, EWMA-Glättung (0,35) nach oben; nach unten rasche Reduktion.
4. Wallbox-Strom A = floor(Überschuss W/(230 × feste Phasenzahl)); unter 6 A → Pause. Obergrenze = min(`MaxCurrentA`, `VehicleAcMaxW/(230×Phases)`). Standard im Code fest 3 Phasen, 16 A, 6,6 kW; echte Ausstattung vor Freigabe prüfen.
5. Ziel-SoC = max(gewünschter Mindest-SoC, Fahrt-km×gelerntem Verbrauch/Kapazität + Restreserve), auf 100 % begrenzt. Strecken > Reichweite melden.
6. PV-Zeitprognose bis Abfahrt; konservativer 25-%-Abschlag. Deadline-Puffer: rechnerisch nötige volle Ladedauer + 0,3–1,3 h, umso früher Netzergänzung je weniger PV prognostiziert. **Heuristik, keine Fahrbereitschaftsgarantie.** Smart weicht im dringenden Fall auf sofortiges Laden aus; „Jetzt“ lädt stets mit konfigurierter Obergrenze, aber Strombudget und Sensor-Sicherungen bleiben aktiv.
7. Optionales Software-Netzbezugsbudget bei `MaxGridImportW>0`; Cloud ist *kein* schnelles Netzanschluss-/Überlastschutzsystem.
8. 120 s Default-Befehlsintervall, Stop nach fehlenden Voraussetzungen schneller (20 s Mindestabstand), 180 s Mindest-PV-Pause nach Stop.

## Live-Gating / Inbetriebnahme

**Standard:** `LiveControl=false`, `MaxGridImportW=0`. `mayLive()` wird nur wahr, wenn `LiveControl`, `CurrentControlVerified`, `HardwareProtectionConfirmed`, `PhasesVerified` **alle** wahr sind, `MaxGridImportW>0` und Status-/Sensoren frisch sind. Bei LiveControl false **keine** Cloud-Änderung durch SmartCharge. Achtung: Bereits von alten UIs geänderte oder zuvor angelegte Wallboxwerte bleiben bestehen; bei Abschaltung von LiveControl muss der Elektrotechniker den sicherheitsrelevanten Zustand selbst prüfen.

Der existierende PUT-Endpunkt für „maxCurrent“ ist **nicht** auf dynamische Wirkung einer laufenden Ladung bestätigt. Vor echtem Aktorzugriff kontrolliert man manuell `RequestAction(CA_LUNA_MAX_CURRENT, 6/8/10)` und reale L1–L3-Aktualisierungen. Phasenwechsel 1↔3 **nicht implementiert**. Es ist **keine** automatische Verbindung zum Fahrzeug-Telemetriesystem vorhanden. Notwendige unabhängige Schutz-/Überwachungseinrichtungen dürfen durch Cloud-Automation nicht ersetzt werden.

## Datenmodell / nachträgliche Eingaben

`State` (persistentes JSON-Attribut):

```
version, mode, paused, entryRequired, tripKm, departure, desiredSoc, reserveSoc,
active, history[], connected, learned{consumption,efficiency,drivingSamples,efficiencySamples},
targetCurrent, reason, lastCommandTs, lastSwitchTs, lastCommandTarget,
lastCommandError, surplusEmaW, surplusPositiveTicks, lastDecision
```

Sitzung (`VehicleModel::newSession`):

```
id, plugTs, unplugTs, recovered, apiSessionId,
meterLastKwh, meterLastTs, energyAcKwh, energySource(api/manual),
meterOriginalAcKwh, meterValid, meterIssue,
odoKm, socAnchorPct, anchorEnergyKwh, socAnchorMoment(plug/now/end),
actualEndSocPct, externallyCharged, entrySkipped, enteredAt, notes
```

Die vom Twingo abgelesenen Werte sind manuell. Der Nutzende kann beim Stecken **Überspringen** wählen, später im aktiven Ladevorgang ergänzen und nachträglich einzelne historische Sitzungen bearbeiten oder vollständig neue historische Sitzungen erstellen (Datum Anfang/Ende, km, Start-SoC, AC-kWh, gemessener End-SoC optional). Für alte Sitzungen lassen sich AC-kWh korrigieren; Herkunft `energySource=manual` und ursprünglicher API-Wert bleiben hinterlegt. Keine historischen kWh manuell in aktive Ladezählung einspielen.

Ankerprinzip: `moment=plug` → Start-SoC + gesamte kWh; `moment=now` → aktueller SoC ab Eingabe, Anker `anchorEnergyKwh=jetzt erfasste AC-kWh`, damit alte kWh nicht doppelt addiert werden; `moment=end` → am Ende gemessener SoC, keine weitere Addition. Sichtbare SoC-/km-Schätzung bleibt unsicher; nach Trennen ggf. „Letzter Stand“ mit explizitem Nicht-Live-Hinweis.

Lernen **nur** auf belegter Fahrt ohne unprotokolliertes externes Laden mit tatsächlich abgelesenem Ende der Vorladung und tatsächlichem Start-SoC der nächsten; Plausibilitätsfilter 8–28 kWh/100 km; EWMA 20 %. Wirkungsgrad nur aus API-energiemäßig gültiger Sitzung und echten Start-/End-SoC-Werten, keine manuell korrigierten Energie-Rohwerte. Standard angenommener Verbrauch 14,5 kWh/100 km, 91 % Ladewirkungsgrad, konfigurierbare nutzbare Kapazität initial 27,5 kWh (Fahrzeugausstattung gegenprüfen).

## Wetter-JSON

`ForecastJsonID` erwartet `{"slots":[{"ts":1795359600,"pvW":1800},{"ts":1795363200,"pvW":4200},...]}` oder Array mit `time` ISO-Timestamp und `pvW`. Slots=PV-Leistung W im Folgezeitraum; bis 168 Slots, 60 % zeitliche Abdeckung, Prognose = Summe max(0,pvW−BaseLoadForecastW), gekappt auf AC-Leistung. Es gibt noch **keinen spezifischen Adapter** auf die vorhandenen DWD/MOSMIX/ICON-Wetter-Kacheln, weil deren konkreter variablenbasierter JSON-Rohwert unbekannt ist.

## UI / Kommunikation

Symcon ≥7.1 HTML-SDK: `GetVisualizationTile()` injiziert initial State und komprimiertes Twingo-Bild, `UpdateVisualizationValue(json_encode(payload))` sendet neue Werte bei jedem Tick; JS `window.handleMessage` ersetzt State und rendert. Useraktion via SDK `requestAction('UI', JSON.stringify({action, data}))`; zugelassen: `mode`, `pause`, `skip`, `trip`, `entry`, `createManual`, `refresh`. Kein Client-Hook-Token. Offline-Demo mit `DEMO=true` simuliert Änderungen lokal.

## Offene technische Schritte in dieser Installation

1. Symcon-Version kontrollieren (≥7.1). Modul-Ordner über Git-Modulverwaltung installieren; Screenshot-Vorschauen sind kein produktiver Integrationsnachweis.
2. Echte IDs PV, Grid, Speicher-SoC und -W zuordnen, Vorzeichen validieren; Alarm/Logging für veraltete Werte.
3. JSON-Prognose des vorhandenen DWD-Wettermoduls in die normalisierte Slot-Variable abbilden (kleiner Adapter); Beispiel vorliegender Wetterskripte abfragen.
4. Hauptskript `12864`: `CA_LUNA_TOTAL_KWH` Quelle prüfen, Session vs Lifetime, API polling, Statusfälle. Prüfen, ob `maxCurrent` **tatsächlich** live den Ladestrom anpasst, ohne die Charge-Amps-App-Konfiguration zu beschädigen.
5. Echte Phasenzahl am Twingo/Luna feststellen; 6,6-kW- oder optionale 11-kW-AC-Ausstattung prüfen; Netzanschlussgrenzen/hardwareseitige Absicherung.
6. Mindestens 2–3 Tage Dry-Run-Regler/Sensorstatus erfassen, dann gestufte Beaufsichtigungs-Tests, erst danach Freigaben.
7. Spätere Module: dynamische Tarife, lokale Push-Warnung bei Planabweichung, Statistik PV-/Netzanteil (mit belastbarer Messwertquelle), Ladesoll-Nachregelung nach realer AC-Lader-Reaktion, ggf. Fahrzeug-Telemetrieintegration, echter Steuerbefehl für sichere Phasenumschaltung nur nach Herstellerfreigabe.

## Testbefehle (Entwicklung, nicht im Symcon-Skript ausführen)

```
php tests/run_tests.php
php tests/mock_module.php
python3 tests/make_previews.py
python3 tests/test_ui.py  # Python Playwright + Chromium lokal nötig
php -l SmartCharge/module.php
```

**Weiterentwicklungsregel:** Keine neue API-Authentifizierung implementieren, bevor das bestehende Skript 12864 geprüft ist; keine physischen Strombegrenzungen/Live-Freigaben ohne konkrete gemessene Gegenprüfung. Backend-Modelle und UI getrennt erweitern, Flags/Status beibehalten, Testfälle zu allen Änderungen ergänzen, Symcon-HTML-SDK-Semantik prüfen.


## Strukturfix v1.1.3

Die interaktiven Demos wurden von `previews/` im Repository-Hauptverzeichnis
nach `docs/previews/` verschoben. Symcon validiert alle nicht ausgenommenen
Hauptordner als eigenstaendige Module und verlangt dort `module.json`.
Die erlaubten Hauptordner sind: `docs`, `libs`, `imgs`, `tests`, `actions`,
sowie tatsaechliche Modulordner (hier `SmartCharge`).
