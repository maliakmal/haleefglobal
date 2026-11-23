(() => {
    const notice = document.querySelector('[data-cookie-notice]');
    if (!notice) return;

    // A versioned dismissal only: this is not opt-in to tracking or any vendor.
    const storageKey = 'haleef_cookie_notice_v1';
    const lifetime = 180 * 24 * 60 * 60 * 1000;
    const status = document.querySelector('[data-cookie-status]');
    const acknowledge = notice.querySelector('[data-cookie-dismiss]');
    let dismissed = false;
    try {
        dismissed = Number(window.localStorage.getItem(storageKey)) > Date.now();
        if (!dismissed) window.localStorage.removeItem(storageKey);
    } catch {
        // Privacy modes that block storage can still use and dismiss the notice.
    }
    notice.hidden = dismissed;
    acknowledge.addEventListener('click', () => {
        try {
            window.localStorage.setItem(storageKey, String(Date.now() + lifetime));
        } catch {}
        notice.hidden = true;
        if (status) status.textContent = 'Notice dismissed. Only essential cookies are in use.';
    });

    const reset = document.querySelector('[data-cookie-reset]');
    if (reset) {
        reset.hidden = false;
        reset.addEventListener('click', () => {
            try {
                window.localStorage.removeItem(storageKey);
            } catch {}
            notice.hidden = false;
            if (status) status.textContent = 'The saved dismissal was cleared in this browser.';
            acknowledge.focus();
        });
    }
})();
