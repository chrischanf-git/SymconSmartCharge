<?php
/**
 * Luna SmartCharge - Symcon 7.1+; erhaelt bestehende Skripte und Charge-Amps-API unveraendert.
 * Nur das vorhandene RequestAction(CA_LUNA_ENABLE/MAX_CURRENT) kann im freigeschalteten Modus schalten.
 */
declare(strict_types=1);
require_once __DIR__ . '/../libs/VehicleModel.php';
require_once __DIR__ . '/../libs/ChargePlanner.php';
require_once __DIR__ . '/../libs/ChargeRegulator.php';

class LunaSmartCharge extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('LunaParentID', 25848);
        foreach (['PvPowerID', 'GridPowerID', 'BatterySocID', 'BatteryPowerID',
                  'HousePowerID', 'HeatPumpPowerID', 'ForecastJsonID'] as $key) {
            $this->RegisterPropertyInteger($key, 0);
        }
        $this->RegisterPropertyInteger('PvSign', 1);
        $this->RegisterPropertyInteger('GridSign', 1); // 1=Bezug +, -1=Einspeisung +
        $this->RegisterPropertyInteger('BatterySign', 1); // 1=Entladung +, -1=Laden +
        $this->RegisterPropertyInteger('Phases', 3); // fest nach Installation bestaetigt, keine Umschaltung
        $this->RegisterPropertyInteger('MaxCurrentA', 16);
        $this->RegisterPropertyInteger('VehicleAcMaxW', 6600);
        $this->RegisterPropertyInteger('BatteryCapacityWh', 27500);
        $this->RegisterPropertyInteger('BatteryReservePct', 35);
        $this->RegisterPropertyInteger('BaseLoadForecastW', 1900);
        $this->RegisterPropertyInteger('ExportMarginW', 250);
        $this->RegisterPropertyInteger('MaxGridImportW', 0);
        $this->RegisterPropertyInteger('MaxDataAgeS', 210);
        $this->RegisterPropertyInteger('CommandIntervalS', 120);
        $this->RegisterPropertyBoolean('LiveControl', false); // sicherer Standard: Dry-Run
        $this->RegisterPropertyBoolean('CurrentControlVerified', false);
        $this->RegisterPropertyBoolean('HardwareProtectionConfirmed', false);
        $this->RegisterPropertyBoolean('PhasesVerified', false);
        $this->RegisterAttributeString('State', json_encode(LunaVehicleModel::freshState()));
        $this->RegisterTimer('Tick', 0, 'LUNAS_Tick($_IPS["TARGET"]);');
        $this->RegisterVariableInteger('Mode', 'Lademodus: 0=Smart, 1=Jetzt', '', 10);
        $this->RegisterVariableBoolean('Paused', 'Laden pausiert', '~Switch', 20);
        $this->RegisterVariableBoolean('EntryRequired', 'Fahrzeugdaten eingeben', '~Switch', 30);
        $this->RegisterVariableFloat('VehicleSoC', 'Fahrzeug-SoC geschaetzt (%)', '', 40);
        $this->RegisterVariableFloat('VehicleRange', 'Reichweite geschaetzt (km)', '', 50);
        $this->RegisterVariableFloat('ChargeEnergy', 'AC-Energie seit Anstecken (kWh)', '', 60);
        $this->RegisterVariableInteger('TargetCurrent', 'SmartCharge Sollstrom (A)', '', 70);
        $this->RegisterVariableString('Reason', 'Regelentscheidung', '', 80);
        $this->RegisterVariableBoolean('Simulation', 'Simulationsbetrieb', '~Switch', 90);
        $this->RegisterVariableString('DataQuality', 'Datenqualitaet', '', 100);
        $this->EnableAction('Mode');
        $this->EnableAction('Paused');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Tick', 60000);
        // Keine Netzabfrage; nur bereits gespeicherte Symcon-Variablen.
        $this->Tick();
    }

    public function GetConfigurationForm()
    {
        return (string)file_get_contents(__DIR__ . '/form.json');
    }

    public function GetVisualizationTile()
    {
        $html = (string)file_get_contents(__DIR__ . '/module.html');
        $photo = @file_get_contents(__DIR__ . '/../imgs/renault-twingo-2026.webp');
        $photoUri = $photo === false ? '' : 'data:image/webp;base64,' . base64_encode($photo);
        $data = $this->buildPayload($this->state(), time());
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        return str_replace(['__LUNA_PHOTO__', '__LUNA_INITIAL__', '__LUNA_DEMO__'],
            [$photoUri, $json ?: '{}', 'false'], $html);
    }

    /** Von der Kachel (HTML-SDK) ODER Symcon-Variablenaktion aufgerufen; keine Token im HTML. */
    public function RequestAction($Ident, $Value)
    {
        $sem = 'LunaSmartCharge_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 1200)) throw new RuntimeException('SmartCharge ist beschaeftigt. Bitte erneut versuchen.');
        try {
        $state = $this->state();
        $action = $Ident;
        $p = [];
        if ($Ident === 'UI') {
            $request = json_decode((string)$Value, true);
            if (!is_array($request)) throw new InvalidArgumentException('Ungueltige UI-Daten');
            $action = (string)($request['action'] ?? '');
            $p = is_array($request['data'] ?? null) ? $request['data'] : [];
        }
        switch ($action) {
            case 'Mode':
                if (!in_array((int)$Value, [0, 1], true)) throw new InvalidArgumentException('Modus 0 oder 1');
                $state['mode'] = (int)$Value === 1 ? 'now' : 'smart';
                break;
            case 'mode':
                $value = (string)($p['value'] ?? '');
                if (!in_array($value, ['now', 'smart'], true)) throw new InvalidArgumentException('Modus unbekannt');
                $state['mode'] = $value;
                break;
            case 'Paused':
                $state['paused'] = (bool)$Value;
                break;
            case 'pause':
                $state['paused'] = (bool)($p['value'] ?? true);
                break;
            case 'skip':
                if ($state['active'] !== null) {
                    $state['entryRequired'] = false;
                    $state['active']['entrySkipped'] = true;
                }
                break;
            case 'trip':
                $this->setTrip($state, $p);
                break;
            case 'createManual':
                $new = LunaVehicleModel::createManualSession($p, time());
                array_unshift($state['history'], $new);
                usort($state['history'], static fn(array $a, array $b): int => ($b['plugTs'] ?? 0) <=> ($a['plugTs'] ?? 0));
                $state['history'] = array_slice($state['history'], 0, 50);
                $state['learned'] = LunaVehicleModel::learn($state['history'], $state['active'], $this->capacity());
                break;
            case 'entry':
                $id = (string)($p['sessionId'] ?? 'active');
                $where = -1;
                if ($id === 'active' && $state['active'] !== null) {
                    LunaVehicleModel::saveEntry($state['active'], $p, time());
                    $state['entryRequired'] = false;
                } else {
                    foreach ($state['history'] as $index => $row) {
                        if ((string)$row['id'] === $id) { $where = $index; break; }
                    }
                    if ($where < 0) throw new InvalidArgumentException('Sitzung nicht mehr vorhanden');
                    LunaVehicleModel::saveEntry($state['history'][$where], $p, time());
                    if (array_key_exists('manualEnergyAcKwh', $p) && $p['manualEnergyAcKwh'] !== '' && $p['manualEnergyAcKwh'] !== null) {
                        LunaVehicleModel::overrideHistoricalEnergy($state['history'][$where], $p['manualEnergyAcKwh']);
                    }
                }
                $this->setTrip($state, $p);
                $state['learned'] = LunaVehicleModel::learn($state['history'], $state['active'], $this->capacity());
                break;
            case 'refresh':
                break;
            default:
                throw new InvalidArgumentException('Aktion nicht erlaubt: ' . $action);
        }
        $this->saveState($state);
        } finally { IPS_SemaphoreLeave($sem); }
        $this->Tick();
    }

    /** Haupttakt: liest nur Symcon-RAM, 1x/60s, 0 Cloud-GETs. */
    public function Tick(): void
    {
        $sem = 'LunaSmartCharge_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($sem, 150)) return;
        try {
            $now = time();
            $state = $this->state();
            if ((int)$state['departure'] <= 0) {
                $morning = strtotime('tomorrow 07:30');
                $state['departure'] = $morning === false ? $now + 18 * 3600 : $morning;
            }
            $d = $this->inputData($now);
            $conn = (bool)$d['connected'];
            $wasConn = (bool)($state['connected'] ?? false);
            // Keine falschen Stecksitzungen bei veraltetem Cloudstatus.
            if ($d['chargerFresh']) {
                if ($conn && !$wasConn) {
                    $state['active'] = LunaVehicleModel::newSession($now, $d['meterKwh'], $d['apiSessionId'], true);
                    $state['entryRequired'] = true;
                }
                if ($conn && $state['active'] === null) {
                    $state['active'] = LunaVehicleModel::newSession($now, $d['meterKwh'], $d['apiSessionId'], true);
                    $state['entryRequired'] = true;
                }
                if (!$conn && $wasConn && $state['active'] !== null) {
                    $state['active']['unplugTs'] = $now;
                    array_unshift($state['history'], $state['active']);
                    $state['history'] = array_slice($state['history'], 0, 50);
                    $state['active'] = null;
                    $state['entryRequired'] = false;
                    $state['learned'] = LunaVehicleModel::learn($state['history'], null, $this->capacity());
                }
                $state['connected'] = $conn;
                if ($conn && $state['active'] !== null) {
                    LunaVehicleModel::updateMeter($state['active'], $d['meterKwh'], $d['apiSessionId'], $now,
                        (float)$this->ReadPropertyInteger('VehicleAcMaxW') / 1000);
                }
            }
            // Glaettung ohne sekundenlange Timer: zwei gueltige Tick-Messungen bis PV-Start.
            if ($d['sensorsFresh']) {
                $instant = min(max(0.0, (float)$d['pvW']), max(0.0, (float)$d['wallboxW'] - (float)$d['gridW']
                    - max(0.0, (float)$d['batW']) - $this->ReadPropertyInteger('ExportMarginW')));
                $oldEma = $state['surplusEmaW'];
                $state['surplusEmaW'] = $oldEma === null ? $instant : round(0.35 * $instant + 0.65 * (float)$oldEma);
                $minPower = 6 * 230 * $this->ReadPropertyInteger('Phases');
                $state['surplusPositiveTicks'] = $instant >= $minPower
                    ? min(5, (int)$state['surplusPositiveTicks'] + 1) : 0;
                $d['smoothedSurplusW'] = $state['surplusEmaW'];
            } else {
                $state['surplusPositiveTicks'] = 0;
            }
            $forecastSlots = $this->forecastSlots($now);
            $deadline = (int)$state['departure'];
            $forecastAc = LunaChargePlanner::expectedAcKwh($forecastSlots, $now, $deadline,
                (float)$this->ReadPropertyInteger('BaseLoadForecastW'),
                (float)$this->ReadPropertyInteger('VehicleAcMaxW') / 1000);
            $cfg = $this->regulatorConfig();
            $decision = LunaChargeRegulator::decide($state, $d, $cfg, $forecastAc, $now);
            // Stoppen duerfen wir immer; nach normalem PV-Stop 3 Minuten Ruhe.
            $lastSwitch = (int)($state['lastSwitchTs'] ?? 0);
            if ($state['mode'] === 'smart' && !$decision['urgent'] && $lastSwitch > 0
                && ($now - $lastSwitch) < 180
                && ($state['lastCommandTarget'] ?? '') === 'stop'
                && $decision['amps'] > 0) {
                $decision['amps'] = 0;
                $decision['reason'] = 'PV-Ladepause: 3 Minuten Mindestruhe';
            }
            $state['targetCurrent'] = (int)$decision['amps'];
            $state['reason'] = (string)$decision['reason'];
            // Kein reiner Zeitstempel im Persistenzattribut: vermeidet unnoetige Flash-Schreibvorgaenge.
            $this->maybeSendCommand($state, $d, $decision, $now);
            $this->saveState($state);
            $this->outputVariables($state, $decision, $d);
            $this->UpdateVisualizationValue($this->buildPayload($state, $now, $d, $decision));
        } catch (Throwable $e) {
            $this->SendDebug('TickError', $e->getMessage(), 0);
            IPS_LogMessage('LunaSmartCharge', 'Fehler: ' . $e->getMessage());
        } finally {
            IPS_SemaphoreLeave($sem);
        }
    }

    private function state(): array
    {
        $decoded = json_decode($this->ReadAttributeString('State'), true);
        if (!is_array($decoded)) return LunaVehicleModel::freshState();
        return array_replace(LunaVehicleModel::freshState(), $decoded);
    }

    private function saveState(array $state): void
    {
        $new = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($new !== false && $this->ReadAttributeString('State') !== $new) {
            $this->WriteAttributeString('State', $new);
        }
    }

    private function setTrip(array &$state, array $p): void
    {
        if (array_key_exists('tripKm', $p)) {
            $v = filter_var($p['tripKm'], FILTER_VALIDATE_INT);
            if ($v === false || $v < 0 || $v > 2500) throw new InvalidArgumentException('Strecke 0 bis 2500 km');
            $state['tripKm'] = $v;
        }
        if (array_key_exists('desiredSoc', $p)) {
            $v = filter_var($p['desiredSoc'], FILTER_VALIDATE_FLOAT);
            if ($v === false || $v < 0 || $v > 100) throw new InvalidArgumentException('Ziel-SoC 0 bis 100 %');
            $state['desiredSoc'] = $v;
        }
        if (array_key_exists('reserveSoc', $p)) {
            $v = filter_var($p['reserveSoc'], FILTER_VALIDATE_FLOAT);
            if ($v === false || $v < 0 || $v > 70) throw new InvalidArgumentException('Reserve 0 bis 70 %');
            $state['reserveSoc'] = $v;
        }
        if (!empty($p['departure'])) {
            $ts = is_numeric($p['departure']) ? (int)$p['departure'] : (int)strtotime((string)$p['departure']);
            if ($ts < time() - 300 || $ts > time() + 30 * 86400) {
                throw new InvalidArgumentException('Abfahrt muss innerhalb der naechsten 30 Tage liegen');
            }
            $state['departure'] = $ts;
        }
    }

    private function capacity(): float
    {
        return max(10.0, $this->ReadPropertyInteger('BatteryCapacityWh') / 1000.0);
    }

    private function id(string $ident): int
    {
        $parent = $this->ReadPropertyInteger('LunaParentID');
        if ($parent <= 0 || !IPS_ObjectExists($parent)) return 0;
        $id = @IPS_GetObjectIDByIdent($ident, $parent);
        return $id !== false && IPS_VariableExists($id) ? (int)$id : 0;
    }

    private function sensor(int $id, int $now, int $maxAge): array
    {
        if ($id <= 0 || !IPS_VariableExists($id)) return [null, false, null];
        $meta = IPS_GetVariable($id);
        $age = max(0, $now - (int)($meta['VariableUpdated'] ?? 0));
        $v = GetValue($id);
        if (!is_numeric($v)) return [null, false, $age];
        return [(float)$v, $age <= $maxAge, $age];
    }

    private function inputData(int $now): array
    {
        $age = $this->ReadPropertyInteger('MaxDataAgeS');
        $idRaw = $this->id('CA_LUNA_RAW');
        $rawAge = $idRaw > 0 ? $now - (int)(IPS_GetVariable($idRaw)['VariableUpdated'] ?? 0) : PHP_INT_MAX;
        $statusID = $this->id('CA_LUNA_CONNECTOR_STATUS');
        $status = $statusID ? strtolower(trim((string)GetValue($statusID))) : '';
        $connected = in_array($status, ['preparing', 'charging', 'suspendedev', 'suspendedevse', 'finishing'], true);
        $apiId = $this->id('CA_LUNA_SESSION_ID');
        $meterID = $this->id('CA_LUNA_TOTAL_KWH');
        $powerID = $this->id('CA_LUNA_POWER_KW');
        $enableID = $this->id('CA_LUNA_ENABLE');
        $currentID = $this->id('CA_LUNA_MAX_CURRENT');
        $m = $meterID ? GetValue($meterID) : null;
        $meter = is_numeric($m) && (float)$m >= 0 ? (float)$m : null;
        $wallboxW = $powerID ? max(0, (float)GetValue($powerID) * 1000.0) : 0.0;
        [$pv, $fpv] = $this->sensor($this->ReadPropertyInteger('PvPowerID'), $now, $age);
        [$grid, $fgrid] = $this->sensor($this->ReadPropertyInteger('GridPowerID'), $now, $age);
        [$batSoc, $fbatsoc] = $this->sensor($this->ReadPropertyInteger('BatterySocID'), $now, $age);
        [$bat, $fbat] = $this->sensor($this->ReadPropertyInteger('BatteryPowerID'), $now, $age);
        [$house, $fhouse] = $this->sensor($this->ReadPropertyInteger('HousePowerID'), $now, $age);
        [$heat, $fheat] = $this->sensor($this->ReadPropertyInteger('HeatPumpPowerID'), $now, $age);
        $chargerFresh = $rawAge >= 0 && $rawAge <= $age && $status !== '';
        return [
            'connected' => $connected, 'chargerFresh' => $chargerFresh,
            'chargerAge' => $rawAge === PHP_INT_MAX ? null : $rawAge,
            'connectorStatus' => $status, 'apiSessionId' => $apiId ? (string)GetValue($apiId) : '',
            'meterKwh' => $meter, 'wallboxW' => $wallboxW,
            'enableID' => $enableID, 'currentID' => $currentID,
            'chargerEnable' => $enableID ? (bool)GetValue($enableID) : false,
            'chargerCurrent' => $currentID ? (int)GetValue($currentID) : 0,
            'pvW' => $pv === null ? null : $pv * $this->ReadPropertyInteger('PvSign'),
            'gridW' => $grid === null ? null : $grid * $this->ReadPropertyInteger('GridSign'),
            'batW' => $bat === null ? null : $bat * $this->ReadPropertyInteger('BatterySign'),
            'batSoc' => $batSoc, 'houseW' => $house, 'heatW' => $heat,
            'sensorsFresh' => $chargerFresh && $fpv && $fgrid && $fbatsoc && $fbat,
            'wpFresh' => $fheat, 'houseFresh' => $fhouse,
        ];
    }

    private function forecastSlots(int $now): array
    {
        $id = $this->ReadPropertyInteger('ForecastJsonID');
        if ($id <= 0 || !IPS_VariableExists($id)) return [];
        return LunaChargePlanner::parseForecast((string)GetValue($id), $now);
    }

    private function regulatorConfig(): array
    {
        return [
            'phases' => in_array($this->ReadPropertyInteger('Phases'), [1, 3], true) ? $this->ReadPropertyInteger('Phases') : 3,
            'minA' => 6, 'maxA' => max(6, min(32, $this->ReadPropertyInteger('MaxCurrentA'))),
            'vehicleAcW' => max(1400, $this->ReadPropertyInteger('VehicleAcMaxW')),
            'capacityKwh' => $this->capacity(),
            'batteryReservePct' => $this->ReadPropertyInteger('BatteryReservePct'),
            'exportMarginW' => max(0, $this->ReadPropertyInteger('ExportMarginW')),
            'maxGridImportW' => $this->ReadPropertyInteger('MaxGridImportW'),
        ];
    }

    private function mayLive(): bool
    {
        return $this->ReadPropertyBoolean('LiveControl')
            && $this->ReadPropertyBoolean('CurrentControlVerified')
            && $this->ReadPropertyBoolean('HardwareProtectionConfirmed')
            && $this->ReadPropertyBoolean('PhasesVerified')
            && $this->ReadPropertyInteger('MaxGridImportW') > 0;
    }

    private function maybeSendCommand(array &$state, array $data, array $decision, int $now): void
    {
        if (!$this->mayLive() || !$data['chargerFresh'] || !$data['connected'] ||
            !$data['enableID'] || !$data['currentID']) return;
        $targetA = (int)$decision['amps'];
        $isOn = (bool)$data['chargerEnable'];
        $last = (int)$state['lastCommandTs'];
        $minInterval = max(60, $this->ReadPropertyInteger('CommandIntervalS'));
        // Sicherheits-Stopp darf nicht durch das regulaere 120-s-Befehlsintervall blockiert werden.
        // Gegen Cloud-Flut wird Stopp dennoch auf mindestens 20 s begrenzt.
        $effectiveInterval = ($targetA === 0 && $isOn) ? 20 : $minInterval;
        if (($now - $last) < $effectiveInterval) return;
        // Ohne verifizierte frische Sensorwerte wird NIE gestartet oder erhoeht.
        if (!$data['sensorsFresh'] && $targetA > 0) return;
        try {
            if ($targetA === 0) {
                if (!$isOn) return;
                RequestAction((int)$data['enableID'], false);
                $state['lastSwitchTs'] = $now;
                $state['lastCommandTarget'] = 'stop';
            } elseif (!$isOn) {
                if ((int)$data['chargerCurrent'] !== $targetA) {
                    RequestAction((int)$data['currentID'], $targetA);
                    $state['lastCommandTarget'] = 'current=' . $targetA;
                } else {
                    RequestAction((int)$data['enableID'], true);
                    $state['lastSwitchTs'] = $now;
                    $state['lastCommandTarget'] = 'start';
                }
            } elseif ((int)$data['chargerCurrent'] !== $targetA) {
                RequestAction((int)$data['currentID'], $targetA);
                $state['lastCommandTarget'] = 'current=' . $targetA;
            } else {
                return;
            }
            $state['lastCommandTs'] = $now;
            $state['lastCommandError'] = '';
            IPS_LogMessage('LunaSmartCharge', 'Befehl: ' . $state['lastCommandTarget'] . ' | ' . $state['reason']);
        } catch (Throwable $e) {
            $state['lastCommandTs'] = $now;
            $state['lastCommandError'] = $e->getMessage();
            IPS_LogMessage('LunaSmartCharge', 'Aktion fehlgeschlagen: ' . $e->getMessage());
        }
    }

    private function outputVariables(array $state, array $decision, array $d): void
    {
        $est = $decision['estimate'];
        $this->writeIfChanged('Mode', $state['mode'] === 'now' ? 1 : 0);
        $this->writeIfChanged('Paused', (bool)$state['paused']);
        $this->writeIfChanged('EntryRequired', (bool)$state['entryRequired']);
        $this->writeIfChanged('VehicleSoC', (float)($est['soc'] ?? 0));
        $this->writeIfChanged('VehicleRange', (float)($est['rangeKm'] ?? 0));
        $this->writeIfChanged('ChargeEnergy', (float)($state['active']['energyAcKwh'] ?? 0));
        $this->writeIfChanged('TargetCurrent', (int)$decision['amps']);
        $this->writeIfChanged('Reason', (string)$decision['reason']);
        $this->writeIfChanged('Simulation', !$this->mayLive());
        $this->writeIfChanged('DataQuality', $est['quality'] . ($d['sensorsFresh'] ? '' : ' | Energiedaten nicht aktuell'));
    }

    private function writeIfChanged(string $ident, $value): void
    {
        $id = $this->GetIDForIdent($ident);
        if (GetValue($id) !== $value) {
            $this->SetValue($ident, $value);
        }
    }

    private function buildPayload(array $state, int $now, ?array $d = null, ?array $decision = null): array
    {
        if ($d === null) $d = $this->inputData($now);
        if ($decision === null) {
            $forecast = LunaChargePlanner::expectedAcKwh($this->forecastSlots($now), $now, (int)$state['departure'],
                $this->ReadPropertyInteger('BaseLoadForecastW'), $this->ReadPropertyInteger('VehicleAcMaxW') / 1000);
            $decision = LunaChargeRegulator::decide($state, $d, $this->regulatorConfig(), $forecast, $now);
        }
        $history = [];
        foreach (array_slice($state['history'], 0, 12) as $row) {
            $history[] = [
                'id' => $row['id'], 'plugTs' => $row['plugTs'], 'unplugTs' => $row['unplugTs'],
                'energyAcKwh' => $row['energyAcKwh'], 'energySource' => $row['energySource'] ?? 'api', 'meterValid' => $row['meterValid'],
                'odoKm' => $row['odoKm'], 'socAnchorPct' => $row['socAnchorPct'],
                'socAnchorMoment' => $row['socAnchorMoment'],
                'actualEndSocPct' => $row['actualEndSocPct'],
                'externallyCharged' => $row['externallyCharged'], 'entrySkipped' => $row['entrySkipped']
            ];
        }
        return [
            'now' => $now, 'isDemo' => false, 'isLive' => $this->mayLive(),
            'connected' => $d['connected'], 'chargerFresh' => $d['chargerFresh'],
            'sensorsFresh' => $d['sensorsFresh'], 'chargerAge' => $d['chargerAge'],
            'connectorStatus' => $d['connectorStatus'], 'chargerEnabled' => $d['chargerEnable'],
            'mode' => $state['mode'], 'paused' => $state['paused'], 'entryRequired' => $state['entryRequired'],
            'active' => $state['active'] === null ? null : [
                'id' => $state['active']['id'], 'plugTs' => $state['active']['plugTs'],
                'energyAcKwh' => $state['active']['energyAcKwh'], 'meterValid' => $state['active']['meterValid'],
                'meterIssue' => $state['active']['meterIssue'], 'odoKm' => $state['active']['odoKm'],
                'socAnchorPct' => $state['active']['socAnchorPct'],
                'socAnchorMoment' => $state['active']['socAnchorMoment'],
                'entrySkipped' => $state['active']['entrySkipped']
            ],
            'history' => $history,
            'estimate' => $decision['estimate'], 'learned' => $state['learned'],
            'targetSoc' => $decision['targetSoc'], 'targetCurrent' => $decision['amps'],
            'reason' => $decision['reason'], 'urgent' => $decision['urgent'],
            'routeImpossible' => $decision['routeImpossible'],
            'neededAcKwh' => $decision['neededAcKwh'], 'forecastAcKwh' => $decision['forecastAcKwh'],
            'tripKm' => $state['tripKm'], 'departure' => $state['departure'],
            'desiredSoc' => $state['desiredSoc'], 'reserveSoc' => $state['reserveSoc'],
            'houseBatteryReservePct' => $this->ReadPropertyInteger('BatteryReservePct'),
            'pvW' => $d['pvW'], 'gridW' => $d['gridW'], 'batteryW' => $d['batW'],
            'batterySoc' => $d['batSoc'], 'houseW' => $d['houseW'], 'heatPumpW' => $d['heatW'],
            'wallboxW' => $d['wallboxW'], 'chargerCurrent' => $d['chargerCurrent'],
            'lastCommandError' => $state['lastCommandError'],
            'learningCapacityKwh' => $this->capacity()
        ];
    }
}
