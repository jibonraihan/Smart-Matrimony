(function () {
    'use strict';

    var contentSelector = '#chatRequestsContent';
    var activeRequest = null;

    function loadTab(url, pushState) {
        var current = document.querySelector(contentSelector);
        if (!current) return;

        var scrollY = window.scrollY || window.pageYOffset || 0;
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        current.setAttribute('aria-busy', 'true');

        fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'partial=1', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Unable to load chat requests.');
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var next = doc.querySelector(contentSelector);
                if (!next) throw new Error('Chat request section was not found.');

                current.replaceWith(next);
                if (pushState) window.history.pushState({ chatTab: true }, '', url);
                window.scrollTo(0, scrollY);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    current.removeAttribute('aria-busy');
                }
            })
            .finally(function () {
                activeRequest = null;
            });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.chat-tabs a');
        if (!link) return;
        if (event.button !== undefined && event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;

        event.preventDefault();
        loadTab(url.pathname + url.search, true);
    });

    window.addEventListener('popstate', function () {
        var url = new URL(window.location.href);
        loadTab(url.pathname + url.search, false);
    });
})();
