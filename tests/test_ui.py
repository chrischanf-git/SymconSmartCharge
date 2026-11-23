from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).resolve().parents[1]
p=root/'docs/previews'
with sync_playwright() as w:
    b=w.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=b.new_page(viewport={'width':390,'height':844},device_scale_factor=1,is_mobile=True,has_touch=True,locale='de-DE')
    errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content((p/'SmartCharge_Mobil_Vorschau.html').read_text(encoding='utf-8'),wait_until='load')
    assert page.locator('#screen-start').is_visible()
    assert page.locator('#screen-details').is_hidden()
    assert page.locator('#screen-history').is_hidden()
    assert page.locator('#car-status').inner_text().strip()=='● Angesteckt'
    assert page.locator('#home-manual').is_visible()
    assert 'active' in page.locator('#smart-mode').get_attribute('class')
    page.screenshot(path=str(p/'SmartCharge_Mobil_Vorschau.png'),full_page=True)
    # Data entry, skip and later correction
    page.locator('#open-entry-top').click()
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
    page.locator('#edit-trip').click()
    assert page.locator('#in-odo').is_hidden()
    page.locator('#in-trip').fill('140')
    page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
    assert '140' in page.locator('#route').inner_text()
    page.locator('#now-mode').click()
    assert 'active' in page.locator('#now-mode').get_attribute('class')
    # Details only on deeper screen
    page.locator('.tab-link[data-page="details"]').click()
    assert page.locator('#screen-start').is_hidden()
    assert page.locator('#screen-details').is_visible()
    assert 'PV aktuell' in page.locator('#screen-details').inner_text()
    page.screenshot(path=str(p/'SmartCharge_Mobil_Details_Vorschau.png'),full_page=True)
    # Historic correction and manual additions from home page and history
    page.locator('.tab-link[data-page="history"]').click()
    assert page.locator('#screen-history').is_visible()
    page.locator('#history .history-action').first.click()
    assert page.locator('#manual-energy-row').is_visible()
    page.locator('#in-energy').fill('9.12')
    page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
    assert '9,12 kWh' in page.locator('#history .history-sub').first.inner_text()
    page.locator('.tab-link[data-page="start"]').click()
    page.locator('#home-manual').click()
    assert page.locator('#manual-when-row').is_visible()
    page.screenshot(path=str(p/'SmartCharge_Manuelles_Nachtragen_Vorschau.png'),full_page=False)
    page.locator('#in-odo').fill('12321')
    page.locator('#in-soc').fill('33')
    page.locator('#in-energy').fill('5.4')
    page.locator('#in-end-soc').fill('51')
    page.locator('#entry-form').evaluate('(el)=>el.requestSubmit()')
    assert not page.locator('#modal').is_visible()
    page.locator('.tab-link[data-page="history"]').click()
    assert 'manuell' in page.locator('#history').inner_text()
    assert not errors,errors
    print('PASS: Smartphone start-only contents, deep views, mode, skip, later entry, history, manual charge')
    page.close()

    page=b.new_page(viewport={'width':1380,'height':960},locale='de-DE')
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.set_content((p/'SmartCharge_Desktop_Vorschau.html').read_text(encoding='utf-8'),wait_until='load')
    assert '9,1' in page.locator('#pv').inner_text()
    assert '8' in page.locator('#target-current').inner_text()
    assert page.locator('#history').locator('tr').count()>=2
    assert page.locator('button').count()==0, 'Dashboard must not send commands'
    page.screenshot(path=str(p/'SmartCharge_Desktop_Vorschau.png'),full_page=True)
    assert not errors,errors
    print('PASS: independent read-only dashboard and metrics')
    page.close()
    b.close()
