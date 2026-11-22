<?php
/** Reine Berechnungen und Sitzungsbuchhaltung; keine Symcon-Abhaengigkeiten. */
declare(strict_types=1);

final class LunaVehicleModel
{
    public static function freshState(): array
    {
        return [
            'version' => 1, 'mode' => 'smart', 'paused' => false, 'entryRequired' => false,
            'tripKm' => 85, 'departure' => 0, 'desiredSoc' => 80.0, 'reserveSoc' => 20.0,
            'active' => null, 'history' => [], 'connected' => false,
            'learned' => ['consumption' => 14.5, 'efficiency' => 0.91, 'drivingSamples' => 0, 'efficiencySamples' => 0],
            'targetCurrent' => 0, 'reason' => 'Noch keine Daten', 'lastCommandTs' => 0,
            'lastSwitchTs' => 0, 'lastCommandTarget' => null, 'lastCommandError' => '',
            'lastDecision' => '', 'surplusEmaW' => null, 'surplusPositiveTicks' => 0,
        ];
    }

    public static function newSession(int $ts, ?float $meterKwh, string $apiSessionId, bool $recovered = false): array
    {
        return [
            'id' => date('Ymd-His', $ts) . '-' . bin2hex(random_bytes(3)),
            'plugTs' => $ts, 'unplugTs' => null, 'recovered' => $recovered,
            'apiSessionId' => $apiSessionId,
            'meterLastKwh' => $meterKwh, 'meterLastTs' => $ts,
            'energyAcKwh' => 0.0, 'energySource' => 'api', 'meterOriginalAcKwh' => null, 'meterValid' => $meterKwh !== null,
            'meterIssue' => $meterKwh === null ? 'Keine gueltige Energiemessung' : '',
            'odoKm' => null, 'socAnchorPct' => null, 'anchorEnergyKwh' => 0.0,
            'socAnchorMoment' => null, 'actualEndSocPct' => null,
            'externallyCharged' => false, 'entrySkipped' => false,
            'enteredAt' => null, 'notes' => ''
        ];
    }

    /** Delta-Zaehler, laesst bei zweifelhaften Reset-/Sprungwerten die Schätzung aus. */
    public static function updateMeter(array &$session, ?float $reading, string $apiId, int $now, float $maxAcKw): void
    {
        if ($reading === null || !is_finite($reading) || $reading < 0) {
            return;
        }
        $prev = $session['meterLastKwh'];
        $oldId = (string)($session['apiSessionId'] ?? '');
        $changedId = $oldId !== '' && $apiId !== '' && $oldId !== $apiId;
        $prevTs = (int)($session['meterLastTs'] ?? $now);
        $session['apiSessionId'] = $apiId !== '' ? $apiId : $oldId;
        $session['meterLastKwh'] = $reading;
        if ($prev === null || abs($reading - (float)$prev) > 0.00001) {
            $session['meterLastTs'] = $now;
        }
        if ($prev === null) {
            $session['meterValid'] = false; // ueberlappungsfreie Referenz nicht gegeben
            $session['meterIssue'] = 'Zaehlerreferenz erst spaeter vorhanden';
            return;
        }
        $delta = $reading - (float)$prev;
        if ($delta < -0.001) {
            if ($changedId) {
                // Neuer OCPP-Sessionzaehler beginnt bei 0. Bei unbekanntem Reset ohne Session-ID: ungueltig.
                $delta = $reading;
            } else {
                $session['meterValid'] = false;
                $session['meterIssue'] = 'Ungeklaerter Ruecksprung des Energiezaehlers';
                return;
            }
        }
        $elapsed = max(1, $now - $prevTs);
        $limit = max(0.5, $maxAcKw * $elapsed / 3600.0 * 1.4 + 0.2);
        if ($delta > $limit) {
            $session['meterValid'] = false;
            $session['meterIssue'] = 'Unplausibler Energiezaehler-Sprung';
            return;
        }
        if (($session['meterValid'] ?? false) && $delta > 0) {
            $session['energyAcKwh'] = round((float)$session['energyAcKwh'] + $delta, 4);
        }
    }

