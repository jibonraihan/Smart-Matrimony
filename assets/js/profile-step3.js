document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.step3-form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const requiredFields = Array.from(form.querySelectorAll('[required]')).filter(function (field) {
            return field.offsetParent !== null && !field.disabled;
        });
        const invalid = requiredFields.find(function (field) {
            return String(field.value || '').trim() === '';
        });
        if (invalid) {
            event.preventDefault();
            invalid.classList.add('step3-field-invalid');
            invalid.focus();
            invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    form.querySelectorAll('[required]').forEach(function (field) {
        const clearInvalid = function () {
            if (String(field.value || '').trim() !== '') field.classList.remove('step3-field-invalid');
        };
        field.addEventListener('change', clearInvalid);
        field.addEventListener('input', clearInvalid);
    });

    const interestInputs = Array.from(form.querySelectorAll('input[name="free_time_interests[]"]'));
    const interestGrid = form.querySelector('.interest-grid');
    if (interestInputs.length && interestGrid) {
        let counter = interestGrid.parentElement.querySelector('.step3-interest-count');
        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'step3-interest-count';
            interestGrid.insertAdjacentElement('afterend', counter);
        }
        const updateInterestCount = function () {
            const count = interestInputs.filter(function (input) { return input.checked; }).length;
            counter.textContent = count === 0 ? 'No interests selected yet.' : count + (count === 1 ? ' interest selected.' : ' interests selected.');
        };
        interestInputs.forEach(function (input) { input.addEventListener('change', updateInterestCount); });
        updateInterestCount();
    }

    form.querySelectorAll('textarea[maxlength]').forEach(function (textarea) {
        const max = parseInt(textarea.getAttribute('maxlength'), 10);
        if (!Number.isFinite(max)) return;
        const counter = document.createElement('div');
        counter.className = 'step3-character-count';
        textarea.insertAdjacentElement('afterend', counter);
        const updateCounter = function () { counter.textContent = textarea.value.length + ' / ' + max; };
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
});
