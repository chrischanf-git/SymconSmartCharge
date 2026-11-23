<?php
declare(strict_types=1);
/** Stubtest OHNE echte Symcon-/Charge-Amps-Hardware. */
$GLOBALS['MOCK_VALUES'] = [];
$GLOBALS['MOCK_IDENTS'] = [];
$GLOBALS['MOCK_NEXT_ID'] = 40000;
function IPS_SemaphoreEnter($key, $wait) { return true; }
function IPS_SemaphoreLeave($key) { return true; }
function IPS_ObjectExists($id) { return false; }
function IPS_InstanceExists($id) { return (int)$id === 10001; }
function LUNAS_GetDashboardData($id) { return $GLOBALS['TEST_MAIN']->GetDashboardData(); }
function IPS_VariableExists($id) { return isset($GLOBALS['MOCK_VALUES'][$id]); }
function GetValue($id) { return $GLOBALS['MOCK_VALUES'][$id] ?? null; }
function IPS_LogMessage($who,$txt) { /* noop */ }
class IPSModule {
    public int $InstanceID = 10001;
    public array $props = [], $attrs=[], $variableIds=[];
    public $lastPayload;
    public $visualizationType = null;
    public function __construct() { $this->Create(); }
    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterPropertyInteger($n,$v) { $this->props[$n]=$v; }
    public function RegisterPropertyBoolean($n,$v) { $this->props[$n]=$v; }
    public function RegisterPropertyString($n,$v) { $this->props[$n]=$v; }
    public function RegisterAttributeString($n,$v) { $this->attrs[$n]=$v; }
    public function RegisterTimer($n,$v,$script) {}
    public function SetTimerInterval($n,$v) {}
    public function RegisterVariableInteger($n,$name,$profile,$pos){$this->reg($n,0);}
    public function RegisterVariableBoolean($n,$name,$profile,$pos){$this->reg($n,false);}
    public function RegisterVariableFloat($n,$name,$profile,$pos){$this->reg($n,0.0);}
    public function RegisterVariableString($n,$name,$profile,$pos){$this->reg($n,'');}
    private function reg($n,$val){$id=++$GLOBALS['MOCK_NEXT_ID'];$this->variableIds[$n]=$id;$GLOBALS['MOCK_VALUES'][$id]=$val;}
    public function GetIDForIdent($n){return $this->variableIds[$n];}
    public function ReadPropertyInteger($n){return (int)$this->props[$n];}
    public function ReadPropertyBoolean($n){return (bool)$this->props[$n];}
    public function ReadPropertyString($n){return (string)$this->props[$n];}
    public function ReadAttributeString($n){return (string)$this->attrs[$n];}
    public function WriteAttributeString($n,$v){$this->attrs[$n]=$v;}
    public function SetValue($n,$v){$GLOBALS['MOCK_VALUES'][$this->variableIds[$n]]=$v;}
    public function EnableAction($n){}
    public function SetVisualizationType($t){$this->visualizationType=$t;}
    public function UpdateVisualizationValue($data){
        // Simuliert Symcon < 9.1: Nur skalare Werte/Strings, keine PHP-Arrays.
        if (!is_string($data)) throw new TypeError('Cannot auto-convert value for parameter Value (Type does not match)');
        $this->lastPayload=json_decode($data,true,512,JSON_THROW_ON_ERROR);
    }
    public function SendDebug($what,$data,$level){}
}
require __DIR__ . '/../SmartCharge/module.php';
$m = new LunaSmartCharge();
$GLOBALS['TEST_MAIN']=$m;
$m->ApplyChanges();
if ($m->visualizationType !== 1) throw new Exception("HTML-SDK Visualisierungstyp ist nicht 1");
$tile = $m->GetVisualizationTile();
if (strpos($tile,'data:image/webp;base64,')===false || strpos($tile,'SIMULATION')===false) {
    // SIMULATION appears as JS text, while injected payload has isLive:false.
    if (strpos($tile,'data:image/webp;base64,')===false || strpos($tile,'"isLive":false')===false)
        throw new Exception('Kachel nicht generiert');
}
$m->RequestAction('UI', json_encode(['action'=>'mode','data'=>['value'=>'now']]));
$m->RequestAction('UI', json_encode(['action'=>'pause','data'=>['value'=>true]]));
$m->RequestAction('UI', json_encode(['action'=>'trip','data'=>['tripKm'=>135,'desiredSoc'=>90,'reserveSoc'=>15,'departure'=>time()+3600]]));
$st = json_decode($m->attrs['State'],true);
if ($st['mode']!=='now' || $st['paused']!==true || $st['tripKm']!==135) throw new Exception('UI-Aktionen nicht gespeichert');
$ts=time();
$m->RequestAction('UI', json_encode(['action'=>'createManual','data'=>[
    'plugTs'=>$ts-4*3600,'unplugTs'=>$ts-2*3600, 'odoKm'=>22005, 'socPct'=>45,
    'moment'=>'plug','manualEnergyAcKwh'=>4.2,'endSocPct'=>58,
]]));
$st=json_decode($m->attrs['State'],true);
if (count($st['history'])!==1 || $st['history'][0]['energySource']!=='manual') throw new Exception('Nachtrag fehlgeschlagen');
if (empty($m->lastPayload) || $m->lastPayload['mode']!=='now' || $m->lastPayload['isLive']) throw new Exception('Visualisierungsupdate fehlgeschlagen');
echo "MOCK MODUL: Start, Tile, RequestAction, Manualhistorie, Simulation BESTANDEN\n";

// Separate dashboard communicates via the exported read-only controller method.
require __DIR__ . '/../Dashboard/module.php';
$dash = new LunaSmartChargeDashboard();
$dash->InstanceID=10002;
$dash->props['ControllerInstanceID']=10001;
$dash->ApplyChanges();
if ($dash->visualizationType !== 1 || empty($dash->lastPayload['mode'])) throw new Exception('Dashboard initialization failed');
$dashTile=$dash->GetVisualizationTile();
if (!str_contains($dashTile,'Energie-Dashboard') || !str_contains($dashTile, 'data:image/webp;base64,')) throw new Exception('Dashboard visualization tile missing');
$themeCss=LunaUiTheme::css(['UiAccentColor'=>'#FF9900','UiBorderPx'=>3,'UiRadiusPx'=>0]);
if (!str_contains($themeCss,'--accent:#FF9900') || !str_contains($themeCss,'--border-size:3px')) throw new Exception('Appearance settings not applied');
$controllerForm=json_decode($m->GetConfigurationForm(),true);
$dashboardForm=json_decode($dash->GetConfigurationForm(),true);
if (!is_array($controllerForm) || !is_array($dashboardForm)) throw new Exception('Configuration form JSON invalid');
if (!str_contains(json_encode($controllerForm),'SelectColor') || !str_contains(json_encode($dashboardForm),'SelectMedia')) throw new Exception('Appearance controls missing');
$blocked=false;
try { $dash->RequestAction('UI','{}'); }catch(InvalidArgumentException $e){$blocked=true;}
if (!$blocked) throw new Exception('Dashboard unexpectedly allows writes');
echo "MOCK DASHBOARD: separate module, payload, theme and read-only behavior PASSED\n";
