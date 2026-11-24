(() => {
    const notice = document.querySelector('[data-cookie-notice]');
    if (!notice) return;

    const controls = document.querySelector('[data-cookie-controls]');
    const minimise = notice.querySelector('[data-cookie-minimise]');
    const launcher = document.querySelector('[data-cookie-expand]');
    const status = document.querySelector('[data-cookie-status]');
    const lifetime = 180 * 24 * 60 * 60 * 1000;
    const collapsedKey = 'haleef_cookie_minimised_v1';
    // Private browsing may refuse persistent storage. Try the session store too.
    function access(method, ...args) {
        if (method === 'setItem') {
            try {
                window.localStorage.setItem(...args);
                try { window.sessionStorage.removeItem(args[0]); } catch {}
                return;
            } catch {}
            // If writing failed (e.g. quota), don't let an older choice override the new one.
            try { window.localStorage.removeItem(args[0]); } catch {}
            try { window.sessionStorage.setItem(...args); } catch {}
            return;
        }
        for (const store of ['sessionStorage', 'localStorage']) {
            try {
                const result = window[store][method](...args);
                if (method === 'getItem' && result != null) return result;
            } catch {}
        }
        return null;
    }
    const isMinimised = () => Number(access('getItem', collapsedKey)) > Date.now();
    function showNotice(show, moveFocus = false) {
        notice.hidden = !show;
        launcher.hidden = show || Boolean(controls);
        launcher.setAttribute('aria-expanded', String(show));
        if (moveFocus) (show ? minimise : launcher).focus({ preventScroll: true });
    }
    minimise.addEventListener('click', () => {
        access('setItem', collapsedKey, String(Date.now() + lifetime));
        showNotice(false, !controls);
    });
    launcher.addEventListener('click', () => {
        access('removeItem', collapsedKey);
        showNotice(true, true);
    });

    const measurementId = notice.dataset.analyticsId;
    if (/^G-[A-Z0-9]{4,20}$/.test(measurementId || '')) {
        const publicPath = notice.dataset.analyticsPath;
        const key = 'haleef_analytics_consent_v1';
        const disabled = 'ga-disable-' + measurementId;
        const choices = ['accepted', 'rejected'];
        const knownEvents = ['contact_enquiry', 'agenda_request', 'begin_checkout', 'whatsapp_click'];
        let consent = null;
        let tag = null;
        let loaded = false;
        let viewed = false;
        window[disabled] = true;

        function savedChoice() {
            try {
                const item = JSON.parse(access('getItem', key) || 'null');
                return item?.property === measurementId && item.until > Date.now() && choices.includes(item.choice) ? item.choice : null;
            } catch { return null; }
        }

        function eraseAnalyticsCookies() {
            const names = document.cookie.split(';').map(item => item.trim().split('=')[0]);
            for (const name of names.filter(name => /^_ga(?:_[A-Za-z0-9]+)?$/.test(name))) {
                const expired = name + '=; Max-Age=0; Path=/; SameSite=Lax';
                document.cookie = expired;
                document.cookie = expired + '; Domain=' + location.hostname;
                if (location.hostname.endsWith('.haleefglobal.com')) document.cookie = expired + '; Domain=haleefglobal.com';
            }
        }

        function event(name) {
            if (consent !== 'accepted' || !publicPath || !loaded || window[disabled]) return;
            window.gtag('event', name, { send_to: measurementId });
        }

        function pageView() {
            if (viewed || consent !== 'accepted' || !loaded || window[disabled]) return;
            viewed = true;
            event('page_view');
        }

        function startAnalytics() {
            if (!publicPath) return;
            window[disabled] = false;
            if (tag) { pageView(); return; }
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            const adsDenied = { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' };
            window.gtag('consent', 'default', { ...adsDenied, analytics_storage: 'denied' });
            window.gtag('consent', 'update', { ...adsDenied, analytics_storage: 'granted' });
            window.gtag('set', 'ads_data_redaction', true);
            window.gtag('set', 'url_passthrough', false);
            window.gtag('js', new Date());

            // Only fixed, allowlisted campaign labels and a public path. Never use the raw URL or referrer.
            const params = new URL(location.href).searchParams;
            const source = (params.get('utm_source') || '').toLowerCase();
            const medium = (params.get('utm_medium') || '').toLowerCase();
            const sources = ['newsletter', 'brevo', 'linkedin', 'google', 'facebook', 'instagram'];
            const media = ['email', 'social', 'organic', 'referral'];
            const config = {
                send_page_view: false, allow_google_signals: false, allow_ad_personalization_signals: false,
                page_location: location.origin + publicPath, page_referrer: '', page_title: 'Haleef ' + publicPath,
                cookie_domain: location.hostname, cookie_expires: lifetime / 1000, cookie_update: false
            };
            if (sources.includes(source) && media.includes(medium)) {
                config.campaign_source = source;
                config.campaign_medium = medium;
            }
            window.gtag('config', measurementId, config);
            tag = document.createElement('script');
            tag.async = true;
            tag.referrerPolicy = 'no-referrer';
            tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
            tag.onload = () => {
                loaded = true;
                sendInitialEvents();
            };
            document.head.append(tag);
        }

        // Only report results generated by the server and rendered while already opted in.
        let resultEvent = savedChoice() === 'accepted' ? document.querySelector('[data-analytics-result]')?.dataset.analyticsResult : null;
        function sendInitialEvents() {
            pageView();
            if (knownEvents.includes(resultEvent)) event(resultEvent);
            resultEvent = null;
        }

        function apply(choice) {
            consent = choice;
            showNotice(!controls && !choice && !isMinimised());
            if (status) status.textContent = choice === 'accepted' ? 'Analytics on.'
                : choice === 'rejected' ? 'Analytics off — rejected.' : 'Analytics off — no choice saved.';
            if (controls) {
                for (const button of controls.querySelectorAll('[data-cookie-choice]')) {
                    button.setAttribute('aria-pressed', String(choice === button.dataset.cookieChoice));
                }
            }
            if (choice === 'accepted') startAnalytics();
            else {
                window[disabled] = true;
                resultEvent = null;
                eraseAnalyticsCookies();
            }
        }

        for (const button of document.querySelectorAll('[data-cookie-choice]')) {
            button.disabled = false;
            button.addEventListener('click', () => {
                const choice = button.dataset.cookieChoice;
                if (!choices.includes(choice)) return;
                access('setItem', key, JSON.stringify({ property: measurementId, choice, until: Date.now() + lifetime }));
                apply(choice);
                if (!controls) launcher.focus({ preventScroll: true });
            });
        }
        document.addEventListener('click', eventInfo => {
            if (!(eventInfo.target instanceof Element)) return;
            const name = eventInfo.target.closest('[data-analytics-click]')?.dataset.analyticsClick;
            if (knownEvents.includes(name)) event(name);
        });
        window.addEventListener('storage', eventInfo => {
            if (eventInfo.key === key || eventInfo.key === collapsedKey || eventInfo.key === null) apply(savedChoice());
        });
        window.addEventListener('pageshow', eventInfo => {
            if (eventInfo.persisted) apply(savedChoice());
        });
        apply(savedChoice());
        return;
    }

    // A versioned dismissal only: this is not opt-in to tracking or any vendor.
    const storageKey = 'haleef_cookie_notice_v1';
    const acknowledge = notice.querySelector('[data-cookie-dismiss]');
    const dismissed = Number(access('getItem', storageKey)) > Date.now();
    showNotice(!controls && !dismissed && !isMinimised());
    acknowledge.addEventListener('click', () => {
        access('setItem', storageKey, String(Date.now() + lifetime));
        showNotice(false, !controls);
        if (status) status.textContent = 'Notice dismissed. Only essential cookies are in use.';
    });

    const reset = document.querySelector('[data-cookie-reset]');
    if (reset) {
        reset.hidden = false;
        reset.addEventListener('click', () => {
            access('removeItem', storageKey);
            access('removeItem', collapsedKey);
            showNotice(true);
            if (status) status.textContent = 'The saved dismissal was cleared in this browser.';
            acknowledge.focus();
        });
    }
})();
