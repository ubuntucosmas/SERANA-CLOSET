import { animate, stagger } from 'animejs';

export const revealDirective = {
    mounted(el, binding) {
        // If we want to animate words, split the text into spans
        if (el.dataset.revealWords === 'true' && el.children.length === 0) {
            const text = el.textContent;
            if (text.trim() !== '') {
                const words = text.split(/\s+/);
                el.innerHTML = '';
                words.forEach((word, index) => {
                    const span = document.createElement('span');
                    span.className = 'word';
                    span.textContent = word;
                    if (index < words.length - 1) {
                        span.textContent += ' ';
                    }
                    el.appendChild(span);
                });
                // If no staggerSelector was set, use the words we just created
                if (!el.dataset.revealStagger) {
                    // We'll use a variable to hold the selector for the animation
                    // We'll set it to '.word' and then use it below.
                    // Note: we cannot change the dataset because it's read-only? Actually we can, but let's use a local variable.
                    // We'll reassign the staggerSelector variable below.
                }
            }
        }

        // Initial state - slow motion ready
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const direction = target.dataset.revealDirection || 'up';
                    // Let staggerSelector be from dataset, but note we might have changed it for words above.
                    // We'll use a variable that we can set conditionally.
                    let staggerSelector = target.dataset.revealStagger;
                    const delay = parseInt(target.dataset.revealDelay || 0);
                    const duration = parseInt(target.dataset.revealDuration || 800);

                    let translateY = [20, 0];
                    let translateX = [0, 0];

                    if (direction === 'down') translateY = [-20, 0];
                    if (direction === 'left') { translateY = [0, 0]; translateX = [20, 0]; }
                    if (direction === 'right') { translateY = [0, 0]; translateX = [-20, 0]; }

                    if (staggerSelector) {
                        const elements = target.querySelectorAll(staggerSelector);
                        if (elements.length > 0) {
                            elements.forEach(child => {
                                child.style.opacity = '0';
                                child.style.transform = 'translateY(20px)';
                            });
                            target.style.opacity = '1';

                            animate(elements, {
                                translateY,
                                translateX,
                                opacity: [0, 1],
                                delay: stagger(100, { start: delay }),
                                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                                duration
                            });
                        }
                    } else {
                        animate(target, {
                            translateY,
                            translateX,
                            opacity: [0, 1],
                            delay,
                            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                            duration
                        });
                    }

                    observer.unobserve(target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });

        observer.observe(el);
    }
};
