# SmartCharge v1.2.0 - Smartphone und Dashboard separat

**Stand: 23.11.2026.** Dieses Paket ersetzt die bisherige UI aus v1.1.4, bewahrt aber den bestehenden SmartCharge-Controller und seine gespeicherten Fahrzeug-/Energiedaten. **Keine neue Charge-Amps-Anmeldung, keine zweite Ladesteuerung, kein Livebetrieb durch das Update.**

## Was in v1.2.0 neu ist

- **Bestehende Instanz `Luna SmartCharge PV-Lademanager`** (in dieser Installation ID **14264**) zeigt jetzt eine **eigenstaendige Smartphone-Oberflaeche**. Die **Startseite** enthaelt nur Fahrzeuguebersicht, Ladeleistung/geladene Energie, Wahl Smart/Jetzt, naechste Abfahrt und **Ladung nachtragen**. Ueber die untere Navigation gelangen Nutzer zu **Details** (Hausenergie, Regelung, Lernen, Pause/Fahrzeugdaten) und **Ladungen** (Historie, Korrekturen).
- **Neuer, zusaetzlicher Modultyp `Luna SmartCharge Energie-Dashboard`** (Ordner `Dashboard/`). Diesen als **separate Symcon-Instanz** anlegen. In deren Konfiguration `ControllerInstanceID` auf die bestehende SmartCharge-Instanz (hier **14264**) setzen. Dashboard und mobile Oberflaeche sind zwei verschiedene Kacheln. Das Dashboard liest nur den Controller und ist absichtlich **schreibgeschuetzt**.
- Beide Instanzen koennen **unabhaengig optisch eingestellt werden**: Akzent-, Hintergrund-, Karten-, Text-, Rahmen- und Sekundaerfarbe; Rundungen, Rahmenstaerke, Kartenabstand, Bildhoehe, Bildposition, `cover`/`contain`, Foto anzeigen/verbergen und eigenes Bild aus einem **Symcon-Bildmedium**. Default-Foto ist der gruene Twingo aus `imgs/renault-twingo-2026.webp`.
- Unter **Optik und Design** der Instanz wird nach **Uebernehmen** eine **Farbvorschau** in der Konsole dargestellt. Eine umfangreichere interaktive Demo mit Farbwaehler liegt unter `docs/previews/SmartCharge_Designwerkstatt.html`. Die Demo nutzt Beispieldaten und steuert keine echte Wallbox.
- Individuelle Bildmedien: nur JPG, PNG oder WebP mit **maximal 2 MB**. Bei ungueltigem oder zu grossem Medium erscheint sicherheitshalber das mitgelieferte Twingo-Foto. Keine externen Bild-URLs, Tokens oder Scripts in der Darstellung.
- Controllerdaten werden weiterhin zyklisch alle **60 Sekunden** verarbeitet. Das neue Dashboard liest den Controller ebenfalls nur alle **60 Sekunden**, ohne eigene Cloudabfragen. Die vorhandenen `RequestAction`-Stellbefehle laufen nur ueber den Controller und bleiben durch bisherige Freigaben gesperrt.

## Update der bestehenden GitHub-Installation

