import base64, pathlib, json, runpy
root=pathlib.Path(__file__).resolve().parents[1]
# Retain the vetted illustrative fixture from v1.1.4 (no real Symcon/EV account data).
fixture=runpy.run_path(str(root/'tests/fixture_v114.py'))['sample']
fixture['isDemo']=True
img=base64.b64encode((root/'imgs/renault-twingo-2026.webp').read_bytes()).decode('ascii')
photo='data:image/webp;base64,'+img
theme=':root{--accent:#47E19C;--mint:#47E19C;--bg:#0B100F;--surface:#151D1A;--surface2:#1A261E;--text:#EFF7F1;--muted:#91A59B;--line:#304137;--radius:20px;--border-size:1px;--card-gap:13px;--photo-height:220px;--photo-fit:cover;--photo-pos:center;--accent-ink:#09251A}'
previews=root/'docs/previews';previews.mkdir(parents=True,exist_ok=True)
for source,output,hash_value in [
  ('SmartCharge/module.html','SmartCharge_Mobil_Vorschau.html',''),
  ('SmartCharge/module.html','SmartCharge_Mobil_Details_Vorschau.html','#details'),
  ('SmartCharge/module.html','SmartCharge_Mobil_Ladungen_Vorschau.html','#history'),
  ('Dashboard/module.html','SmartCharge_Desktop_Vorschau.html',''),
]:
 page=(root/source).read_text()
 page=page.replace('__LUNA_PHOTO__',photo).replace('__LUNA_INITIAL__',json.dumps(fixture,ensure_ascii=False,separators=(',',':'))).replace('__LUNA_DEMO__','true').replace('__LUNA_THEME_CSS__',theme)
 page='<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Luna SmartCharge Vorschau</title></head><body>'+page+'</body></html>'
 (previews/output).write_text(page,encoding='utf-8')
 print('Preview:',output,round(len(page)/1024),'KB')

