# Charge Amps Luna SmartCharge – Einrichtung und Inbetriebnahme

**Version:** 1.1 · **Stand:** 22.11.2026 · **Status:** Fertig programmiertes Erstpaket, **nicht an einer echten Symcon-/Charge-Amps-Anlage getestet. Standard: SIMULATION.**

Die neue modulare Erweiterung ergänzt vorhandene Skripte und verändert diese nicht. Sie enthält eine grüne Smartphone-/Desktop-Kachel mit dem bereitgestellten Foto des grünen Renault Twingo (2026). Die Kachel nutzt das **Symcon-HTML-SDK ab Symcon 7.1**, damit Steueraktionen ohne einen im Browser sichtbaren Geheim-Token über die Anmeldung der Visualisierung laufen.

## 1. Was bereits funktioniert (Quellcode / Vorschau)

- Mobile & Desktop als **eine responsive HTML-SDK-Visualisierung**, inkl. Foto, grüner Akzentfarbe.
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
5. Die Instanz als **Kachel** in deiner Symcon-Kachelvisualisierung einfügen. Die Darstellung ist responsive für Handy und Desktop.

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
- `previews/*.html` – offline bedienbare Demos mit Beispieldaten, ohne echte Wallboxsteuerung.
- `tests/run_tests.php` – lokale PHP-Rechentests (kein Symcon-/Cloud-End-to-End-Test).
- `tests/mock_module.php` – Modul-Stubs (ohne echtes Symcon).
- `tests/test_ui.py` – getestete Smartphone-/Desktop-Interaktionen in Chromium.
- `tests/make_previews.py` – standalone Demo-HTML erzeugen.
- `docs/TECHNIK_KI.md` – technische Übergabe für spätere Weiterentwicklung.
