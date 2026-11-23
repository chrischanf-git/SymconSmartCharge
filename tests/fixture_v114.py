import json,base64,time,datetime,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
html=(root/'SmartCharge/module.html').read_text()
img=base64.b64encode((root/'imgs/renault-twingo-2026.webp').read_bytes()).decode('ascii')
now=int(time.time());d=datetime.datetime.fromtimestamp(now);departure=(d+datetime.timedelta(days=1)).replace(hour=7,minute=30,second=0,microsecond=0)
if int(departure.timestamp())<now: departure+=datetime.timedelta(days=1)
sample={
 'now':now,'isDemo':True,'isLive':False,'connected':True,'chargerFresh':True,
 'sensorsFresh':True,'chargerAge':18,'connectorStatus':'charging','chargerEnabled':True,
 'mode':'smart','paused':False,'entryRequired':False,
 'active':{'id':'20261122-0810-demo','plugTs':now-4800,'energyAcKwh':3.9,'meterValid':True,
           'meterIssue':'','odoKm':12540,'socAnchorPct':48,'socAnchorMoment':'plug',
           'anchorEnergyKwh':0,'entrySkipped':False},
 'history':[
 {'id':'20261120-demo','plugTs':now-2*86400,'unplugTs':now-2*86400+6000,'energyAcKwh':8.3,'meterValid':True,'odoKm':12415,'socAnchorPct':42,'socAnchorMoment':'plug','actualEndSocPct':69,'externallyCharged':False,'entrySkipped':False},
 {'id':'20261118-demo','plugTs':now-4*86400,'unplugTs':now-4*86400+2800,'energyAcKwh':5.1,'meterValid':True,'odoKm':12332,'socAnchorPct':53,'socAnchorMoment':'plug','actualEndSocPct':70,'externallyCharged':False,'entrySkipped':False}
 ],
 'estimate':{'valid':True,'soc':60.9,'rangeKm':115,'gainKm':24,'addedAcKwh':3.9,'quality':'Nur Schaetzung'},
 'learned':{'consumption':14.5,'efficiency':0.91,'drivingSamples':0,'efficiencySamples':2},
 'targetSoc':80,'targetCurrent':8,'reason':'PV-Ueberschuss verfuegbar, Netz-/Speicherbilanz beruecksichtigt',
 'urgent':False,'routeImpossible':False,'neededAcKwh':6.35,'forecastAcKwh':16.3,
 'tripKm':85,'departure':int(departure.timestamp()),'desiredSoc':80,'reserveSoc':20,'houseBatteryReservePct':35,
 'pvW':9100,'gridW':-700,'batteryW':-800,'batterySoc':74,'houseW':2900,'heatPumpW':2000,
 'wallboxW':5500,'chargerCurrent':8,'lastCommandError':'','learningCapacityKwh':27.5
}
html='<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Luna SmartCharge – Vorschau</title></head><body>'+html+'</body></html>'
html=html.replace('__LUNA_PHOTO__' ,'data:image/webp;base64,'+img).replace('__LUNA_INITIAL__',json.dumps(sample,ensure_ascii=False,separators=(',',':'))).replace('__LUNA_DEMO__','true')
for name in []:
 (root/'docs/previews'/name).write_text(html,encoding='utf-8')
print('Generated HTML preview', len(html))
