from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).resolve().parents[1]
html=(root/'previews/SmartCharge_Mobil_Vorschau.html').read_text(encoding='utf-8')
p=root/'previews'
with sync_playwright() as w:
 b=w.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=b.new_page(viewport={'width':414,'height':900},device_scale_factor=1.4, is_mobile=True,has_touch=True,locale='de-DE')
 errors=[];page.on('pageerror', lambda e:errors.append(str(e)))
 page.set_content(html,wait_until='domcontentloaded');page.wait_for_timeout(250)
 assert page.locator('#car-status').inner_text().strip()=='● Angesteckt'
 assert page.locator('#smart-mode').get_attribute('class').find('active')>=0
 page.screenshot(path=str(p/'SmartCharge_Mobil_Vorschau.png'),full_page=False)
 # Test primary entry, skip and correction
 page.locator('#open-entry-top').click()
 assert 'Fahrzeugdaten' in page.locator('#modal-title').inner_text()
 assert page.locator('#skip-entry').is_visible()
 page.screenshot(path=str(p/'SmartCharge_Fahrtdaten_Vorschau.png'),full_page=False)
 page.locator('#skip-entry').click()
 assert not page.locator('#modal').is_visible()
 page.locator('#open-entry-top').click()
 page.locator('#in-soc').fill('62')
 page.locator('#in-odo').fill('12546')
 page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
 assert not page.locator('#modal').is_visible()
 assert float(page.locator('#soc-pct').inner_text().replace(',','.'))>62
 # trip editor then mode
 page.locator('#edit-trip').click()
 assert page.locator('#in-odo').is_hidden()
 page.locator('#in-trip').fill('140')
 page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
 assert page.locator('#route').inner_text()=='140 km'
 page.locator('#now-mode').click()
 assert 'active' in page.locator('#now-mode').get_attribute('class')
 # historical correction, manually amended energy
 page.locator('#history .history-action').first.click()
 assert 'nachträglich' in page.locator('#modal-title').inner_text()
 assert page.locator('#manual-energy-row').is_visible()
 page.locator('#in-energy').fill('9.12')
 page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
 assert '9,12 kWh' in page.locator('#history .history-sub').first.inner_text()
 # a brand new missing historical session
 page.locator('#create-manual').click()
 assert 'Vergangene Ladung' in page.locator('#modal-title').inner_text()
 assert page.locator('#manual-when-row').is_visible()
 page.screenshot(path=str(p/'SmartCharge_Manuelles_Nachtragen_Vorschau.png'),full_page=False)
 page.locator('#in-odo').fill('12321')
 page.locator('#in-soc').fill('33')
 page.locator('#in-energy').fill('5.4')
 page.locator('#in-end-soc').fill('51')
 page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
 assert not page.locator('#modal').is_visible(), 'Submit manually missed'+page.locator('#modal').get_attribute('class')
 assert 'manuell' in page.locator('#history').inner_text()
 assert not errors, errors
 print('Mobile UI: all actions including skip, editing, adding charging sessions PASSED')
 page.close()
 # Desktop screenshot
 page=b.new_page(viewport={'width':1430,'height':920},device_scale_factor=1.0,locale='de-DE')
 errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.set_content(html,wait_until='domcontentloaded');page.wait_for_timeout(200)
 page.screenshot(path=str(p/'SmartCharge_Desktop_Vorschau.png'),full_page=True)
 assert not errors,errors
 print('Desktop UI PASSED')
 b.close()