1. **Vorher eine Sicherung** deiner laufenden Symcon-Installation und ggf. des Git-Repositories anlegen.
2. Dieses ZIP entpacken. `library.json`, `SmartCharge/`, `Dashboard/`, `libs/`, `imgs/`, `docs/`, `tests/` muessen **direkt in der Wurzel** des Git-Repositories stehen. `docs/previews/` ist erlaubt; ein `previews/`-Ordner **direkt neben `library.json`** erzeugt wieder einen Symcon-Modulfehler.
3. Auf GitHub die neuen und geaenderten Dateien hochladen und committen. `SmartCharge/module.php`, `SmartCharge/module.html`, `SmartCharge/form.json`, `library.json` und `libs/UiTheme.php` dabei aktualisieren. `Dashboard/` **neu** hinzufuegen. Vorhandene Instanz **nicht loeschen**.
4. In Symcon unter `Kern-Instanzen > Modules` das bestehende Repository **aktualisieren**. Danach die **bisherige SmartCharge-Instanz ID 14264** einmal oeffnen, das neue Design-Panel pruefen und **Uebernehmen**. Deine alten Messwert-IDs, Fahrten, Modus/Abfahrtsziele und bisherigen Sicherheitsschalter werden dadurch nicht geloescht.
5. Visualisierung neu laden: Die existierende **SmartCharge-Kachel** ist jetzt die Smartphone-App mit `Start / Details / Ladungen`.
6. **Neue Instanz anlegen**: `Luna SmartCharge Energie-Dashboard`, bevorzugt unter derselben Wallbox-Kategorie. Dort `SmartCharge-Controller` auf **14264** setzen, **Uebernehmen** klicken und die **Dashboard-Instanz als zweite, eigenstaendige Kachel** zur Visualisierung hinzufuegen.
7. Optik bei **jeder der beiden Instanzen** separat einstellen. Nach dem Speichern die zugehoerige Kachel neu oeffnen, damit das aktualisierte Bild und CSS geladen werden.
8. **Simulationsbetrieb beibehalten**, bis Stromsteuerung, Sensorvorzeichen, Datenalter, Forecast und elektrische Schutzfunktionen geprueft wurden.

### Aktuell bekannte Variablen-IDs dieser Installation (aus Diagnose 22.11.2026)

| Signal | ID | Beschreibung |
|---|---:|---|
| SmartCharge-Controller | `14264` | Bestehende Instanz, nicht neu anlegen |
| Charge-Amps-Variablen | Kategorie `25848` | Bestehendes Luna-Laufsystem |
| PV-Leistung | `26684` | W, Produktion positiv |
| Netzleistung | `37583` | W, Bezug positiv |
| Hausspeicher-SoC | `47921` | Prozent |
| Hausspeicherleistung | `40466` | W, soll nun alle 120 s aktualisiert werden |
| Hausverbrauch optional | `25505` | W |
| Waermepumpe optional | noch nicht zugewiesen | W |
| Wetterprognose JSON | noch nicht zugewiesen | Zeit-Slots erforderlich |

### Hinweis zur echten Darstellung in Symcon

Die Smartphone-Kachel bleibt auch am PC bewusst kompakt wie eine mobile App. Das zusaetzliche Dashboard ist fuer groessere Bildschirme ausgelegt. Farb-, Bild- und Rahmenoptionen veraendern nur die **Darstellung**, nicht die Laderegelung. Die dynamische UI wird mit dem **HTML-SDK ab Symcon 7.1** bereitgestellt. **Keine neuen WebHooks, keine geheimen Tokens im Browser.**

---

## Historische Einrichtung und Konzeptdokumentation (Stand bis v1.1.4)


## Bild- und Layoutaktualisierung v1.1.4 (22.11.2026)

- Das Renault-Twingo-Bild in `imgs/renault-twingo-2026.webp` wurde durch das vom Anwender bereitgestellte Motiv mit gruenem Hintergrund ersetzt und fuer die mobile Anzeige zugeschnitten/komprimiert.
- In `SmartCharge/module.html` wurden nur Bildposition, Bildhintergrund und der Status-Badge angepasst. Alle Aktionen, Variablen, Sensor-Verknuepfungen und Reglerfunktionen sind gegenueber v1.1.3 unveraendert.
- Nach dem GitHub-Update die Modulverwaltung aktualisieren, die Kachel einmal neu laden und ggf. den Visualisierungs-Cache leeren. Bestehende Instanz **nicht loeschen**: Zustand und eingetragene Variablen bleiben erhalten.
- Die Visualisierung liest das Bild in `GetVisualizationTile()` serverseitig ein. Fuer die Anzeige der neuen Grafik muss die Kachel neu geladen werden.
- `docs/previews` bleibt im `docs`-Ordner und darf nicht als `previews` in der Repository-Wurzel liegen.

# Charge Amps Luna SmartCharge – Einrichtung und Inbetriebnahme

**Urspruengliche Erstversion:** 1.1.3 · **Stand:** 22.11.2026 · **Status:** Fertig programmiertes Erstpaket, **nicht an einer echten Symcon-/Charge-Amps-Anlage getestet. Standard: SIMULATION.**

