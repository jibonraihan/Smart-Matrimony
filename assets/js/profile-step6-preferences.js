document.addEventListener('DOMContentLoaded', () => {
    const religion = document.getElementById('religion');
    const preferredGender = document.getElementById('preferred_gender');
    const islamicFields = Array.from(document.querySelectorAll('.step6-islamic-preference'));
    const questionCards = Array.from(document.querySelectorAll('.step6-question-card'));
    const form = document.querySelector('.step6-form');
    const userGender = form ? (form.dataset.userGender || '') : '';

    const syncIslamicPreferences = () => {
        if (!religion || !preferredGender) return;

        const isIslam = religion.value === 'Islam';
        const gender = preferredGender.value;

        islamicFields.forEach((field) => {
            const genderRule = field.dataset.preferredGender || '';
            const shouldShow = isIslam && (!genderRule || genderRule === gender);
            const select = field.querySelector('select');

            field.hidden = !shouldShow;
            if (select) {
                select.disabled = !shouldShow;
                if (!shouldShow) select.value = '';
            }
        });
    };

    const syncPartnerQuestions = () => {
        const gender = userGender;
        questionCards.forEach((card) => {
            const questionGender = card.dataset.questionGender || 'Both';
            const shouldShow = questionGender === 'Both' || questionGender === gender;
            const input = card.querySelector('[data-trait-question-input]');

            card.hidden = !shouldShow;
            if (input) {
                input.disabled = !shouldShow;
                input.required = shouldShow;
                if (!shouldShow) input.value = '';
            }
        });
    };

    if (religion && preferredGender) {
        religion.addEventListener('change', syncIslamicPreferences);
        preferredGender.addEventListener('change', syncIslamicPreferences);
        syncIslamicPreferences();
    }

    if (questionCards.length) {
        syncPartnerQuestions();
    }


    const minAge = document.getElementById('min_age');
    const maxAge = document.getElementById('max_age');

    const syncAgeRange = (changedField = null) => {
        if (!minAge || !maxAge) return;

        let min = parseInt(minAge.value, 10);
        let max = parseInt(maxAge.value, 10);
        if (Number.isNaN(min) || Number.isNaN(max)) return;

        min = Math.min(80, Math.max(15, min));
        max = Math.min(80, Math.max(15, max));

        // Minimum age can never be greater than maximum age.
        // Equal values are allowed (e.g. 26 / 26).
        if (min > max) {
            if (changedField === minAge) {
                max = min;
                maxAge.value = String(max);
            } else {
                min = max;
                minAge.value = String(min);
            }
        }

        minAge.value = String(min);
        maxAge.value = String(max);

        // Keep both fields flexible within the selected range:
        // min can be 15..max, max can be min..80.
        minAge.max = String(max);
        maxAge.min = String(min);
    };

    [minAge, maxAge].forEach((field) => {
        if (field) field.addEventListener('change', function () {
            syncAgeRange(this);
        });
    });
    syncAgeRange();

    const minHeightFeet = document.getElementById('min_height_feet');
    const minHeightInches = document.getElementById('min_height_inches');
    const maxHeightFeet = document.getElementById('max_height_feet');
    const maxHeightInches = document.getElementById('max_height_inches');

    const getHeightInches = (feetSelect, inchesSelect) => {
        if (!feetSelect || !inchesSelect || feetSelect.value === '' || inchesSelect.value === '') return null;
        return (parseInt(feetSelect.value, 10) * 12) + parseInt(inchesSelect.value, 10);
    };

    const syncHeightRange = () => {
        const minHeight = getHeightInches(minHeightFeet, minHeightInches);
        const maxHeight = getHeightInches(maxHeightFeet, maxHeightInches);

        if (minHeight !== null && maxHeight !== null && maxHeight < minHeight) {
            maxHeightFeet.value = minHeightFeet.value;
            maxHeightInches.value = minHeightInches.value;
        }
    };

    [minHeightFeet, minHeightInches, maxHeightFeet, maxHeightInches].forEach((field) => {
        if (field) field.addEventListener('change', syncHeightRange);
    });
    syncHeightRange();

    if (form) {
        form.addEventListener('submit', () => {
            // Re-sync immediately before submit so hidden/inapplicable fields never post stale values.
            syncIslamicPreferences();
            syncPartnerQuestions();
        });
    }
});