studio=r'''<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartCharge Designwerkstatt</title><style>
*{box-sizing:border-box}body{margin:0;background:#0c120f;color:#f3fff7;font-family:system-ui,Arial,sans-serif}header{padding:22px 26px;border-bottom:1px solid #33443b}header h1{margin:3px 0 2px;font-size:25px}header p{margin:0;color:#a0b8a9;font-size:13px}.workspace{display:grid;grid-template-columns:260px 1fr;gap:20px;padding:18px;align-items:start}.tools{background:#19271f;border-radius:17px;border:1px solid #334739;padding:18px;display:grid;gap:13px;position:sticky;top:16px}.tools h2{font-size:16px;margin:0}.tools label{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px;color:#d7eae0}.tools input[type=color]{width:47px;height:32px;border:0;background:transparent;cursor:pointer}.tools input[type=range]{width:110px;accent-color:#47E19C}.tools select{background:#102017;color:#fff;border:1px solid #516957;border-radius:8px;padding:6px}.tools input[type=file]{width:100%;font-size:11px}.tools .meta{font-size:11px;color:#a8bcaf}.views{display:flex;align-items:flex-start;gap:17px;min-width:0;overflow-x:auto}.device{flex:0 0 auto}.device h2{font-size:13px;color:#8ba795;font-weight:650;margin:5px 2px 10px}.device iframe{border:1px solid #445d4d;border-radius:16px;background:#0b100f;display:block;box-shadow:0 18px 45px #0007}.phone iframe{width:400px;height:750px}.desktop iframe{width:800px;height:750px}.note{font-size:12px;color:#aabbb0;margin-top:12px}@media(max-width:900px){.workspace{grid-template-columns:1fr}.tools{position:static;grid-template-columns:1fr 1fr}.views{flex-direction:column}.desktop iframe{width:min(800px,calc(100vw - 36px))}.phone iframe{width:min(400px,calc(100vw - 36px))}}@media(max-width:560px){.tools{grid-template-columns:1fr}}
</style></head><body><header><div style="font-size:11px;color:#47e19c;letter-spacing:.15em">LUNA SMARTCHARGE 1.2</div><h1>Designwerkstatt</h1><p>Live-Vorschau: Smartphone und eigenstaendiges Energie-Dashboard mit Beispieldaten. Keine Verbindung zur Wallbox.</p></header><div class="workspace"><div class="tools"><h2>Optik aendern</h2>
<label>Akzent <input id="accent" type="color" value="#47e19c"></label><label>Hintergrund <input id="bg" type="color" value="#0b100f"></label><label>Karten <input id="surface" type="color" value="#151d1a"></label><label>Rahmen <input id="line" type="color" value="#304137"></label><label>Text <input id="text" type="color" value="#eff7f1"></label><label>Rundungen <input id="radius" type="range" min="0" max="38" value="20"></label><label>Rahmenbreite <input id="width" type="range" min="0" max="4" value="1"></label><label>Abstand <input id="gap" type="range" min="6" max="30" value="13"></label><label>Bildhoehe <input id="height" type="range" min="115" max="400" value="220"></label><label>Bildausschnitt <select id="fit"><option value="cover">Fuellen</option><option value="contain">Ganzes Bild</option></select></label><label>Bildposition <select id="pos"><option value="center">Mitte</option><option value="top">Oben</option><option value="bottom">Unten</option></select></label><label>Bild sichtbar <input id="show" type="checkbox" checked></label><label>Eigenes Foto nur fuer diese Vorschau:</label><input id="file" type="file" accept="image/jpeg,image/png,image/webp"><button id="reset" style="background:#47e19c;border:0;border-radius:10px;padding:11px;font-weight:800">Zuruecksetzen</button><div class="meta">Die dauerhaften Einstellungen erfolgen in den Symcon-Instanzkonfigurationen. Nach dem Anwenden die Kachel neu laden.</div></div>
<div><div class="views"><div class="device phone"><h2>Smartphone / Startseite</h2><iframe id="phone" src="SmartCharge_Mobil_Vorschau.html" title="Smartphone"></iframe><p class="note">Start, Details und Ladungen sind unten umschaltbar.</p></div><div class="device desktop"><h2>Separates Dashboard</h2><iframe id="dashboard" src="SmartCharge_Desktop_Vorschau.html" title="Dashboard"></iframe><p class="note">Eigenstaendige read-only Symcon-Kachel.</p></div></div></div></div>
<script>(function(){
const ids=['accent','bg','surface','line','text','radius','width','gap','height','fit','pos','show'];const el=id=>document.getElementById(id);
function apply(){['phone','dashboard'].forEach(which=>{const iframe=el(which);try{const doc=iframe.contentDocument;if(!doc)return;const r=doc.documentElement.style;const vals={'--accent':el('accent').value,'--mint':el('accent').value,'--bg':el('bg').value,'--surface':el('surface').value,'--line':el('line').value,'--text':el('text').value,'--radius':el('radius').value+'px','--border-size':el('width').value+'px','--card-gap':el('gap').value+'px','--photo-height':el('height').value+'px','--photo-fit':el('fit').value,'--photo-pos':el('pos').value};Object.entries(vals).forEach(([k,v])=>r.setProperty(k,v));doc.querySelectorAll('.photo').forEach(n=>n.style.display=el('show').checked?'':'none');doc.querySelectorAll('.car-card').forEach(n=>n.style.gridTemplateColumns=el('show').checked?'':'1fr');let hex=el('accent').value, rgb=[1,3,5].map(p=>parseInt(hex.substr(p,2),16));r.setProperty('--accent-ink',rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722>=155?'#0b1510':'#ffffff')}catch(e){}})}
ids.forEach(id=>{el(id).addEventListener('input',apply);el(id).addEventListener('change',apply)});
['phone','dashboard'].forEach(id=>el(id).addEventListener('load',apply));
el('file').addEventListener('change',e=>{const file=e.target.files[0];if(!file||file.size>2000000||!/^image\/(webp|png|jpeg)$/.test(file.type)){alert('Bitte JPG, PNG oder WebP bis 2 MB waehlen');return}const reader=new FileReader();reader.onload=()=>{['phone','dashboard'].forEach(id=>{const i=el(id);i.contentDocument?.querySelectorAll('.photo img').forEach(img=>img.src=reader.result)})};reader.readAsDataURL(file)});
el('reset').addEventListener('click',()=>location.reload());
})();</script></body></html>'''
# Embed both previews directly so the design studio works as one standalone HTML file.
from html import escape
studio=studio.replace('src="SmartCharge_Mobil_Vorschau.html"',
    'srcdoc="'+escape((previews/'SmartCharge_Mobil_Vorschau.html').read_text(encoding='utf-8'),quote=True)+'"')
studio=studio.replace('src="SmartCharge_Desktop_Vorschau.html"',
    'srcdoc="'+escape((previews/'SmartCharge_Desktop_Vorschau.html').read_text(encoding='utf-8'),quote=True)+'"')
(previews/'SmartCharge_Designwerkstatt.html').write_text(studio,encoding='utf-8')
print('Design studio:',len(studio))