Die ursprüngliche modulare Erweiterung ergaenzte vorhandene Skripte und veraenderte diese nicht. Ab v1.2.0 sind Smartphone- und Desktop-Dashboard zwei getrennte Kacheln. Vorher enthielt sie eine gruene Smartphone-/Desktop-Kachel mit dem bereitgestellten Foto des grünen Renault Twingo (2026). Die Kachel nutzt das **Symcon-HTML-SDK ab Symcon 7.1**, damit Steueraktionen ohne einen im Browser sichtbaren Geheim-Token über die Anmeldung der Visualisierung laufen.

### Kompatibilitaetshinweis zu Symcon 7.1-9.0

Die vorherige Modulversion 1.1 verwendete im `Create()`-Aufruf die Konstante `INSTANCE_VISUALIZATION_TYPE_HTML`, die in aelteren Symcon-Versionen noch nicht definiert ist. In Version **1.1.1** wird deshalb der kompatible Wert `1` an `SetVisualizationType()` uebergeben. Das HTML-SDK und die Methode `SetVisualizationType(1)` werden ab Symcon 7.1 unterstuetzt. Das Modul startet weiterhin ohne Live-Steuerung.

### Kompatibilitaetsfix 1.1.2: Instanzerstellung / Visualisierungsdaten

Beim Anlegen der Instanz trat auf aelteren Symcon-Versionen die Meldung `Cannot auto-convert value for parameter Value (Type does not match)` auf, weil `UpdateVisualizationValue()` ein PHP-Array anstatt eines skalaren JSON-Werts uebergeben bekam. **Version 1.1.2** codiert das Statusobjekt vor dem Senden mit `json_encode()`. `handleMessage()` im HTML kann JSON-Strings bereits verarbeiten. Die Stufenlogik und der sichere Simulationsmodus bleiben unveraendert. Mit einem streng typisierten Mock (Simulation der aelteren HTML-SDK-Schnittstelle) wurde dieser Fall nun ebenfalls getestet.

### Strukturfix 1.1.3: Symcon-Modulverwaltung / previews

Symcon behandelt unbekannte Ordner direkt neben `library.json` als Module.
Der bisherige Hauptordner `previews/` wurde deshalb als fehlerhaftes Modul
(`module.json fehlt`) erkannt. In 1.1.3 liegen die Demo-Dateien unter
`docs/previews/`, das von Symcon erlaubt ist. Alle Echtzeit- und
Wallboxfunktionen bleiben unveraendert.

**Wichtig bei einem bereits hochgeladenen GitHub-Repository:** Der alte
Ordner `previews/` im Repository-Hauptverzeichnis muss **geloescht bzw.
nach `docs/previews/` verschoben** werden. Das Hochladen einer neuen ZIP-Datei
allein entfernt diesen alten Ordner **nicht**. In der GitHub-Webansicht
kann man mit der Taste `.` den Web-Editor oeffnen, den Ordner verschieben
und die Aenderung committen. Danach Symcon -> Modules -> Auf
Aktualisierung pruefen.

## 1. Was bereits funktioniert (Quellcode / Vorschau)

