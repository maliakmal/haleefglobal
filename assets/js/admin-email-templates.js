const emailEditor = document.querySelector('[data-email-editor]');
if (emailEditor) {
    const body = emailEditor.elements.body;
    const error = document.querySelector('[data-email-error]');
    let timer;
    let request;
    async function preview() {
        request?.abort();
        request = new AbortController();
        const signal = request.signal;
        try {
            const response = await fetch(emailEditor.dataset.preview, { method: 'POST', body: new FormData(emailEditor), signal });
            const result = await response.json();
            if (signal.aborted) return;
            if (!response.ok) { error.textContent = result.error || 'Preview unavailable.'; return; }
            error.textContent = '';
            document.querySelector('[data-email-subject]').textContent = result.subject;
            document.querySelector('[data-email-text]').textContent = result.text;
            document.querySelector('[data-email-html]').srcdoc = result.html;
        } catch (err) {
            if (!signal.aborted) error.textContent = 'Preview unavailable. Try refreshing.';
        }
    }
    function schedule() {
        clearTimeout(timer);
        request?.abort();
        timer = setTimeout(preview, 400);
    }
    emailEditor.addEventListener('input', schedule);
    emailEditor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-email-token]');
        if (!button) return;
        body.setRangeText(button.dataset.emailToken, body.selectionStart, body.selectionEnd, 'end');
        body.focus();
        schedule();
    });
    emailEditor.addEventListener('submit', event => {
        if (event.submitter?.value !== 'preview') return;
        event.preventDefault();
        clearTimeout(timer);
        preview();
    });
}
