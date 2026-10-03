document.addEventListener('DOMContentLoaded', () => {
    const getModal = () => document.getElementById('bookingCancelModal');

    const closeModal = () => {
        const modal = getModal();
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('booking-modal-open');

        const reasonInput = document.getElementById('cancellationReason');
        const bookingIdInput = document.getElementById('cancelBookingInput');
        if (reasonInput) reasonInput.value = '';
        if (bookingIdInput) bookingIdInput.value = '';
    };

    // Customer-booking status tabs are loaded in-place. This prevents the
    // browser from navigating to the top of the dashboard or following the
    // #bookings fragment on every status change.
    const loadBookingTab = async (link) => {
        const panel = document.getElementById('bookings');
        if (!panel || !link) return;

        const href = link.href.split('#')[0];
        const previousScrollY = window.scrollY;
        const tabs = panel.querySelectorAll('.booking-tab');
        tabs.forEach((tab) => {
            tab.setAttribute('aria-disabled', 'true');
            tab.style.pointerEvents = 'none';
        });
        panel.setAttribute('aria-busy', 'true');
        panel.classList.add('booking-panel-loading');

        try {
            const response = await fetch(href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Unable to load bookings.');

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextPanel = doc.getElementById('bookings');
            if (!nextPanel) throw new Error('Booking section was not returned.');

            panel.replaceWith(nextPanel);
            history.pushState({ bookingTab: true }, '', href + '#bookings');
            window.scrollTo(0, previousScrollY);
        } catch (error) {
            // Keep the current booking section intact if the in-place request fails.
            console.error('Customer bookings tab load failed:', error);
            window.location.href = link.href;
        } finally {
            const currentPanel = document.getElementById('bookings');
            if (currentPanel) {
                currentPanel.setAttribute('aria-busy', 'false');
                currentPanel.classList.remove('booking-panel-loading');
                currentPanel.querySelectorAll('.booking-tab').forEach((tab) => {
                    tab.removeAttribute('aria-disabled');
                    tab.style.pointerEvents = '';
                });
            }
        }
    };

    // Delegation keeps working after the booking panel is replaced by AJAX.
    document.addEventListener('click', (event) => {
        const tab = event.target.closest('.booking-tab');
        if (tab && document.getElementById('bookings')?.contains(tab)) {
            event.preventDefault();
            loadBookingTab(tab);
            return;
        }

        const openCancel = event.target.closest('.js-open-cancel');
        if (openCancel) {
            const modal = getModal();
            if (!modal) return;
            const id = openCancel.dataset.bookingId || '';
            const customer = openCancel.dataset.customer || 'Customer';
            const bookingIdInput = document.getElementById('cancelBookingInput');
            const bookingIdLabel = document.getElementById('cancelBookingId');
            const customerLabel = document.getElementById('cancelCustomerName');
            const reasonInput = document.getElementById('cancellationReason');
            if (bookingIdInput) bookingIdInput.value = id;
            if (bookingIdLabel) bookingIdLabel.textContent = `#${id}`;
            if (customerLabel) customerLabel.textContent = customer;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('booking-modal-open');
            window.setTimeout(() => reasonInput?.focus(), 50);
            return;
        }

        if (event.target.closest('.js-close-cancel')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && getModal()?.classList.contains('is-open')) {
            closeModal();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.classList.contains('js-delete-booking-form')) {
            if (!window.confirm('Delete this booking history permanently? This action cannot be undone.')) {
                event.preventDefault();
            }
            return;
        }

        if (!form.classList.contains('booking-action-form')) return;
        const action = form.querySelector('input[name="action"]')?.value;
        if (action !== 'booking_confirm' && action !== 'booking_complete') return;

        const text = action === 'booking_confirm'
            ? 'Confirm this booking? A confirmation email will be sent to the customer.'
            : 'Mark this booking as completed?';
        if (!window.confirm(text)) event.preventDefault();
    });
});
