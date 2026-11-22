if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const nodes = document.querySelectorAll(
        '[data-reveal], .learning-course-copy > section, .learning-editorial .learning-band, ' +
        '.learning-highlights > div, .training-intro, .training-formats, .training-focus, .training-process, .course-card'
    );
    const observer = new IntersectionObserver(entries => {
        entries.forEach(({target, isIntersecting}) => {
            if (!isIntersecting) return;
            target.classList.remove('learning-reveal-pending');
            observer.unobserve(target);
        });
    }, {threshold: 0, rootMargin: '0px 0px 12px 0px'});
    nodes.forEach(node => {
        if (node.getBoundingClientRect().top <= innerHeight || node.matches(':target') || node.querySelector('[role="status"],[role="alert"]')) return;
        node.classList.add('learning-reveal', 'learning-reveal-pending');
        observer.observe(node);
        node.addEventListener('focusin', () => {
            node.classList.remove('learning-reveal-pending');
            observer.unobserve(node);
        }, {once:true});
    });
}
