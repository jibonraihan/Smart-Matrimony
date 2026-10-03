document.addEventListener('DOMContentLoaded', function () {
    const timeline = document.getElementById('hiwTimeline');
    const progress = document.getElementById('hiwProgress');
    const steps = Array.from(document.querySelectorAll('.hiw-step'));

    if (!timeline || !progress || !steps.length) return;

    const updateJourney = () => {
        const rect = timeline.getBoundingClientRect();
        const viewportPoint = window.innerHeight * 0.58;
        const travelled = Math.min(Math.max(viewportPoint - rect.top, 0), rect.height);
        progress.style.height = rect.height ? `${(travelled / rect.height) * 100}%` : '0%';

        let activeIndex = -1;
        steps.forEach((step, index) => {
            const stepRect = step.getBoundingClientRect();
            const distance = Math.abs((stepRect.top + stepRect.height / 2) - viewportPoint);
            if (distance < window.innerHeight * 0.28) activeIndex = index;
            step.classList.toggle('is-active', index === activeIndex);
        });
    };

    let ticking = false;
    const onScroll = () => {
        if (ticking) return;
        window.requestAnimationFrame(() => {
            updateJourney();
            ticking = false;
        });
        ticking = true;
    };

    updateJourney();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', updateJourney);
});