- **Seit v1.2.0 getrennt:** SmartCharge-Controller = kompakte mobile UI; neue Dashboard-Instanz = eigenstaendige Read-only-Kachel. Vorher war es eine responsive HTML-SDK-Visualisierung.
- Beim Erkennen einer physisch plausiblen Steckverbindung: Hinweis zur Eingabe von Kilometerstand und **abgelesenem** Fahrzeug-Akku-SoC. Bei offener Kachel erscheint die Eingabemaske; eine geschlossene Smartphone-App kann nicht selbst geöffnet werden.
- **Überspringen** und jederzeit **später eintragen**, inkl. bereits abgeschlossener Sitzungen (bis zu 50 gespeicherte Sitzungen; letzte 8 in Liste). Auch **bisher fehlende Ladesitzungen vollständig manuell anlegen** und AC-kWh bei vorhandenen Sitzungen nachträglich korrigieren.
- Referenzzeitpunkt auswählen: **Beim Anstecken** oder **Gerade jetzt** (nachträglich eingegebener aktueller Fahrzeug-SoC). Dadurch werden zuvor geladene kWh nicht noch einmal gezählt. Für vergangene Sitzungen auch **am Ende abgelesenen SoC** erfassen.
- Laufende **SoC-/Reichweitenschätzung** aus tatsächlich gezählten AC-kWh, nutzbarer Kapazität, Ladeverlustmodell und Verbrauchsmodell. Anzeige mit Kennzeichnung **geschätzt**.
- Konservatives Lernen des Verbrauchs aus aufeinanderfolgenden Fahrten nur bei nachvollziehbar gemessenem *Abfahrts*-SoC und *Ansteck*-SoC, dokumentiertem Kilometerstand und **ohne unprotokolliertes Laden unterwegs**; Ladenverluste nur bei tatsächlich abgelesenen Start-/End-SoC.
- Smart/Jetzt/Pause, Ziel-SoC, Abfahrt und Reichweitenbedarf; PV-Überschuss, Netzbilanz, Hausbatterie-Reserve, Wärmepumpe/Haus als Messwert.
- Prognoseauswertung bei **normalisierten Zeitreihen** (siehe Abschnitt 6): abhängig von prognostiziertem PV-Ertrag wird der Startpuffer für ergänzendes Netzladen zwischen ca. 0,3 und 1,3 Stunden angepasst. Reale PV-Erzeugung begrenzt die zulässige PV-Ladung zusätzlich.
- **Dry-Run standardmäßig**, Wolkenfilter und geglättete Leistungsstufen, Start nach zwei Überschussmessungen, 3 Minuten Mindestruhe nach Stopp, Cloud-Befehle frühestens alle 120 Sekunden.

**Nicht zugesichert:** tatsächliche dynamische Wirksamkeit des bereits vorhandenen `maxCurrent`-Cloud-Endpoints, 1-/3-Phasen-Umschaltung, exakter Fahrzeug-Akkuwert, echte Fahrzeug-Telemetrie, absolut garantierte rechtzeitige Fahrtbereitschaft oder hardwareseitige Netzabsicherung. Das sind prüfpflichtige Voraussetzungen bzw. weitere Schritte.

## 2. Vorhandene Installation bleibt bestehen

| Komponente | Ausgangsstand |
|---|---|
| Charge-Amps-Kategorie | `25848` |
| Vorhandenes API-/Aktions-/Polling-Skript | `12864` |
| Vorhandene Stellgrößen | `CA_LUNA_ENABLE`, `CA_LUNA_MAX_CURRENT` |
| Vorhandene Messpunkte | `CA_LUNA_CONNECTOR_STATUS`, `CA_LUNA_SESSION_ID`, `CA_LUNA_TOTAL_KWH`, `CA_LUNA_POWER_KW`, `CA_LUNA_RAW` |
| Alter WebHook | `/hook/chargeamps-luna` (wird von **diesem Modul nicht benötigt**) |
| Bereits programmierter Stellstrombereich | `6–16 A` |

Das vorhandene Skript `12864` muss weiter **separat zyklisch** über Symcon die Charge-Amps-Daten aktualisieren. Das Modul ruft die Cloud selbst **nicht** ab, um unnötige HTTP-Anfragen zu vermeiden. Bei geöffneter Kachel werden nur kleine Zustandsmeldungen übertragen (kein Browser-Dauerpolling); Sitzungen sind auf 50 begrenzt. Keine Zugangsdaten, Tokens oder RFID-Tags in dieses neue Modul kopieren.

## 3. Installation des PHP-Moduls (Symcon 7.1+)

**Vorher:** Sicherung deiner bestehenden Skripte und Symcon-Daten anlegen. Symcon-Version in der Konsole prüfen.

**Empfohlener Weg (über eigenes Git-Repository):**

