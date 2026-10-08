(function () {
    'use strict';

    function bindReviewTabs() {
        document.querySelectorAll('.review-tab[data-review-filter]').forEach(function (tab) {
            if (tab.dataset.reviewBound === '1') return;
            tab.dataset.reviewBound = '1';

            tab.addEventListener('click', function (event) {
                event.preventDefault();

                var url = tab.href;
                var panel = document.getElementById('reviews');
                if (!panel) {
                    window.location.href = url;
                    return;
                }

                var scrollY = window.scrollY;
                var oldTop = panel.getBoundingClientRect().top;

                fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Review section could not be loaded.');
                        return response.text();
                    })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var freshPanel = doc.getElementById('reviews');
                        if (!freshPanel) throw new Error('Review section was not found.');

                        panel.replaceWith(freshPanel);
                        panel = freshPanel;

                        if (window.history && window.history.replaceState) {
                            window.history.replaceState({}, '', url);
                        }

                        bindReviewTabs();

                        requestAnimationFrame(function () {
                            window.scrollTo(0, scrollY);
                            var newTop = panel.getBoundingClientRect().top;
                            if (Math.abs(newTop - oldTop) > 2) {
                                window.scrollBy(0, newTop - oldTop);
                            }
                        });
                    })
                    .catch(function () {
                        window.location.href = url;
                    });
            });
        });
    }

    bindReviewTabs();
})();
