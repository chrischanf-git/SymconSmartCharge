<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/VehicleModel.php';
require_once __DIR__ . '/../libs/ChargePlanner.php';
require_once __DIR__ . '/../libs/ChargeRegulator.php';
function check(bool $cond, string $label): void { if (!$cond) throw new RuntimeException('FAIL: ' . $label); echo "OK  ".$label."\n"; }
function closeTo(float $a, float $b, float $eps=0.2): bool { return abs($a-$b)<=$eps; }
$ts = 1774260000;
$s = LunaVehicleModel::newSession($ts, 1.0, 'A');
LunaVehicleModel::saveEntry($s, ['odoKm'=>1000,'socPct'=>48,'moment'=>'plug'], $ts);
LunaVehicleModel::updateMeter($s, 2.0, 'A', $ts+600, 6.6);
check(closeTo($s['energyAcKwh'], 1.0, 0.001), 'AC-Energie steigt als Delta');
LunaVehicleModel::updateMeter($s, 2.0, 'A', $ts+660, 6.6);
LunaVehicleModel::updateMeter($s, 2.3, 'A', $ts+900, 6.6);
check(closeTo($s['energyAcKwh'], 1.3, 0.001), 'Gleicher Zaehlerstand erhoeht Zeitfenster, kein doppeltes Zaehlen');
$est = LunaVehicleModel::estimate($s, 27.5, ['consumption'=>14.5,'efficiency'=>.91]);
check($est['valid'] && closeTo($est['soc'], 52.3), 'SoC-Schaetzung aus AC-kWh und Wirkungsgrad');
LunaVehicleModel::saveEntry($s, ['odoKm'=>1000,'socPct'=>57,'moment'=>'now'], $ts+950);
$est = LunaVehicleModel::estimate($s, 27.5, ['consumption'=>14.5,'efficiency'=>.91]);
check(closeTo($est['soc'],57.0), 'Nachtraegliche Eingabe jetzt addiert fruehere kWh nicht erneut');
LunaVehicleModel::updateMeter($s, 2.8, 'A', $ts+1200, 6.6);
$est = LunaVehicleModel::estimate($s, 27.5, ['consumption'=>14.5,'efficiency'=>.91]);
check($est['soc']>57.5, 'Neue kWh nach Eingabe zaehlen');
$bad=LunaVehicleModel::newSession($ts, 4.0, 'A');
LunaVehicleModel::updateMeter($bad, 1.0, 'A', $ts+120, 6.6);
check(!$bad['meterValid'], 'Ungeklärter Zaehlerreset verwirft SoC-Schaetzung');
$reset=LunaVehicleModel::newSession($ts, 3.0, 'A');
LunaVehicleModel::updateMeter($reset, 0.2, 'B', $ts+300, 6.6);
check($reset['meterValid'] && closeTo($reset['energyAcKwh'],.2,.001),'Neuer Session-Identifier kann gueltigen Reset signalisieren');
LunaVehicleModel::saveEntry($reset, ['odoKm'=>1,'socPct'=>54,'moment'=>'plug'], $ts);
$reset['unplugTs']=$ts+400;$reset['actualEndSocPct']=65.0;
$cur=LunaVehicleModel::newSession($ts+60000,0.0,'X');
LunaVehicleModel::saveEntry($cur, ['odoKm'=>101,'socPct'=>15,'moment'=>'plug','externallyCharged'=>false], $ts+60000);
$learn=LunaVehicleModel::learn([$reset],$cur,27.5);
check($learn['drivingSamples']===1 && $learn['consumption']<14.5, 'Lernen aus dokumentierter Fahrt mit gemessenem End-SoC');
$cur['externallyCharged']=true;
check(LunaVehicleModel::learn([$reset],$cur,27.5)['drivingSamples']===0, 'Externe Ladungen werden nicht zum Verbrauchslernen genutzt');
$state=LunaVehicleModel::freshState();
$state['active']=$s;
$state['surplusPositiveTicks']=2;
$state['departure']=$ts+5*3600;
$cfg=['phases'=>3,'minA'=>6,'maxA'=>16,'vehicleAcW'=>6600,'capacityKwh'=>27.5,'batteryReservePct'=>35,'exportMarginW'=>250,'maxGridImportW'=>12000];
$d=['connected'=>true,'sensorsFresh'=>true,'chargerEnable'=>true,'batSoc'=>74.0,'batW'=>-800.0,'pvW'=>9100.0,'wallboxW'=>5500.0,'gridW'=>-700.0];
$r=LunaChargeRegulator::decide($state,$d,$cfg,16.0,$ts);
check($r['amps']===8,'PV-Ueberschuss mit 3 Phasen wird auf 8 A begrenzt');
$d['pvW']=0;
check(LunaChargeRegulator::decide($state,$d,$cfg,16.0,$ts)['amps']===0,'Ohne aktuelle PV-Erzeugung trotz Export kein Smartladen');
$d['pvW']=9100;
$d['batSoc']=20;
check(LunaChargeRegulator::decide($state,$d,$cfg,16.0,$ts)['amps']===0,'Hausspeicher unter Reserve stoppt Smartladung');
$d['batSoc']=74;
$state['mode']='now';
check(LunaChargeRegulator::decide($state,$d,$cfg,16.0,$ts)['amps']===9,'Jetztmodus begrenzt 6,6 kW Fahrzeuglader auf 9 A 3-phasig');
$d['sensorsFresh']=false;
check(LunaChargeRegulator::decide($state,$d,$cfg,16.0,$ts)['amps']===0,'Ohne aktuelle Eingangswerte keine Freigabe');
$forecast=json_encode(['slots'=>[['ts'=>$ts,'pvW'=>8000],['ts'=>$ts+3600,'pvW'=>6000],['ts'=>$ts+7200,'pvW'=>0]]]);
$slots=LunaChargePlanner::parseForecast($forecast,$ts);
$estimate=LunaChargePlanner::expectedAcKwh($slots,$ts,$ts+7200,2000,6.6);
check($estimate!==null && $estimate>9,'Zeitaufgeloeste PV-Prognose wird fuer die Ladeplanung verwendet');
// Nachtraegliche Erfassung bisher unbekannter Ladesitzungen und Energie-Override.
$manual=LunaVehicleModel::createManualSession([
    'plugTs'=>$ts+120, 'unplugTs'=>$ts+3720,
    'odoKm'=>20250, 'socPct'=>34, 'moment'=>'plug', 'manualEnergyAcKwh'=>5.6,
    'endSocPct'=>51.0, 'externallyCharged'=>false,
], $ts+8000);
check(($manual['energySource']??'')==='manual' && $manual['meterValid'] && closeTo($manual['energyAcKwh'],5.6,.001), 'Historische Ladesitzung inklusive AC-kWh kann manuell erfasst werden');
LunaVehicleModel::overrideHistoricalEnergy($manual,6.2);
check(closeTo($manual['energyAcKwh'],6.2,.001) && closeTo($manual['meterOriginalAcKwh'],5.6,.001), 'Nachtraegliche Energiekorrektur mit Originalwert');
check(LunaVehicleModel::learn([$manual],null,27.5)['efficiencySamples']===0, 'Manuell korrigierte Energiewerte werden nicht zum Wirkungsgrad-Lernen verwendet');
$rejected=false;
try {LunaVehicleModel::overrideHistoricalEnergy($manual,-12);} catch(InvalidArgumentException $e){$rejected=true;}
check($rejected,'Manuelle unplausible Energiewerte werden abgewiesen');
// Die PV-Prognose beeinflusst konkret den zeitlichen Notlade-Puffer.
$state['mode']='smart';$state['paused']=false;
$d['sensorsFresh']=true;$d['batSoc']=80;
$d['gridW']=-2000;$d['wallboxW']=0;
$state['departure']=$ts+2*3600;
$withSun=LunaChargeRegulator::decide($state,$d,$cfg,30.0,$ts);
$withoutSun=LunaChargeRegulator::decide($state,$d,$cfg,0.0,$ts);
check($withSun['urgent']===false && $withoutSun['urgent']===true, 'PV-Prognose veraendert rechtzeitig den Abfahrtslade-Puffer');
echo "ALLE TESTS BESTANDEN\n";
