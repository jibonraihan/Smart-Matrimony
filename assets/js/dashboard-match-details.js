(function () {
    'use strict';

    const modal = document.getElementById('matchDetailsModal');
    const list = document.getElementById('matchDetailsList');
    if (!modal || !list) return;

    const overall = document.getElementById('matchDetailsOverall');
    const forward = document.getElementById('matchDetailsForward');
    const reverse = document.getElementById('matchDetailsReverse');
    const title = document.getElementById('matchDetailsTitle');

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

    function openModal(data, name) {
        if (!data) return;
        overall.textContent = fmt(data.score);
        forward.textContent = fmt(data.forward_score);
        reverse.textContent = fmt(data.reverse_score);
        title.textContent = name ? name + ' — Match Details' : 'Match Details';
        list.innerHTML = '';

        Object.values(data.categories || {}).forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'match-detail-row';
            const final = item.score;
            row.innerHTML =
                '<div class="match-detail-name"><i class="fa-solid ' + String(item.icon || 'fa-circle').replace(/[^a-z0-9-]/gi, '') + '"></i><span>' + escapeHtml(item.label || '') + '</span></div>' +
                '<div class="match-detail-score"><strong>' + fmt(item.forward) + '</strong>You → Them</div>' +
                '<div class="match-detail-score"><strong>' + fmt(item.reverse) + '</strong>Them → You</div>' +
                '<div class="match-detail-final ' + statusClass(final) + '">' + fmt(final) + '</div>';
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

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
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
