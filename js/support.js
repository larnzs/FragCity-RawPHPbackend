(function () {
    'use strict';

    function initSupport() {
        const page = document.querySelector('.support-page');
        if (!page) return;

        const copyBtn = page.querySelector('#copyTsAddress');
        const toast = page.querySelector('#toast');

        if (copyBtn && !copyBtn.dataset.bound) {
            copyBtn.dataset.bound = '1';
            copyBtn.addEventListener('click', async function () {
                const tsAddress = 'ts.fragcity.ir:9987';
                const original = copyBtn.innerHTML;
                try {
                    await navigator.clipboard.writeText(tsAddress);
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> کپی شد!';
                    copyBtn.classList.add('copied');
                    setTimeout(function () {
                        copyBtn.innerHTML = original;
                        copyBtn.classList.remove('copied');
                    }, 1800);
                } catch (error) {
                    window.prompt('آدرس TeamSpeak را کپی کنید:', tsAddress);
                }
            });
        }

        if (toast) {
            setTimeout(function () {
                toast.classList.add('is-hidden');
            }, 3000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupport, { once: true });
    } else {
        initSupport();
    }
})();
