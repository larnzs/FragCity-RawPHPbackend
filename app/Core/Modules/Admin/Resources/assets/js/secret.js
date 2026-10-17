let resetTimers = new WeakMap();

function loadConfetti(callback) {
    if (typeof confetti === 'function') {
        callback();
        return;
    }

    const src = typeof u === 'function'
        ? u('assets/js/libs/confetti.js')
        : '/assets/js/libs/confetti.js';

    if (window.AdminAssetLoader && typeof window.AdminAssetLoader.loadScripts === 'function') {
        window.AdminAssetLoader.loadScripts([src], callback);
        return;
    }

    const script = document.createElement('script');
    script.src = src;
    script.onload = callback;
    document.head.appendChild(script);
}

function launchConfetti(element) {
    if (typeof confetti !== 'function') {
        loadConfetti(() => launchConfetti(element));
        return;
    }

    const colors = [
        getComputedStyle(document.documentElement)
            .getPropertyValue('--primary')
            .trim(),
        getComputedStyle(document.documentElement)
            .getPropertyValue('--accent')
            .trim(),
    ];

    const duration = 3000;
    const endTime = Date.now() + duration;

    (function frame() {
        confetti({
            particleCount: 2,
            angle: 60,
            spread: 55,
            origin: { x: 0 },
            colors: colors,
        });
        confetti({
            particleCount: 2,
            angle: 120,
            spread: 55,
            origin: { x: 1 },
            colors: colors,
        });

        if (Date.now() < endTime) {
            requestAnimationFrame(frame);
        }
    })();
}

document.addEventListener('click', (event) => {
    const target = event.target.closest('.secret-confetti');
    if (!target) return;

    let clickCount = resetTimers.get(target) || { count: 0, timer: null };
    clickCount.count += 1;

    if (clickCount.timer) {
        clearTimeout(clickCount.timer);
    }

    if (clickCount.count === 3) {
        launchConfetti(target);
        clickCount.count = 0;
    } else {
        clickCount.timer = setTimeout(() => {
            clickCount.count = 0;
            clickCount.timer = null;
            resetTimers.set(target, clickCount);
        }, 1000);
    }
    resetTimers.set(target, clickCount);
});
