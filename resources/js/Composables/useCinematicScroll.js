import { onMounted, onUnmounted } from 'vue';

// Progressive enhancement: native scrolling, visible content without JavaScript,
// and one shared frame loop only while the active image layers are moving.
export function useCinematicScroll(root) {
    let media;
    let revealObserver;
    let layerObserver;
    let mutationObserver;
    let frame = 0;
    let destroyed = false;
    const prepared = new WeakSet();
    const revealed = new WeakSet();
    const layers = new Map();
    const activeLayers = new Set();
    const animations = new Set();
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    function reveal(element, immediately = false) {
        if (revealed.has(element)) return;
        revealed.add(element);
        element.removeAttribute('data-cinema-waiting');
        revealObserver?.unobserve(element);
        if (immediately || media.matches || !element.animate) return;
        const image = element.dataset.cinemaReveal === 'image';
        const animation = element.animate(image ? [
            { opacity: 0.3, clipPath: 'inset(5% 0 5% 0 round 24px)', transform: 'scale(.97)' },
            { opacity: 1, clipPath: 'inset(0% 0 0% 0 round 24px)', transform: 'scale(1)' },
        ] : [
            { opacity: 0, transform: 'translate3d(0, 28px, 0)' },
            { opacity: 1, transform: 'translate3d(0, 0, 0)' },
        ], {
            duration: image ? 1100 : 780,
            delay: clamp(Number(element.dataset.cinemaDelay) || 0, 0, 280),
            easing: 'cubic-bezier(.16, 1, .3, 1)',
            fill: 'backwards',
        });
        animations.add(animation);
        animation.onfinish = () => animations.delete(animation);
        animation.oncancel = () => animations.delete(animation);
    }

    function prepare() {
        if (!root.value || media.matches || destroyed) return;
        root.value.querySelectorAll('[data-cinema-stagger]').forEach(group => {
            group.querySelectorAll(group.dataset.cinemaStagger).forEach((child, index) => {
                if (prepared.has(child)) return;
                child.dataset.cinemaReveal ||= 'up';
                child.dataset.cinemaDelay ||= String((index % 4) * 85);
            });
        });
        root.value.querySelectorAll('[data-cinema-reveal]').forEach(element => {
            if (prepared.has(element)) return;
            prepared.add(element);
            // Restored/deep-linked scroll positions never hide content above you.
            if (element.getBoundingClientRect().bottom < 0) reveal(element, true);
            else {
                element.setAttribute('data-cinema-waiting', '');
                revealObserver.observe(element);
            }
        });
        root.value.querySelectorAll('[data-cinema-depth]').forEach(element => {
            if (layers.has(element)) return;
            const scene = element.closest('[data-cinema-scene]') || element.parentElement;
            layers.set(element, { scene, current: 0, depth: clamp(Number(element.dataset.cinemaDepth) || 0, -70, 70) });
            layerObserver.observe(element);
        });
        requestFrame();
    }

    function requestFrame() {
        if (!frame && !destroyed && !media.matches && document.visibilityState !== 'hidden') {
            frame = requestAnimationFrame(updateLayers);
        }
    }

    function updateLayers() {
        frame = 0;
        if (destroyed || media.matches) return;
        const height = window.innerHeight;
        const strength = window.innerWidth < 768 ? 0.4 : 1;
        const scenePositions = new Map();
        // Read positions before writing transforms, and use stable scene anchors.
        activeLayers.forEach(element => {
            const layer = layers.get(element);
            if (!layer || !element.isConnected) return;
            if (!scenePositions.has(layer.scene)) scenePositions.set(layer.scene, layer.scene.getBoundingClientRect());
        });
        let moving = false;
        activeLayers.forEach(element => {
            const layer = layers.get(element);
            if (!layer || !element.isConnected) return;
            const rect = scenePositions.get(layer.scene);
            const progress = clamp((height / 2 - rect.top - rect.height / 2) / ((height + rect.height) / 2), -1, 1);
            const desired = progress * layer.depth * strength;
            layer.current += (desired - layer.current) * 0.14;
            if (Math.abs(desired - layer.current) > 0.08) moving = true;
            else layer.current = desired;
            element.style.setProperty('--cinema-y', `${layer.current.toFixed(2)}px`);
        });
        if (moving) requestFrame();
    }

    function focusReveal(event) {
        const element = event.target.closest('[data-cinema-reveal]');
        if (element && root.value?.contains(element)) {
            reveal(element, true);
            // Keyboard users do not have to wait for an animation already running.
            element.getAnimations?.().forEach(animation => animation.finish());
        }
    }

    function resetMotion() {
        cancelAnimationFrame(frame);
        frame = 0;
        animations.forEach(animation => animation.cancel());
        animations.clear();
        root.value?.querySelectorAll('[data-cinema-waiting]').forEach(element => element.removeAttribute('data-cinema-waiting'));
        layers.forEach((layer, element) => {
            layer.current = 0;
            element.style.removeProperty('--cinema-y');
            element.style.removeProperty('will-change');
        });
    }

    function preferenceChanged() {
        if (media.matches) resetMotion();
        else { prepare(); requestFrame(); }
    }

    onMounted(() => {
        media = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (!root.value || !window.IntersectionObserver) return;
        revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) reveal(entry.target); });
        }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
        layerObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    activeLayers.add(entry.target);
                    if (!media.matches) entry.target.style.willChange = 'transform';
                } else {
                    activeLayers.delete(entry.target);
                    entry.target.style.removeProperty('will-change');
                }
            });
            requestFrame();
        }, { rootMargin: '120px' });
        mutationObserver = new MutationObserver(prepare);
        mutationObserver.observe(root.value, { childList: true, subtree: true });
        window.addEventListener('scroll', requestFrame, { passive: true });
        window.addEventListener('resize', requestFrame, { passive: true });
        document.addEventListener('visibilitychange', requestFrame);
        root.value.addEventListener('focusin', focusReveal);
        media.addEventListener('change', preferenceChanged);
        prepare();
    });

    onUnmounted(() => {
        destroyed = true;
        resetMotion();
        revealObserver?.disconnect();
        layerObserver?.disconnect();
        mutationObserver?.disconnect();
        window.removeEventListener('scroll', requestFrame);
        window.removeEventListener('resize', requestFrame);
        document.removeEventListener('visibilitychange', requestFrame);
        root.value?.removeEventListener('focusin', focusReveal);
        media?.removeEventListener('change', preferenceChanged);
    });
}
