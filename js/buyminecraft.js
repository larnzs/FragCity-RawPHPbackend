(function () {
    'use strict';

    function initMinecraftStorePage() {
        const root = document.querySelector('.mc-store-page');
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const pricingNode = root.querySelector('#mcStorePricingData');
        const playersInput = root.querySelector('#mcStorePlayers');
        const memoryInput = root.querySelector('#mcStoreMemory');
        const cpuInput = root.querySelector('#mcStoreCpu');
        const diskInput = root.querySelector('#mcStoreDisk');
        if (!pricingNode || !playersInput || !memoryInput || !cpuInput || !diskInput) return;

        const pricing = {
            baseSlots: Number(pricingNode.dataset.baseSlots),
            basePrice: Number(pricingNode.dataset.basePrice),
            extraSlotPrice: Number(pricingNode.dataset.extraSlotPrice),
            ramPrices: {
                1024: Number(pricingNode.dataset.ramPrice1024),
                1280: Number(pricingNode.dataset.ramPrice1280),
                1536: Number(pricingNode.dataset.ramPrice1536)
            }
        };

        if (
            !Number.isFinite(pricing.baseSlots) ||
            !Number.isFinite(pricing.basePrice) ||
            !Number.isFinite(pricing.extraSlotPrice) ||
            Object.values(pricing.ramPrices).some(function (value) { return !Number.isFinite(value); })
        ) {
            console.error('[FragCity] Minecraft pricing configuration is invalid.');
            return;
        }

        const tomanFormatter = new Intl.NumberFormat('fa-IR');
        const numberFormatter = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });

        function formatToman(value) {
            return tomanFormatter.format(Math.max(0, Math.round(value))) + ' تومان';
        }

        function formatGb(megabytes) {
            return numberFormatter.format(Number(megabytes) / 1024) + ' گیگابایت';
        }

        function setSummary(name, value) {
            const target = root.querySelector('[data-mc-summary="' + name + '"]');
            if (target) target.textContent = value;
        }

        function syncRangeProgress(input) {
            const min = Number(input.min);
            const max = Number(input.max);
            const value = Number(input.value);
            const progress = max > min ? ((value - min) / (max - min)) * 100 : 0;
            input.style.setProperty('--mc-range-progress', progress + '%');
        }

        function update() {
            const players = Math.max(pricing.baseSlots, Math.min(64, Number(playersInput.value) || pricing.baseSlots));
            const memory = Number(memoryInput.value);
            const cpu = Number(cpuInput.value);
            const disk = Number(diskInput.value);
            const extraSlots = Math.max(0, players - pricing.baseSlots);
            const extraPrice = extraSlots * pricing.extraSlotPrice;
            const ramPrice = pricing.ramPrices[memory];
            const totalPrice = pricing.basePrice + extraPrice + (Number.isFinite(ramPrice) ? ramPrice : 0);

            const playersOutput = root.querySelector('#mcStorePlayersValue');
            if (playersOutput) playersOutput.textContent = numberFormatter.format(players);

            syncRangeProgress(playersInput);
            setSummary('players', numberFormatter.format(players) + ' بازیکن');
            setSummary('memory', formatGb(memory));
            setSummary('cpu', numberFormatter.format(cpu / 100) + ' vCore');
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
    }

    document.addEventListener('DOMContentLoaded', initMinecraftStorePage);
    window.addEventListener('content-loaded', function (event) {
        if (!event.detail || event.detail.page === 'minecraft') {
            initMinecraftStorePage();
        }
    });
})();
