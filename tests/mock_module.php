<?php
declare(strict_types=1);
/** Stubtest OHNE echte Symcon-/Charge-Amps-Hardware. */
const INSTANCE_VISUALIZATION_TYPE_HTML = 1;
$GLOBALS['MOCK_VALUES'] = [];
$GLOBALS['MOCK_IDENTS'] = [];
$GLOBALS['MOCK_NEXT_ID'] = 40000;
function IPS_SemaphoreEnter($key, $wait) { return true; }
function IPS_SemaphoreLeave($key) { return true; }
function IPS_ObjectExists($id) { return false; }
function IPS_VariableExists($id) { return isset($GLOBALS['MOCK_VALUES'][$id]); }
function GetValue($id) { return $GLOBALS['MOCK_VALUES'][$id] ?? null; }
function IPS_LogMessage($who,$txt) { /* noop */ }
class IPSModule {
    public int $InstanceID = 10001;
    public array $props = [], $attrs=[], $variableIds=[];
    public $lastPayload;
    public function __construct() { $this->Create(); }
    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterPropertyInteger($n,$v) { $this->props[$n]=$v; }
    public function RegisterPropertyBoolean($n,$v) { $this->props[$n]=$v; }
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
    public function ReadAttributeString($n){return (string)$this->attrs[$n];}
    public function WriteAttributeString($n,$v){$this->attrs[$n]=$v;}
    public function SetValue($n,$v){$GLOBALS['MOCK_VALUES'][$this->variableIds[$n]]=$v;}
    public function EnableAction($n){}
    public function SetVisualizationType($t){}
    public function UpdateVisualizationValue($data){$this->lastPayload=$data;}
    public function SendDebug($what,$data,$level){}
}
require __DIR__ . '/../SmartCharge/module.php';
$m = new LunaSmartCharge();
$m->ApplyChanges();
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
