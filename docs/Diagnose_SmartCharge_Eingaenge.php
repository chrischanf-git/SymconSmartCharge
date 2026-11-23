<?php
/**
 * Luna SmartCharge - einmalige, nur lesende Diagnose der Messwert-Aktualitaet.
 * In ein NEUES PHP-Skript in IP-Symcon kopieren und manuell ausfuehren.
 * Kein RequestAction, keine API-Anfragen und keine Konfigurationsaenderungen.
 * Stand: 22.11.2026
 */
declare(strict_types=1);

$instanceID = 14264; // aus dem uebermittelten Symcon-Objektbaum
if (!IPS_InstanceExists($instanceID)) {
    throw new RuntimeException('SmartCharge-Instanz #' . $instanceID . ' nicht gefunden. ID anpassen.');
}

$cfg = json_decode(IPS_GetConfiguration($instanceID), true);
if (!is_array($cfg)) {
    throw new RuntimeException('Instanz-Konfiguration kann nicht gelesen werden.');
}

$maxAge = max(1, (int)($cfg['MaxDataAgeS'] ?? 210));
$lunaParentID = (int)($cfg['LunaParentID'] ?? 25848);
$now = time();

function lunaCheckVar(string $label, int $id, int $limit, int $now, bool $numeric): string
{
    if ($id <= 0) {
        return sprintf('%-31s %-9s %s', $label, 'FEHLT', 'Keine Variablen-ID ausgewaehlt');
    }
    if (!IPS_VariableExists($id)) {
        return sprintf('%-31s %-9s %s', $label, 'FEHLT', 'Variable #' . $id . ' nicht vorhanden');
    }

    $meta = IPS_GetVariable($id);
    $ts = (int)($meta['VariableUpdated'] ?? 0);
    $age = $ts > 0 ? max(0, $now - $ts) : null;
    $value = GetValue($id);
    $valueText = $numeric
        ? (is_numeric($value) ? (string)$value : '[nicht numerisch]')
        : (($label === 'Luna RAW') ? '[Inhalt ausgeblendet]' : substr((string)$value, 0, 35));
    $okType = !$numeric || is_numeric($value);
    $status = ($age !== null && $age <= $limit && $okType) ? 'AKTUELL' : 'PRUEFEN';
    $ageText = $age === null ? '?' : (string)$age;

    return sprintf('%-31s %-9s ID=%-6d Wert=%-16s Alter=%ss',
        $label, $status, $id, $valueText, $ageText);
}

$required = [
    'PV-Leistung (W)'              => 'PvPowerID',
    'Netzleistung (W)'             => 'GridPowerID',
    'Hausspeicher-SoC (%)'         => 'BatterySocID',
    'Hausspeicherleistung (W)'     => 'BatteryPowerID'
];
$optional = [
    'Hausverbrauch (W, optional)'  => 'HousePowerID',
    'Waermepumpe (W, optional)'    => 'HeatPumpPowerID'
];

echo "LUNA SMARTCHARGE - MESSWERT-DIAGNOSE\n";
echo 'Stand: ' . date('d.m.Y H:i:s', $now) . "\n";
echo 'Instanz: ' . $instanceID . ' | Luna-Kategorie: ' . $lunaParentID . ' | Maximalalter: ' . $maxAge . " s\n\n";
echo "PFLICHTWERTE:\n";
foreach ($required as $label => $key) {
    echo lunaCheckVar($label, (int)($cfg[$key] ?? 0), $maxAge, $now, true) . "\n";
}

echo "\nCHARGE-AMPS-STATUS:\n";
foreach (['Luna RAW' => 'CA_LUNA_RAW', 'Connector Status' => 'CA_LUNA_CONNECTOR_STATUS'] as $label => $ident) {
    $id = ($lunaParentID > 0 && IPS_ObjectExists($lunaParentID))
        ? @IPS_GetObjectIDByIdent($ident, $lunaParentID)
        : false;
    echo lunaCheckVar($label, $id === false ? 0 : (int)$id, $maxAge, $now, false) . "\n";
}

echo "\nOPTIONALE WERTE (nicht notwendig fuer die Datenqualitaetsfreigabe):\n";
foreach ($optional as $label => $key) {
    echo lunaCheckVar($label, (int)($cfg[$key] ?? 0), $maxAge, $now, true) . "\n";
}

echo "\nHinweis: SmartCharge behandelt Sensorwerte als veraltet, wenn VariableUpdated\n";
echo "aelter als MaxDataAgeS ist. Fehlende optionale Quellen allein sperren\n";
echo "die Smartladung nicht. Keine API-Daten, Zugangsdaten oder Tokens ausgegeben.\n";