    public static function saveEntry(array &$session, array $input, int $now): void
    {
        if (!isset($input['odoKm'], $input['socPct'])) {
            throw new InvalidArgumentException('Kilometerstand und Auto-SoC sind erforderlich.');
        }
        $odo = filter_var($input['odoKm'], FILTER_VALIDATE_INT);
        $soc = filter_var($input['socPct'], FILTER_VALIDATE_FLOAT);
        if ($odo === false || $odo < 0 || $odo > 9999999 || $soc === false || $soc < 0 || $soc > 100) {
            throw new InvalidArgumentException('Kilometerstand oder Auto-SoC ungueltig.');
        }
        $moment = (string)($input['moment'] ?? 'plug');
        if (!in_array($moment, ['plug', 'now', 'end'], true)) {
            throw new InvalidArgumentException('Ungueltiger Referenzzeitpunkt.');
        }
        if ($moment === 'end' && $session['unplugTs'] === null) {
            throw new InvalidArgumentException('End-Referenz nur fuer beendete Sitzungen.');
        }
        $session['odoKm'] = $odo;
        $session['socAnchorPct'] = (float)$soc;
        $session['socAnchorMoment'] = $moment;
        $session['anchorEnergyKwh'] = $moment === 'plug' ? 0.0 : (float)$session['energyAcKwh'];
        $session['externallyCharged'] = (bool)($input['externallyCharged'] ?? false);
        $session['entrySkipped'] = false;
        $session['enteredAt'] = $now;
        if (array_key_exists('endSocPct', $input) && $input['endSocPct'] !== '' && $input['endSocPct'] !== null) {
            $end = filter_var($input['endSocPct'], FILTER_VALIDATE_FLOAT);
            if ($end === false || $end < 0 || $end > 100) {
                throw new InvalidArgumentException('Gemessener End-SoC ungueltig.');
            }
            $session['actualEndSocPct'] = (float)$end;
        }
    }

    /** Manuelle AC-kWh nur fuer bereits ABGESCHLOSSENE Sitzungen; mit Herkunftsmarkierung. */
    public static function overrideHistoricalEnergy(array &$session, $value): void
    {
        if ($session['unplugTs'] === null) {
            throw new InvalidArgumentException('Laufende Sitzung darf nicht manuell ueberschrieben werden.');
        }
        $kwh = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($kwh === false || !is_finite((float)$kwh) || $kwh < 0 || $kwh > 150) {
            throw new InvalidArgumentException('AC-Energie muss zwischen 0 und 150 kWh liegen.');
        }
        if (($session['meterOriginalAcKwh'] ?? null) === null) {
            $session['meterOriginalAcKwh'] = (float)$session['energyAcKwh'];
        }
        $session['energyAcKwh'] = round((float)$kwh, 3);
        if (($session['socAnchorMoment'] ?? '') === 'end') {
            $session['anchorEnergyKwh'] = $session['energyAcKwh'];
        }
        $session['energySource'] = 'manual';
        $session['meterValid'] = true;
        $session['meterIssue'] = 'AC-kWh manuell nachgetragen';
    }

    /** Eine bisher nicht erfasste, beendete Ladesitzung manuell anlegen. */
    public static function createManualSession(array $input, int $now): array
    {
        $plug = filter_var($input['plugTs'] ?? null, FILTER_VALIDATE_INT);
        $unplug = filter_var($input['unplugTs'] ?? null, FILTER_VALIDATE_INT);
        if ($plug === false || $unplug === false || $plug === null || $unplug === null ||
            $plug < $now - 3 * 365 * 86400 || $plug > $now || $unplug <= $plug ||
            $unplug > $now + 60 || ($unplug - $plug) > 72 * 3600) {
            throw new InvalidArgumentException('Start/Ende der manuellen Sitzung ungueltig (max. 72 h).');
        }
        $s = self::newSession((int)$plug, 0.0, 'manuell', false);
        $s['unplugTs'] = (int)$unplug;
        self::saveEntry($s, $input, $now);
        self::overrideHistoricalEnergy($s, $input['manualEnergyAcKwh'] ?? null);
        $s['meterOriginalAcKwh'] = null; // Keine API-Rohablesung bei rein manuell erfasster Sitzung.
        return $s;
    }