1. ZIP entpacken. Das Verzeichnis `ChargeAmps_SmartCharge_v1` enthält an der obersten Ebene `library.json` sowie die Unterordner `SmartCharge`, `libs` und `imgs`. Diese Struktur **unverändert** in das Stammverzeichnis eines eigenen Git-Repositories übertragen (öffentlich oder privat, je nach Zugriff/Verwaltung).
2. Symcon-Konsole öffnen → **Kern-Instanzen → Modules / Modulverwaltung** → „+“ → Repository-URL eintragen. Symcon installiert die Bibliothek direkt von Git.
3. Im Objektbaum unter deiner Charge-Amps-Kategorie **Neue Instanz** → **Luna SmartCharge PV-Lademanager** auswählen. Die neue Instanz ist unabhängig vom bestehenden Skript.
4. Instanzeinstellungen öffnen; `LunaParentID` zunächst auf `25848` belassen und alle echten Sensoren zuordnen (siehe unten). Änderungen mit **Übernehmen** bestätigen.
5. Die bestehende Controller-Instanz als **Smartphone-Kachel** einfuegen. Fuer das separate Dashboard eine **zweite Instanz vom Modultyp Energie-Dashboard** anlegen und mit der ersten verknuepfen (siehe Abschnitt v1.2.0 oben).

**Alternativ lokale Modul-Entwicklung:** Die Bibliothek kann in den Modulordner der jeweiligen Symcon-Installation kopiert werden; danach muss der Symcon-Dienst für die Registrierung eines neuen Moduls neu gestartet werden. Der Pfad hängt von Plattform und Symcon-Installation ab, auf SymBox ist der Git-Weg deutlich einfacher. Verzeichnisse nicht einzeln in die Script-Konsole einfügen: `module.php` ist **kein normales PHP-Skript**, sondern eine `IPSModule`-Klasse.

## 4. Echte Symcon-Variablen zuordnen

Im Konfigurationsformular der neuen Instanz:

| Eigenschaft | Notwendig? | Einheit/Interpretation |
|---|---|---|
| `LunaParentID` | ja | bisher `25848` |
| `PvPowerID` | Smart ja | **W**, Erzeugung positiv |
| `GridPowerID` | Smart ja | **W**, Netzbezug positiv / Einspeisung negativ (oder umgekehrtes Vorzeichen einstellen) |
| `BatterySocID` | Smart ja | **%**, 0–100 |
| `BatteryPowerID` | Smart ja | **W**, Batterieentladung positiv / Aufladung negativ (Vorzeichen umstellbar) |
| `HousePowerID` | optional | **W**, Hausverbrauch zur Anzeige (nicht zusätzlich von der Netzbilanz abziehen) |
| `HeatPumpPowerID` | optional | **W**, elektrische Leistung WP zur Anzeige; Verbrauch bereits in Netzbilanz enthalten |
| `ForecastJsonID` | optional | JSON-String mit Zeitpunkten und **PV-Leistung in W**, siehe unten |

**Für Smartregelung sind PV, Netz, Speicher-SoC und Speicherleistung erforderlich.** Wenn eine Quelle fehlt, veraltet ist oder die Wallbox-Rohdaten veraltet sind, steht der Sollstrom auf **0 A**. Solange Simulation läuft, bleibt die echte Wallbox unberührt.

**Wichtig:** Die ursprünglichen Werte aus deinem Wetter-Modul sind bisher nicht als Objekt-IDs bekannt; für diesen Punkt muss gegebenenfalls ein kleiner Adapter erstellt werden. Aktuelle Haus-/WP-/PV-Werte nicht doppelt vom Netzanschlusswert abziehen! In der Regelgröße ist die Wärmepumpe über die reale Netzbilanz bereits enthalten.

## 5. Fahrzeug / Elektrik konfigurieren

- `Phases`: **tatsächlich fest verwendete Ladephasen**, zunächst 1 oder 3 nach fachlicher Prüfung. Es gibt **keine dynamische Phasenumschaltung**.
- `VehicleAcMaxW`: zunächst `6600 W` als **konfigurierbarer Startwert**. Nur auf 11000 W setzen, wenn dein konkreter Twingo tatsächlich den entsprechenden AC-Lader besitzt.
- `BatteryCapacityWh`: 27500 als modellabhängiger Startwert; technisch verifizieren.
- `MaxCurrentA`: hier max. `16 A` passend zum bestehenden Hauptskript, niemals höher einstellen, solange dessen Grenzen 6–16 A sind.
- `BatteryReservePct`: z. B. 35 %; bei Unterschreitung pausiert Smartladen (nicht notwendigerweise Sofort-/Notladung).
- `ExportMarginW`: z. B. 250 W Puffer.
- `MaxGridImportW`: **maximaler gesamter Netzbezug am Hausanschluss in W** (nicht nur zusätzlich zur Wallbox); erst nach Prüfung von Installation, Vorsicherungen, Netzbedingungen und sonstigen Lasten setzen. `0` **verhindert die Live-Freigabe**.

