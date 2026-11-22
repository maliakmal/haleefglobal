document.querySelectorAll('[data-learning-share]').forEach(widget => {
    const url = widget.querySelector('[data-share-url]');
    const status = widget.querySelector('[data-share-status]');
    const copy = widget.querySelector('[data-copy-link]');
    const share = widget.querySelector('[data-native-share]');

    copy.hidden = false;
    url.hidden = true;
    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url.value);
            status.textContent = 'Link copied.';
        } catch {
            url.hidden = false;
            url.focus();
            url.select();
            status.textContent = 'Copy the selected link to share it.';
        }
    });

    if (typeof navigator.share === 'function') {
        share.hidden = false;
        share.addEventListener('click', async () => {
            try {
                await navigator.share({title: document.title, url: url.value});
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Sharing was unavailable. Try Copy link.';
            }
        });
    }
});
