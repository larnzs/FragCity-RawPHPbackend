(function () {
    'use strict';

    function initMinecraftStorePage() {
        const root = document.querySelector('.mc-store-page');
        if (!root || root.dataset.initialized === '1') return false;

        const pricingNode = root.querySelector('#mcStorePricingData');
        const playersInput = root.querySelector('#mcStorePlayers');
        const memoryInput = root.querySelector('#mcStoreMemory');
        const cpuInput = root.querySelector('#mcStoreCpu');
        const diskInput = root.querySelector('#mcStoreDisk');

        const missing = [];
        if (!pricingNode) missing.push('#mcStorePricingData');
        if (!playersInput) missing.push('#mcStorePlayers');
        if (!memoryInput) missing.push('#mcStoreMemory');
        if (!cpuInput) missing.push('#mcStoreCpu');
        if (!diskInput) missing.push('#mcStoreDisk');

        if (missing.length) {
            const signature = missing.join(',');
            if (root.dataset.missingElements !== signature) {
                console.error('[FragCity] Minecraft store initialization is waiting for:', missing);
                root.dataset.missingElements = signature;
            }
            return false;
        }

        delete root.dataset.missingElements;

        const pricing = {
            baseSlots: Number(pricingNode.dataset.baseSlots),
            basePrice: Number(pricingNode.dataset.basePrice),
            extraSlotPrice: Number(pricingNode.dataset.extraSlotPrice),
            // Data attributes ending in digits retain the hyphen in DOMStringMap.
            // Read them by their exact HTML attribute names to avoid NaN pricing.
            ramPrices: {
                1024: Number(pricingNode.getAttribute('data-ram-price-1024')),
                1280: Number(pricingNode.getAttribute('data-ram-price-1280')),
                1536: Number(pricingNode.getAttribute('data-ram-price-1536'))
            }
        };

        const invalidPricing =
            !Number.isInteger(pricing.baseSlots) ||
            !Number.isFinite(pricing.basePrice) ||
            !Number.isFinite(pricing.extraSlotPrice) ||
            pricing.baseSlots < 1 ||
            pricing.basePrice < 0 ||
            pricing.extraSlotPrice < 0 ||
            Object.values(pricing.ramPrices).some(function (value) {
                return !Number.isFinite(value) || value < 0;
            });

        if (invalidPricing) {
            console.error('[FragCity] Minecraft pricing configuration is invalid.');
            return false;
        }

        // Set this only after all required elements and pricing data are ready.
        root.dataset.initialized = '1';

        const tomanFormatter = new Intl.NumberFormat('fa-IR');
        const numberFormatter = new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 2
        });

        function formatToman(value) {
            const safeValue = Number.isFinite(Number(value))
                ? Math.max(0, Math.round(Number(value)))
                : 0;
            return tomanFormatter.format(safeValue) + ' تومان';
        }

        function formatGb(megabytes) {
            const value = Number(megabytes);
            return numberFormatter.format((Number.isFinite(value) ? value : 0) / 1024)
                + ' گیگابایت';
        }

        function setSummary(name, value) {
            const target = root.querySelector('[data-mc-summary="' + name + '"]');
            if (target) target.textContent = String(value ?? '');
        }

        function syncRangeProgress(input) {
            const min = Number(input.min);
            const max = Number(input.max);
            const value = Number(input.value);
            const progress = Number.isFinite(min) && Number.isFinite(max) && max > min
                ? ((value - min) / (max - min)) * 100
                : 0;
            input.style.setProperty('--mc-range-progress', Math.min(100, Math.max(0, progress)) + '%');
        }

        function update() {
            const rawPlayers = Number(playersInput.value);
            const players = Math.max(
                pricing.baseSlots,
                Math.min(64, Number.isFinite(rawPlayers) ? rawPlayers : pricing.baseSlots)
            );
            const memory = Number(memoryInput.value);
            const cpu = Number(cpuInput.value);
            const disk = Number(diskInput.value);
            const extraSlots = Math.max(0, players - pricing.baseSlots);
            const extraPrice = extraSlots * pricing.extraSlotPrice;
            const ramPrice = pricing.ramPrices[memory] ?? 0;
            const totalPrice = pricing.basePrice + extraPrice + ramPrice;

            const playersOutput = root.querySelector('#mcStorePlayersValue');
            if (playersOutput) playersOutput.textContent = numberFormatter.format(players);

            syncRangeProgress(playersInput);
            setSummary('players', numberFormatter.format(players) + ' بازیکن');
            setSummary('memory', formatGb(memory));
            setSummary('cpu', numberFormatter.format((Number.isFinite(cpu) ? cpu : 100) / 100) + ' vCore');
            setSummary('disk', formatGb(disk));
            setSummary('base-price', formatToman(pricing.basePrice));
            setSummary(
                'extra-price',
                extraSlots > 0
                    ? numberFormatter.format(extraSlots) + ' × ' + tomanFormatter.format(pricing.extraSlotPrice) +
                        ' = ' + formatToman(extraPrice)
                    : 'بدون اسلات اضافه'
            );
            setSummary(
                'ram-price',
                ramPrice > 0 ? '+ ' + formatToman(ramPrice) : 'بدون هزینه اضافه'
            );
            setSummary('total-price', formatToman(totalPrice) + ' / ماه');
        }

        [playersInput, memoryInput, cpuInput, diskInput].forEach(function (input) {
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });

        update();
        return true;
    }

    function ensureInsertionObserver() {
        if (
            !document.body ||
            !('MutationObserver' in window) ||
            window.__fragCityMinecraftStoreObserver
        ) {
            return;
        }

        const observer = new MutationObserver(function () {
            const root = document.querySelector('.mc-store-page');
            if (!root || root.dataset.initialized === '1') return;
            initMinecraftStorePage();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        window.__fragCityMinecraftStoreObserver = observer;
    }

    function tryInitializeWhenReady() {
        initMinecraftStorePage();
        ensureInsertionObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInitializeWhenReady, { once: true });
    } else {
        tryInitializeWhenReady();
    }

    window.addEventListener('content-loaded', function (event) {
        if (!event.detail || event.detail.page === 'buyserveropen') {
            tryInitializeWhenReady();
        }
    });
})();