Die neue Regelung ersetzt **keine** elektrische Schutzfunktion bzw. **kein** schnelles, unabhängiges Hardware-Lastmanagement. Bei Cloud-Ausfall kann ein Netzlastsprung nicht innerhalb der normalen 60–120 Sekunden ausgeregelt werden.

## 6. Format für die PV-Prognose

Die Prognose muss zeitaufgelöst vorliegen. Das Modul versteht eine Stringvariable mit JSON in z. B. diesem Format:

```json
{
  "slots": [
    {"ts": 1795359600, "pvW": 1800},
    {"ts": 1795363200, "pvW": 4200},
    {"ts": 1795366800, "pvW": 7600},
    {"ts": 1795370400, "pvW": 5900}
  ]
}
```

- `ts`: Unixzeit in Sekunden; alternativ ein Feld `time` als ISO-Zeitstring.
- `pvW`: prognostizierte mittlere PV-Leistung **in Watt** für den Zeitraum ab diesem Slot bis zum nächsten.
- 15- oder 60-Minuten-Abstände sind sinnvoll. Maximal werden 168 Slots verarbeitet. Prognosen mit weniger als 60 % Abdeckung bis zur Abfahrt werden ignoriert.
- **Nicht** nur ein Tagesertrag in kWh. Ohne Zeitreihe erscheint „Keine Zeitprognose“. Der Regler arbeitet dann mit aktuellem Überschuss und konservativem Abfahrtspuffer.
- Die Prognose verwendet eine **konfigurierte Grundlast** (`BaseLoadForecastW`, Standard 1900 W) und ist eine Näherung, keine garantierte Ladezusage.

Alternatives Beispiel: `[ {"time":"2026-11-23T11:00:00+01:00", "pvW":4500}, {"time":"2026-11-23T12:00:00+01:00", "pvW":5800} ]`.

## 7. Schrittweise Inbetriebnahme (bitte in dieser Reihenfolge)

1. Neue Kachel öffnen: Oben muss **SIMULATION** stehen. Handymodus, grüner Twingo, Eingabeformular, Umschaltung und **Überspringen** prüfen.
2. Auto an- und abstecken: Bei geöffneter Kachel erscheint die Eingabeaufforderung spätestens nach der nächsten 60-Sekunden-Aktualisierung (sofern das alte Polling frischen Status liefert). Bei geschlossenem Handy erst beim nächsten Öffnen.
3. Zunächst **Überspringen** drücken. Später in der Fahrzeugkarte auf **Fahrtdaten bearbeiten** oder unter **Vergangene Ladungen → Nachtragen** tippen. „Beim Anstecken“ benötigt den damals tatsächlich abgelesenen SoC; „Gerade jetzt“ nutzt den aktuellen SoC und vermeidet Doppelzählung früherer kWh.
3a. Für eine vergessene, inzwischen beendete Ladesitzung unter **Vergangene Ladungen → + Vergangene Ladung manuell hinzufügen** Beginn, Ende, Kilometerstand, tatsächlich abgelesenen Start-SoC und die AC-kWh eingeben. Den realen End-SoC optional ergänzen. Zum Korrigieren der Energie einer bereits aufgezeichneten Sitzung **Nachtragen** öffnen und das optionale Feld **AC-Ladeenergie (kWh)** nutzen. Manuell korrigierte Energie wird gekennzeichnet und nicht zum Wirkungsgradlernen verwendet.
4. Mit dem Auto bei realem Ladebeginn/Ende in der **API-RAW-Antwort** und bei `CA_LUNA_TOTAL_KWH` prüfen, ob Energie **pro Sitzung** oder **kumuliert** gezählt wird. Nach neuer Session/durch Pause und Neustart prüfen. Unplausible Sprünge deaktivieren die SoC-Berechnung.
5. Sensoren zuordnen und Richtungen prüfen: Während Einspeisung muss im Modul „Netzeinspeisung“ stehen; bei Hausbatterie-Entladung muss diese als positiv erfasst sein. Bei WP-Start sollte der verfügbare PV-Überschuss sinken.
6. Für mehrere Tage Sollstrom und Entscheidungsgrund **nur in der Simulation** protokollieren: Sonne/Wolken/Wärmepumpe/Hausspeicher unter Reserve / zeitkritische Abfahrt / Sensor fällt aus.
7. **Separat manuell** im vorhandenen System `RequestAction` auf `CA_LUNA_MAX_CURRENT` in einer beaufsichtigten Sitzung testen: 6→8→10 A und echte Phasenströme aus `CA_LUNA_L1_A` usw. prüfen. Der bisherige Settings-PUT kann sich anders verhalten als ein dynamisches Lastmanagement. Ohne klare Reaktion **keine Live-Steuerung**.
8. Hardwareseitige Leistungsabsicherung, festen Phasenmodus und Anschlussbudget fachlich prüfen. Erst dann die Freigaben `CurrentControlVerified`, `HardwareProtectionConfirmed`, `PhasesVerified` und `LiveControl` aktivieren sowie `MaxGridImportW > 0` setzen. **Erst dann** kann das Modul eine Stellaktion auslösen.
9. Nach Freigabe engmaschig prüfen; bei Problemen **LiveControl wieder deaktivieren**. Ein zuvor gesetzter Wallboxwert bleibt dadurch möglicherweise bestehen; falls erforderlich selbständig einen sicheren Zustand herstellen.

