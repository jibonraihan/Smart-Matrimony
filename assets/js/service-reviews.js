document.addEventListener('DOMContentLoaded', () => {
    const config = window.SM_SERVICE_REVIEW || {};
    const cards = document.querySelectorAll('[data-review-card]');
    if (!cards.length || !config.endpoint) return;

    const labels = {
        1: 'Not satisfied',
        2: 'Could be better',
        3: 'Good',
        4: 'Very good',
        5: 'Excellent'
    };

    const setFeedback = (element, message, type = '') => {
        if (!element) return;
        element.textContent = message || '';
        element.className = `service-review-feedback${type ? ` ${type}` : ''}`;
    };

    cards.forEach(card => {
        const form = card.querySelector('[data-review-form]');
        if (!form) return;

        const stars = [...form.querySelectorAll('[data-rating]')];
        const label = form.querySelector('[data-rating-label]');
        const textarea = form.querySelector('[data-review-text]');
        const count = form.querySelector('[data-review-count]');
        const feedback = form.querySelector('[data-review-feedback]');
        const submit = form.querySelector('.service-review-submit');
        let selectedRating = 0;

        const updateStars = (rating) => {
            selectedRating = rating;
            stars.forEach(star => {
                const value = Number(star.dataset.rating || 0);
                const active = value <= rating;
                star.classList.toggle('active', active);
                star.setAttribute('aria-checked', value === rating ? 'true' : 'false');
            });
            if (label) label.textContent = rating ? `${labels[rating]} · ${rating}/5` : 'Choose a rating';
        };

        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const value = Number(star.dataset.rating || 0);
                stars.forEach(item => item.classList.toggle('preview', Number(item.dataset.rating || 0) <= value));
            });
            star.addEventListener('mouseleave', () => {
                stars.forEach(item => item.classList.remove('preview'));
            });
            star.addEventListener('focus', () => {
                const value = Number(star.dataset.rating || 0);
                stars.forEach(item => item.classList.toggle('preview', Number(item.dataset.rating || 0) <= value));
            });
            star.addEventListener('blur', () => {
                stars.forEach(item => item.classList.remove('preview'));
            });
            star.addEventListener('click', () => updateStars(Number(star.dataset.rating || 0)));
            star.addEventListener('keydown', event => {
                if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const next = Math.min(5, selectedRating + 1 || 1);
                    updateStars(next);
                    stars[next - 1]?.focus();
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    const next = Math.max(1, selectedRating - 1 || 1);
                    updateStars(next);
                    stars[next - 1]?.focus();
                }
            });
        });

        if (textarea && count) {
            const updateCount = () => {
                count.textContent = `${textarea.value.length}/2000`;
            };
            textarea.addEventListener('input', updateCount);
            updateCount();
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            setFeedback(feedback, '');

            if (selectedRating < 1 || selectedRating > 5) {
                setFeedback(feedback, 'Please choose a star rating first.', 'error');
                stars[0]?.focus();
                return;
            }

            const bookingDetailId = Number(card.dataset.bookingDetailId || 0);
            if (!bookingDetailId) {
                setFeedback(feedback, 'This booking item could not be identified.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'submit');
            formData.append('csrf', config.csrf || '');
            formData.append('booking_detail_id', String(bookingDetailId));
            formData.append('rating', String(selectedRating));
            formData.append('review_text', textarea ? textarea.value.trim() : '');

            submit.disabled = true;
            submit.classList.add('is-loading');
            setFeedback(feedback, 'Submitting your review…');

            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Your review could not be submitted.');
                }

                const review = data.review || {};
                card.classList.add('is-reviewed');
                card.innerHTML = `
                    <div class="service-review-head">
                        <div>
                            <span class="service-review-kicker"><i class="fa-solid fa-circle-check"></i> YOUR REVIEW</span>
                            <h4>Thanks for sharing your experience</h4>
                        </div>
                        <span class="service-review-published">Published</span>
                    </div>
                    <div class="service-review-rating" aria-label="${Number(review.rating || selectedRating)}/5 stars">
                        ${[1,2,3,4,5].map(star => `<i class="fa-solid fa-star${star <= Number(review.rating || selectedRating) ? ' active' : ''}"></i>`).join('')}
                        <strong>${Number(review.rating || selectedRating)}/5</strong>
                    </div>
                    ${review.review_text ? `<p class="service-review-text">“${escapeHtml(review.review_text).replace(/\n/g, '<br>')}”</p>` : ''}
                    <small class="service-review-date">Submitted just now</small>
                    ${review.provider_rating !== undefined ? `<div class="service-review-updated"><i class="fa-solid fa-chart-simple"></i> Service rating updated to <strong>${Number(review.provider_rating).toFixed(1)}/5</strong> from ${Number(review.provider_review_count || 0)} review${Number(review.provider_review_count || 0) === 1 ? '' : 's'}.</div>` : ''}
                `;
            } catch (error) {
                setFeedback(feedback, error.message || 'Your review could not be submitted. Please try again.', 'error');
                submit.disabled = false;
                submit.classList.remove('is-loading');
            }
        });
    });

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#039;',
            '"': '&quot;'
        }[character]));
    }
});
