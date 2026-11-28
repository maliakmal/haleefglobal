const delegateForm = document.querySelector('[data-delegate-form]');
if (delegateForm) {
    const list = delegateForm.querySelector('[data-delegate-list]');
    const template = delegateForm.querySelector('[data-delegate-template]');
    const add = delegateForm.querySelector('[data-add-delegate]');
    const status = delegateForm.querySelector('[data-delegate-status]');
    const limit = Number(delegateForm.dataset.limit);
    const number = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rate = delegateForm.elements.promo_rate;
    const money = cents => delegateForm.dataset.currencyPattern.replace('0.00', number.format(cents / 100));
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
            row.querySelectorAll('input, select').forEach(input => {
                input.name = input.name.replace(/^delegates\[[^\]]*\]/, `delegates[${index}]`);
                input.autocomplete = input.autocomplete.replace(/^section-delegate\S+/, `section-delegate${index}`);
            });
        });
        add.disabled = rows.length >= limit;
        delegateForm.querySelector('[data-delegate-count]').textContent = rows.length;
        const subtotal = Number(delegateForm.dataset.unitCents) * rows.length;
        const discount = Math.max(0, Math.min(subtotal - 1, Math.floor((subtotal * Number(rate.value) + 50) / 100)));
        delegateForm.querySelector('[data-delegate-subtotal]').textContent = money(subtotal);
        delegateForm.querySelector('[data-promo-line]').hidden = !discount;
        delegateForm.querySelector('[data-promo-discount]').textContent = discount ? '−' + money(discount) : '';
        delegateForm.querySelector('[data-delegate-total]').textContent = money(subtotal - discount);
    }

    delegateForm.addEventListener('input', event => {
        if (event.target.name !== 'promo_code' && event.target.name !== 'delegates[0][email]') return;
        if (!rate.value) return;
        rate.value = '';
        delegateForm.querySelector('[data-promo-notice]').textContent = 'Apply the promo code again to update your total.';
        refresh();
    });
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