## 8. Häufige Fragen / Fehler

**Zeigt keine Auto-km/SoC:** Eingabe übersprungen, Energiebaseline ungültig oder noch nie eingegeben. Es werden **keine erfundenen Fahrzeugdaten** angezeigt. Fahrzeug-BMS-Telemetrie liegt nicht vor.

**Regler bleibt bei 0 A:** PV unter Mindestladeleistung (6 A × Zahl der Phasen × 230 V), Hausakku unter Reserve, pausiert, Ziel erreicht, veralteter Netz-/Batteriemesswert, Auto nicht verbunden oder Mindest-Ladepause.

**Keine PV-Prognose:** Zeitreihen-JSON fehlt oder deckt nur einen Teil bis Abfahrt ab.

**Keine echten Befehle trotz Umschaltung:** Normaler Dry-Run. Die vier Sicherheitsfreigaben und `MaxGridImportW > 0` müssen nach den technischen Tests erfüllt sein.

**Kein Lernfortschritt:** Verifizierte Fahrdaten und **echte** End-SoC-Ablesungen fehlen. Geschätzte SoCs werden nicht als Wahrheit behandelt; das ist Absicht.

**WebHook aus alter Desktop-Kachel:** Das alte HTML enthielt einen Token im Browsercode. Das neue Modul braucht ihn nicht; alte Hooks trotzdem gegen unbefugte Aufrufe absichern.

## 9. Dateien

- `SmartCharge/module.php` – Symcon-Instanz / lokale Messpunkte / Takt / sichere UI-Aktionen.
- `SmartCharge/module.html` – responsive Grün-UI für Symcon-HTML-SDK.
- `SmartCharge/form.json` – technische Parametrierung.
- `libs/VehicleModel.php` – Sitzungen, Eingabe, Nachtrag, SoC und Lernen.
- `libs/ChargePlanner.php` – Zeitreihen-PV-Prognose.
- `libs/ChargeRegulator.php` – Smart-/Jetzt-Entscheidungslogik.
- `imgs/renault-twingo-2026.webp` – komprimierter Zuschnitt aus deinem hochgeladenen Foto.
- `docs/previews/*.html` – offline bedienbare Demos mit Beispieldaten, ohne echte Wallboxsteuerung.
- `tests/run_tests.php` – lokale PHP-Rechentests (kein Symcon-/Cloud-End-to-End-Test).
- `tests/mock_module.php` – Modul-Stubs (ohne echtes Symcon).
- `tests/test_ui.py` – getestete Smartphone-/Desktop-Interaktionen in Chromium.
- `tests/make_previews.py` – standalone Demo-HTML erzeugen.
- `docs/TECHNIK_KI.md` – technische Übergabe für spätere Weiterentwicklung.
