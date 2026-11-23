const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const code = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/cookies.js'), 'utf8');
const id = 'G-TEST123456';
const consentKey = 'haleef_analytics_consent_v1';
const consentRecord = choice => JSON.stringify({property:id,choice,until:Date.now()+300000});

function visit({stored, path = '/contact', result} = {}) {
    const requests = [];
    const cookieWrites = [];
    const storage = new Map(stored ? [[consentKey,stored]] : []);
    const buttons = {};
    class Element {
        constructor(dataset = {}) { this.dataset = dataset; }
        closest() { return this; }
        addEventListener(type, fn) { this[type] = fn; }
    }
    for (const choice of ['accepted','rejected']) buttons[choice] = new Element({cookieChoice:choice});
    const notice = new Element({analyticsId:id,analyticsPath:path});
    const window = {
        localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},
        addEventListener(type, fn) { this[type] = fn; }
    };
    const document = {
        querySelector(selector) {
            if (selector === '[data-cookie-notice]') return notice;
            if (selector === '[data-analytics-result]' && result) return new Element({analyticsResult:result});
            return null;
        },
        querySelectorAll:()=>Object.values(buttons),
        addEventListener(type, fn) { this[type] = fn; },
        createElement:()=>({}),
        head:{append:node=>requests.push(node)},
        get cookie() { return '_ga=123; haleef_session=keep'; },
        set cookie(value) { cookieWrites.push(value); }
    };
    const location = new URL('https://www.haleefglobal.com/contact?email=private%40example.test&utm_source=brevo&utm_medium=email');
    vm.runInNewContext(code,{window,document,location,URL,Element});
    const events = () => Array.from(window.dataLayer || [],call=>Array.from(call)).filter(call=>call[0] === 'event').map(call=>call[1]);
    return {window,document,storage,buttons,cookieWrites,requests,notice,Element,events};
}

const fresh = visit({result:'contact_enquiry'});
assert.equal(fresh.notice.hidden,false);
assert.equal(fresh.requests.length,0);
fresh.buttons.rejected.click();
assert.equal(fresh.requests.length,0);
assert.equal(JSON.parse(fresh.storage.get(consentKey)).choice,'rejected');
assert.equal(visit({stored:consentRecord('rejected')}).requests.length,0);
assert.equal(visit({stored:JSON.stringify({choice:'accepted',until:1})}).requests.length,0);
assert.equal(visit({stored:consentRecord('accepted'),path:''}).requests.length,0);
fresh.buttons.accepted.click();
assert.equal(fresh.requests.length,1);
assert.equal(fresh.requests[0].referrerPolicy,'no-referrer');
const cfg = Array.from(fresh.window.dataLayer).find(x=>x[0]==='config')[2];
assert.equal(cfg.page_location,'https://www.haleefglobal.com/contact');
assert.equal(cfg.page_referrer,'');
assert.equal(cfg.campaign_source,'brevo');
assert.equal(cfg.allow_google_signals,false);
assert.equal(cfg.send_page_view,false);
assert.ok(!JSON.stringify(Array.from(fresh.window.dataLayer)).includes('private'));
fresh.requests[0].onload();
assert.deepEqual(fresh.events(),['page_view']);
fresh.document.click({target:new fresh.Element({analyticsClick:'begin_checkout'})});
assert.deepEqual(fresh.events(),['page_view','begin_checkout']);
fresh.buttons.rejected.click();
assert.equal(fresh.window['ga-disable-'+id],true);
assert.ok(fresh.cookieWrites.some(x=>x.startsWith('_ga=;')));
assert.ok(!fresh.cookieWrites.some(x=>x.startsWith('haleef_session=')));
fresh.document.click({target:new fresh.Element({analyticsClick:'whatsapp_click'})});
assert.deepEqual(fresh.events(),['page_view','begin_checkout']);

const returning = visit({stored:consentRecord('accepted'),result:'agenda_request'});
returning.requests[0].onload();
assert.deepEqual(returning.events(),['page_view','agenda_request']);
returning.storage.set(consentKey,consentRecord('rejected'));
returning.window.storage({key:consentKey});
assert.equal(returning.window['ga-disable-'+id],true);
console.log('Analytics consent and event privacy checks passed.');
