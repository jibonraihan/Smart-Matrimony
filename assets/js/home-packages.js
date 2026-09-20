(function () {
    'use strict';

    const carousel = document.querySelector('[data-package-carousel]');
    if (!carousel) return;

    const viewport = carousel.querySelector('.featured-package-viewport');
    const track = carousel.querySelector('[data-package-track]');
    const prev = carousel.querySelector('[data-package-prev]');
    const next = carousel.querySelector('[data-package-next]');
    const dotsWrap = document.querySelector('[data-package-dots]');
    const originals = Array.from(track.querySelectorAll('[data-package-card]'));
    const total = originals.length;
    if (!total) return;

    let visible = 3;
    let cloneCount = 0;
    let index = 0;
    let cardStep = 0;
    let timer = null;
    let isAnimating = false;
    let touchStartX = 0;
    let touchDeltaX = 0;

    function getVisible() {
        if (window.innerWidth <= 767.98) return 1;
        if (window.innerWidth <= 991.98) return 2;
        return 3;
    }

    function createDots() {
        if (!dotsWrap) return;
        dotsWrap.innerHTML = '';
        if (total <= visible) {
            dotsWrap.hidden = true;
            return;
        }
        dotsWrap.hidden = false;
        for (let i = 0; i < total; i += 1) {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'featured-package-dot';
            dot.setAttribute('aria-label', `Go to package ${i + 1}`);
            dot.addEventListener('click', () => goTo(cloneCount + i));
            dotsWrap.appendChild(dot);
        }
    }

    function updateDots() {
        if (!dotsWrap || dotsWrap.hidden) return;
        const dots = dotsWrap.querySelectorAll('.featured-package-dot');
        const realIndex = ((index - cloneCount) % total + total) % total;
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === realIndex));
    }

    function updateControls() {
        const disabled = total <= visible;
        prev.disabled = disabled;
        next.disabled = disabled;
        prev.hidden = disabled;
        next.hidden = disabled;
    }

    function rebuild() {
        visible = getVisible();
        track.querySelectorAll('.featured-package-clone').forEach((node) => node.remove());

        if (total <= visible) {
            cloneCount = 0;
            index = 0;
            originals.forEach((card) => track.appendChild(card));
        } else {
            cloneCount = visible;
            const before = originals.slice(-cloneCount).map((card) => {
                const clone = card.cloneNode(true);
                clone.classList.add('featured-package-clone');
                return clone;
            });
            const after = originals.slice(0, cloneCount).map((card) => {
                const clone = card.cloneNode(true);
                clone.classList.add('featured-package-clone');
                return clone;
            });
            originals.forEach((card) => track.appendChild(card));
            before.reverse().forEach((clone) => track.insertBefore(clone, track.firstChild));
            after.forEach((clone) => track.appendChild(clone));
            index = cloneCount;
        }

        requestAnimationFrame(() => {
            const gap = parseFloat(getComputedStyle(track).gap) || 0;
            const width = viewport.clientWidth;
            const cardWidth = total <= visible ? (width - gap * Math.max(visible - 1, 0)) / visible : (width - gap * (visible - 1)) / visible;
            cardStep = cardWidth + gap;
            track.querySelectorAll('[data-package-card]').forEach((card) => {
                card.style.flexBasis = `${cardWidth}px`;
            });
            track.style.transition = 'none';
            track.style.transform = `translate3d(${-index * cardStep}px,0,0)`;
            requestAnimationFrame(() => { track.style.transition = ''; });
            createDots();
            updateControls();
            updateDots();
        });
    }

    function goTo(target, animate = true) {
        if (total <= visible) return;
        index = target;
        isAnimating = animate;
        track.style.transition = animate ? '' : 'none';
        track.style.transform = `translate3d(${-index * cardStep}px,0,0)`;
        updateDots();
    }

    function nextSlide() { if (total > visible) goTo(index + 1); }
    function prevSlide() { if (total > visible) goTo(index - 1); }

    function startAuto() {
        stopAuto();
        if (total <= visible) return;
        timer = window.setInterval(nextSlide, 4500);
    }

    function stopAuto() {
        if (timer) window.clearInterval(timer);
        timer = null;
    }

    track.addEventListener('transitionend', () => {
        if (!isAnimating || total <= visible) return;
        isAnimating = false;
        if (index >= cloneCount + total) {
            index = cloneCount;
            track.style.transition = 'none';
            track.style.transform = `translate3d(${-index * cardStep}px,0,0)`;
            requestAnimationFrame(() => { track.style.transition = ''; });
        } else if (index < cloneCount) {
            index = cloneCount + total - 1;
            track.style.transition = 'none';
            track.style.transform = `translate3d(${-index * cardStep}px,0,0)`;
            requestAnimationFrame(() => { track.style.transition = ''; });
        }
        updateDots();
    });

    prev.addEventListener('click', () => { prevSlide(); startAuto(); });
    next.addEventListener('click', () => { nextSlide(); startAuto(); });

    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);

    viewport.addEventListener('touchstart', (event) => {
        touchStartX = event.touches[0].clientX;
        touchDeltaX = 0;
        stopAuto();
    }, { passive: true });
    viewport.addEventListener('touchmove', (event) => {
        touchDeltaX = event.touches[0].clientX - touchStartX;
    }, { passive: true });
    viewport.addEventListener('touchend', () => {
        if (Math.abs(touchDeltaX) > 45) {
            if (touchDeltaX < 0) nextSlide(); else prevSlide();
        }
        startAuto();
    }, { passive: true });

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(rebuild, 160);
    });

    rebuild();
    startAuto();
})();
