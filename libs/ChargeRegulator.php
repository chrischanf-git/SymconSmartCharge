<?php
declare(strict_types=1);

/** Entscheidungslogik, ohne Seiteneffekte; die Symcon-Modulschicht sendet Befehle. */
final class LunaChargeRegulator
{
    /** @param array $d Daten mit pvW, gridW(+Bezug), batW(+Entladung), batSoc, wallboxW, freshness */
    public static function decide(array $state, array $d, array $cfg, ?float $forecastAcKwh, int $now): array
    {
        $phases = (int)$cfg['phases'];
        $minA = (int)$cfg['minA'];
        $maxA = min((int)$cfg['maxA'], (int)floor((float)$cfg['vehicleAcW'] / (230 * $phases)));
        $maxA = max($minA, $maxA);
        $target = LunaVehicleModel::targetSoc($state, (float)$cfg['capacityKwh']);
        $s = LunaVehicleModel::estimate($state['active'], (float)$cfg['capacityKwh'], $state['learned']);
        $connected = (bool)($d['connected'] ?? false);
        if (!$connected && $state['active'] === null && !empty($state['history'])) {
            $last = LunaVehicleModel::estimate($state['history'][0], (float)$cfg['capacityKwh'], $state['learned']);
            if ($last['valid']) {
                $s = $last;
                $s['quality'] = 'Zuletzt beim Abstecken (nicht live)';
            }
        }
        $pause = (bool)$state['paused'];
        $mode = (string)$state['mode'];
        $safe = (bool)($d['sensorsFresh'] ?? false);
        $reason = '';
        $needAc = null;
        $urgent = false;
        $ready = $connected && $s['valid'] && $s['soc'] >= $target['targetSoc'] - 0.4;
        $estTarget = (float)$target['targetSoc'];
        if ($connected && $s['valid']) {
            $needAc = max(0.0, ($estTarget - (float)$s['soc']) / 100 * (float)$cfg['capacityKwh'] /
                max(0.65, (float)$state['learned']['efficiency']));
        }
        $deadline = (int)$state['departure'];
        if ($deadline > $now && $needAc !== null && $needAc > 0.05) {
            $hoursLeft = ($deadline - $now) / 3600;
            $fullChargeKw = min((float)$cfg['vehicleAcW']/1000, $maxA*$phases*230/1000);
            // Zeitpuffer bewusst prognoseabhaengig: Bei wenig vorhergesagter PV wird
            // frueher mit Netzergänzung begonnen. Trotzdem verhindert die minimale
            // physikalische Ladezeit ein Warten bis zur letzten Minute.
            $physicalHours = $needAc / max(1.0, $fullChargeKw);
            $coverage = $forecastAcKwh === null ? 0.0 :
                min(1.0, max(0.0, $forecastAcKwh * 0.75 / max(0.1, $needAc)));
            $bufferHours = 0.3 + (1.0 - $coverage) * 1.0;
            $mustStart = $physicalHours + $bufferHours;
            $urgent = ($hoursLeft <= $mustStart);
        }
        if (!$connected) {
            $reason = 'Kein Fahrzeug angeschlossen'; $desired = 0;
        } elseif ($pause) {
            $reason = 'Manuell pausiert'; $desired = 0;
        } elseif ($ready) {
            $reason = 'Ladeziel erreicht (Auto-SoC geschaetzt)'; $desired = 0;
        } elseif (!$safe) {
            $reason = 'Messwerte fehlen oder sind veraltet: keine Freigabe'; $desired = 0;
        } elseif ($mode === 'now' || $urgent) {
            $reason = $urgent && $mode === 'smart' ? 'Abfahrtsgarantie: fehlende Energie jetzt laden' : 'Jetzt laden: Netzbezug erlaubt';
            $desired = $maxA;
        } else {
            $gridW = (float)$d['gridW'];
            $batteryDraw = max(0, (float)$d['batW']);
            $instantW = min(max(0.0, (float)$d['pvW']), max(0, (float)$d['wallboxW'] - $gridW - $batteryDraw - (float)$cfg['exportMarginW']));
            // Weich nach oben, rasch nach unten bei Lastspitzen.
            $allowedW = min((float)($d['smoothedSurplusW'] ?? $instantW), $instantW + 250);
            $lowBattery = (float)$d['batSoc'] < (float)$cfg['batteryReservePct'];
            if ($lowBattery) {
                $desired = 0; $reason = 'Hausspeicher unter Reserve, PV-Ladung pausiert';
            } else {
                $desired = (int)floor($allowedW / (230 * $phases));
                $desired = $desired >= $minA ? max($minA, min($desired, $maxA)) : 0;
                $reason = $desired ? 'PV-Ueberschuss verfuegbar, Netz-/Speicherbilanz beruecksichtigt'
                                    : 'PV-Ueberschuss unter Mindestladeleistung';
            }
            if ($desired > 0 && !(bool)($d['chargerEnable'] ?? false) && (int)($state['surplusPositiveTicks'] ?? 0) < 2) {
                $desired = 0;
                $reason = 'PV-Ueberschuss wird noch bestaetigt (2 Messungen)';
            }
            if (!$s['valid']) $reason .= ' (ohne Fahrzeug-SoC; keine Abfahrtsgarantie)';
        }
        // Konservatives Anschlussbudget (kein Ersatz fuer hardwareseitige Absicherung).
        if ($desired > 0 && $safe && (int)$cfg['maxGridImportW'] > 0) {
            $grid = (float)$d['gridW'];
            $wallbox = (float)$d['wallboxW'];
            $budgetW = (int)$cfg['maxGridImportW'] - max(0, $grid) + $wallbox;
            $capA = (int)floor(max(0, $budgetW) / (230 * $phases));
            if ($capA < $desired) {
                $desired = $capA >= $minA ? $capA : 0;
                $reason .= '; Netzbezugsbudget begrenzt';
            }
        }
        if ($target['routeNeedsMoreThanFull']) $reason .= '; ACHTUNG: Fahrtbedarf groesser als Akkukapazitaet';
        return [
            'amps' => $desired, 'reason' => $reason, 'urgent' => $urgent,
            'targetSoc' => $target['targetSoc'], 'routeImpossible' => $target['routeNeedsMoreThanFull'],
            'neededAcKwh' => $needAc, 'forecastAcKwh' => $forecastAcKwh, 'estimate' => $s,
        ];
    }
}
