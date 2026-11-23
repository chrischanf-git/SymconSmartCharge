<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/UiTheme.php';

/** Separate read-only dashboard tile linked to an existing SmartCharge controller. */
class LunaSmartChargeDashboard extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('ControllerInstanceID', 0);
        foreach (LunaUiTheme::defaults() as $key => $default) {
            if (is_int($default)) $this->RegisterPropertyInteger($key, $default);
            elseif (is_bool($default)) $this->RegisterPropertyBoolean($key, $default);
            else $this->RegisterPropertyString($key, $default);
        }
        $this->RegisterTimer('DashboardTick', 0, 'LUNAD_Tick($_IPS["TARGET"]);');
        // Compatible with Symcon >= 7.1; constant name not available on all hosts.
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('DashboardTick', 60000);
        $this->Tick();
    }

    public function GetConfigurationForm()
    {
        $form = json_decode((string)file_get_contents(__DIR__ . '/form.json'), true);
        if (!is_array($form)) return '{}';
        $panel = LunaUiTheme::formPanel('Dashboard: Optik und Design');
        $panel['items'][] = ['type' => 'Label', 'caption' => 'Vorschau der derzeit gespeicherten Farbwahl:'];
        $panel['items'][] = ['type' => 'Image', 'image' => LunaUiTheme::previewUri($this->uiTheme()), 'width' => '430px', 'center' => true];
        $form['elements'][] = $panel;
        return (string)json_encode($form, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function GetVisualizationTile()
    {
        $theme = $this->uiTheme();
        $html = (string)file_get_contents(__DIR__ . '/module.html');
        $photoUri = LunaUiTheme::photoUri($theme, __DIR__ . '/../imgs/renault-twingo-2026.webp');
        $payload = $this->controllerData();
        // Escape as JS literal; JSON payload already parsed and reencoded.
        $data = json_decode($payload, true);
        $json = json_encode(is_array($data) ? $data : ['error' => 'Ungueltige Controllerdaten'],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        return str_replace(['__LUNA_PHOTO__','__LUNA_INITIAL__','__LUNA_DEMO__','__LUNA_THEME_CSS__'],
            [$photoUri, $json ?: '{}', 'false', LunaUiTheme::css($theme)], $html);
    }

    public function Tick(): void
    {
        try {
            // The controller already polls its local Symcon values. This dashboard never calls the cloud.
            $this->UpdateVisualizationValue($this->controllerData());
        } catch (Throwable $e) {
            $this->SendDebug('DashboardTick', $e->getMessage(), 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        throw new InvalidArgumentException('Dashboard ist schreibgeschuetzt. Steuerung in der Smartphone-Kachel.');
    }

    private function controllerData(): string
    {
        $id = $this->ReadPropertyInteger('ControllerInstanceID');
        if ($id <= 0 || !IPS_InstanceExists($id)) {
            return json_encode(['error' => 'Bitte SmartCharge-Controller-Instanz in der Dashboard-Konfiguration auswaehlen.']);
        }
        if (!function_exists('LUNAS_GetDashboardData')) {
            return json_encode(['error' => 'SmartCharge-Controller noch nicht aktualisiert. Bitte Modulupdate pruefen.']);
        }
        try {
            $json = LUNAS_GetDashboardData($id);
            if (!is_string($json) || !is_array(json_decode($json, true))) {
                return json_encode(['error' => 'Controller hat keine gueltigen Statusdaten geliefert.']);
            }
            return $json;
        } catch (Throwable $e) {
            return json_encode(['error' => 'Controllerdaten nicht verfuegbar: ' . $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
    private function uiTheme(): array
    {
        $cfg = [];
        foreach (LunaUiTheme::defaults() as $key => $default) {
            if (is_int($default)) $cfg[$key] = $this->ReadPropertyInteger($key);
            elseif (is_bool($default)) $cfg[$key] = $this->ReadPropertyBoolean($key);
            else $cfg[$key] = $this->ReadPropertyString($key);
        }
        return $cfg;
    }

}
