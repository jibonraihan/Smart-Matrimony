document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('bookingCancelModal');
    const bookingIdInput = document.getElementById('cancelBookingInput');
    const bookingIdLabel = document.getElementById('cancelBookingId');
    const customerLabel = document.getElementById('cancelCustomerName');
    const reasonInput = document.getElementById('cancellationReason');

    const closeModal = () => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('booking-modal-open');
        if (reasonInput) reasonInput.value = '';
        if (bookingIdInput) bookingIdInput.value = '';
    };

    document.querySelectorAll('.js-open-cancel').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal) return;
            const id = button.dataset.bookingId || '';
            const customer = button.dataset.customer || 'Customer';
            if (bookingIdInput) bookingIdInput.value = id;
            if (bookingIdLabel) bookingIdLabel.textContent = `#${id}`;
            if (customerLabel) customerLabel.textContent = customer;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('booking-modal-open');
            window.setTimeout(() => reasonInput?.focus(), 50);
        });
    });

    document.querySelectorAll('.js-close-cancel').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('is-open')) closeModal();
    });

    document.querySelectorAll('.js-delete-booking-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm('Delete this booking history permanently? This action cannot be undone.')) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.booking-action-form').forEach((form) => {
        const action = form.querySelector('input[name="action"]')?.value;
        if (action === 'booking_confirm' || action === 'booking_complete') {
            form.addEventListener('submit', (event) => {
                const text = action === 'booking_confirm'
                    ? 'Confirm this booking? A confirmation email will be sent to the customer.'
                    : 'Mark this booking as completed?';
                if (!window.confirm(text)) event.preventDefault();
            });
        }
    });
});