    public static function estimate(?array $session, float $capacityKwh, array $learned): array
    {
        $invalid = ['valid' => false, 'soc' => null, 'rangeKm' => null,
            'gainKm' => null, 'addedAcKwh' => null, 'quality' => 'Keine Fahrzeugangaben'];
        if ($session === null || $session['socAnchorPct'] === null) {
            return $invalid;
        }
        if (!($session['meterValid'] ?? false)) {
            $invalid['quality'] = 'Energiemessung ungueltig';
            return $invalid;
        }
        $eta = min(0.98, max(0.65, (float)$learned['efficiency']));
        $cons = min(30, max(8, (float)$learned['consumption']));
        $ac = max(0, (float)$session['energyAcKwh'] - (float)$session['anchorEnergyKwh']);
        $calculated = min(100, (float)$session['socAnchorPct'] + $ac * $eta / $capacityKwh * 100);
        $hasReadEnd = $session['unplugTs'] !== null &&
            ($session['actualEndSocPct'] !== null || ($session['socAnchorMoment'] ?? '') === 'end');
        $soc = $session['actualEndSocPct'] !== null && $session['unplugTs'] !== null
            ? (float)$session['actualEndSocPct'] : $calculated;
        return [
            'valid' => true, 'soc' => round($soc, 1),
            'rangeKm' => round($capacityKwh * $soc / 100 / $cons * 100),
            'gainKm' => round(max(0.0, (float)$session['energyAcKwh']) * $eta / $cons * 100),
            'addedAcKwh' => round($ac, 2),
            'quality' => $hasReadEnd ? 'Gemessener Endwert' : (($session['energySource'] ?? 'api') === 'manual' ? 'Manuelle Energie + Schaetzung' : 'Nur Schaetzung'),
        ];
    }

    public static function targetSoc(array $state, float $capacityKwh): array
    {
        $c = (float)$state['learned']['consumption'];
        $km = (float)$state['tripKm'];
        $routeSoc = 100 * ($km * $c / 100) / $capacityKwh + (float)$state['reserveSoc'];
        $target = max($routeSoc, (float)$state['desiredSoc']);
        return ['targetSoc' => round(min(100.0, $target), 1), 'routeNeedsMoreThanFull' => $routeSoc > 100];
    }

    /** Lernt NUR aus nachvollziehbaren Referenzen, Ausreisser ausgeschlossen. */
    public static function learn(array $history, ?array $active, float $capacityKwh): array
    {
        $rows = $history;
        if ($active !== null) $rows[] = $active;
        usort($rows, static fn(array $a, array $b): int => ($a['plugTs'] ?? 0) <=> ($b['plugTs'] ?? 0));
        $cons = 14.5;
        $eff = 0.91;
        $nDrive = 0;
        $nEff = 0;
        $prev = null;
        foreach ($rows as $s) {
            if ($prev !== null
                && $prev['unplugTs'] !== null && $prev['actualEndSocPct'] !== null
                && $s['odoKm'] !== null && $prev['odoKm'] !== null
                && ($s['socAnchorMoment'] ?? '') === 'plug'
                && $s['socAnchorPct'] !== null && !$s['externallyCharged']) {
                $km = (int)$s['odoKm'] - (int)$prev['odoKm'];
                $socDiff = (float)$prev['actualEndSocPct'] - (float)$s['socAnchorPct'];
                if ($km >= 20 && $km <= 1000 && $socDiff > 3) {
                    $sample = ($socDiff / 100 * $capacityKwh) / $km * 100;
                    if ($sample >= 8 && $sample <= 28) {
                        $cons = 0.8 * $cons + 0.2 * $sample;
                        ++$nDrive;
                    }
                }
            }
            if ($s['unplugTs'] !== null && $s['actualEndSocPct'] !== null
                && $s['socAnchorPct'] !== null && $s['meterValid'] && ($s['energySource'] ?? 'api') !== 'manual') {
                $ac = (float)$s['energyAcKwh'] - (float)$s['anchorEnergyKwh'];
                $deltaSoc = (float)$s['actualEndSocPct'] - (float)$s['socAnchorPct'];
                if ($ac >= 1.0 && $deltaSoc >= 4) {
                    $sample = ($deltaSoc / 100 * $capacityKwh) / $ac;
                    if ($sample >= 0.65 && $sample <= 0.98) {
                        $eff = 0.8 * $eff + 0.2 * $sample;
                        ++$nEff;
                    }
                }
            }
            $prev = $s;
        }
        return ['consumption' => round($cons, 3), 'efficiency' => round($eff, 4),
            'drivingSamples' => $nDrive, 'efficiencySamples' => $nEff];
    }
}
