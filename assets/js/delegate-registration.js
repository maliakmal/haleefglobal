const delegateForm = document.querySelector('[data-delegate-form]');
if (delegateForm) {
    const list = delegateForm.querySelector('[data-delegate-list]');
    const template = delegateForm.querySelector('[data-delegate-template]');
    const add = delegateForm.querySelector('[data-add-delegate]');
    const status = delegateForm.querySelector('[data-delegate-status]');
    const limit = Number(delegateForm.dataset.limit);
    const number = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const cards = () => [...list.querySelectorAll('[data-delegate]')];
    add.hidden = false;

    function refresh() {
        const rows = cards();
        rows.forEach((row, index) => {
            row.querySelector('[data-delegate-number]').textContent = index + 1;
            const remove = row.querySelector('[data-remove-delegate]');
            if (remove) {
                remove.hidden = false;
                remove.querySelector('.sr-only').textContent = `delegate ${index + 1}`;
            }
            row.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/^delegates\[[^\]]*\]/, `delegates[${index}]`);
                input.autocomplete = input.autocomplete.replace(/^section-delegate\S+/, `section-delegate${index}`);
            });
        });
        add.disabled = rows.length >= limit;
        delegateForm.querySelector('[data-delegate-count]').textContent = rows.length;
        delegateForm.querySelector('[data-delegate-total]').textContent =
            delegateForm.dataset.currencyPattern.replace('0.00', number.format(Number(delegateForm.dataset.unitCents) * rows.length / 100));
    }

    add.addEventListener('click', () => {
        if (cards().length >= limit) return;
        const newRow = template.content.cloneNode(true);
        const input = newRow.querySelector('input');
        list.append(newRow);
        refresh();
        status.textContent = add.disabled ? `Delegate added. The limit is ${limit} per booking.` : 'Delegate added.';
        input.focus();
    });
    list.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-delegate]');
        if (!remove) return;
        const card = remove.closest('[data-delegate]');
        const next = card.nextElementSibling;
        card.remove();
        refresh();
        status.textContent = 'Delegate removed.';
        (next?.querySelector('[data-remove-delegate]') || add).focus();
    });
    refresh();
}
