export function animateEffortSlider(slider, previous, index, count) {
    if (previous === null || previous === index) return;
    const distance = (previous - index) * Math.max(0, slider.clientWidth - 28) / (count - 1);
    slider.style.setProperty('--ai-effort-slide-offset', `${distance}px`);
    slider.classList.remove('ai-effort-sliding');
    // Restart the thumb transition from its previous visual position.
    void slider.offsetWidth;
    slider.classList.add('ai-effort-sliding');
}
