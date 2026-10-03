(function () {
    'use strict';

    const modal = document.getElementById('matchDetailsModal');
    const list = document.getElementById('matchDetailsList');
    if (!modal || !list) return;

    const overall = document.getElementById('matchDetailsOverall');
    const title = document.getElementById('matchDetailsTitle');
    const forward = document.getElementById('matchDetailsForward');
    const reverse = document.getElementById('matchDetailsReverse');

    function fmt(value) {
        if (value === null || value === undefined || value === '') return '—';
        return Math.round(Number(value)) + '%';
    }

    function statusClass(value) {
        if (value === null || value === undefined) return 'is-na';
        if (Number(value) >= 100) return 'is-match';
        if (Number(value) <= 0) return 'is-no';
        return 'is-partial';
    }

    function safe(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
        return div.innerHTML;
    }

    function openModal(data, name) {
        if (!data) return;
        overall.textContent = fmt(data.score);
        if (forward) forward.textContent = fmt(data.score);
        if (reverse) reverse.textContent = '—%';
        title.textContent = name ? name + ' — Match Details' : 'Match Details';
        list.innerHTML = '';

        Object.values(data.details || {}).forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'match-detail-row';
            const contribution = Number(item.contribution || 0);
            row.innerHTML =
                '<div class="match-detail-name"><i class="fa-solid ' + String(item.icon || 'fa-circle').replace(/[^a-z0-9-]/gi, '') + '"></i><span>' + safe(item.label || '') + '</span></div>' +
                '<div class="match-detail-value"><span class="match-detail-label">You want</span><strong>' + safe(item.want || 'No preference') + '</strong></div>' +
                '<div class="match-detail-value"><span class="match-detail-label">They have</span><strong>' + safe(item.have || 'Not provided') + '</strong></div>' +
                '<div class="match-detail-contribution ' + statusClass(item.score) + '"><strong>' + Math.round(contribution) + '%</strong><span>Contribution</span></div>';
            list.appendChild(row);
        });

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('match-details-open');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('match-details-open');
    }

    document.querySelectorAll('.profile-match-details-button').forEach(function (button) {
        button.addEventListener('click', function () {
            let data = null;
            try { data = JSON.parse(button.getAttribute('data-match-details') || 'null'); } catch (e) { data = null; }
            const card = button.closest('.profile-card');
            const name = card ? (card.querySelector('.profile-card-name-row h4') || {}).textContent : '';
            openModal(data, name ? name.trim() : '');
        });
    });

    modal.querySelectorAll('[data-match-close]').forEach(function (element) {
        element.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
})();
