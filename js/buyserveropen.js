(function () {
    'use strict';

    function initServerStorePage() {
        const root = document.querySelector('.server-store-page');
        if (!root || root.dataset.serverStoreInitialized === '1') return;
        root.dataset.serverStoreInitialized = '1';

        const memoryInput = root.querySelector('#serverStoreMemory');
        const diskInput = root.querySelector('#serverStoreDisk');
        const cpuInput = root.querySelector('#serverStoreCpu');
        const memoryOutput = root.querySelector('#serverStoreMemoryValue');
        const diskOutput = root.querySelector('#serverStoreDiskValue');
        const cpuOutput = root.querySelector('#serverStoreCpuValue');
        const mapInput = root.querySelector('#serverStoreMap');
        const playersInput = root.querySelector('#serverStorePlayers');
        const playersOutput = root.querySelector('#serverStorePlayersValue');
        const playerPresetButtons = Array.from(root.querySelectorAll('[data-player-preset]'));
        const capacityPricingPanel = root.querySelector('.server-store-capacity-panel');
        const tickrateSelect = root.querySelector('#serverStoreTickrate');
        const execSelect = root.querySelector('#serverStoreExec');
        const mapgroupSelect = root.querySelector('#serverStoreMapgroup');
        const cooldownSelect = root.querySelector('#serverStoreCooldown');
        const guardSelect = root.querySelector('#serverStoreGuard');
        const guardMinutesField = root.querySelector('#serverStoreGuardMinutesField');
        const guardMinutesInput = root.querySelector('#serverStoreGuardMinutes');
        const durationInputs = Array.from(root.querySelectorAll('input[name="duration_days"]'));
        const durationPriceOutputs = Array.from(root.querySelectorAll('[data-duration-price]'));

        if (
            !memoryInput || !diskInput || !cpuInput ||
            !memoryOutput || !diskOutput || !cpuOutput ||
            !mapInput || !playersInput || !playersOutput || !capacityPricingPanel || !tickrateSelect || !execSelect ||
            !mapgroupSelect || !cooldownSelect || !guardSelect ||
            !guardMinutesField || !guardMinutesInput || durationInputs.length === 0
        ) {
            root.dataset.serverStoreInitialized = '0';
            return;
        }

        const capacityPricing = {
            baseSlots: Number(capacityPricingPanel.dataset.baseSlots),
            basePrice: Number(capacityPricingPanel.dataset.basePrice),
            extraSlotPrice: Number(capacityPricingPanel.dataset.extraSlotPrice),
            priceRoundingStep: Number(capacityPricingPanel.dataset.priceRoundingStep || 100),
            ramPrices: {
                1024: Number(capacityPricingPanel.dataset.ramPrice1024),
                1280: Number(capacityPricingPanel.dataset.ramPrice1280),
                1536: Number(capacityPricingPanel.dataset.ramPrice1536)
            }
        };

        if (
            !Number.isInteger(capacityPricing.baseSlots) ||
            !Number.isFinite(capacityPricing.basePrice) ||
            !Number.isFinite(capacityPricing.extraSlotPrice) ||
            capacityPricing.baseSlots < 1 ||
            capacityPricing.basePrice < 0 ||
            capacityPricing.extraSlotPrice < 0 ||
            !Number.isInteger(capacityPricing.priceRoundingStep) ||
            capacityPricing.priceRoundingStep < 1 ||
            Object.values(capacityPricing.ramPrices).some(function (price) {
                return !Number.isFinite(price) || price < 0;
            })
        ) {
            root.dataset.serverStoreInitialized = '0';
            return;
        }

        const tomanFormatter = new Intl.NumberFormat('fa-IR');
        function formatToman(amount) {
            return tomanFormatter.format(amount) + ' تومان';
        }

        const allMapGroups = Array.from(mapgroupSelect.options).map(function (option) {
            return {
                value: option.value,
                label: option.textContent.trim(),
                configs: (option.dataset.gameModes || '').split('|').filter(Boolean)
            };
        });

        function setSummary(key, value) {
            const target = root.querySelector('[data-server-summary="' + key + '"]');
            if (target) target.textContent = value;
        }

        function syncMapGroups(preferredValue) {
            const selectedMode = execSelect.selectedOptions[0];
            const allowedValues = (selectedMode?.dataset.mapGroups || '')
                .split('|')
                .filter(Boolean);
            const availableGroups = allMapGroups.filter(function (group) {
                return group.configs.includes(execSelect.value)
                    && allowedValues.includes(group.value);
            });

            mapgroupSelect.replaceChildren();

            availableGroups.forEach(function (group) {
                const option = document.createElement('option');
                option.value = group.value;
                option.textContent = group.label;
                mapgroupSelect.appendChild(option);
            });

            if (availableGroups.length === 0) {
                const fallback = document.createElement('option');
                fallback.value = '';
                fallback.textContent = 'برای این CFG گروه نقشه‌ای تعریف نشده است';
                fallback.disabled = true;
                fallback.selected = true;
                mapgroupSelect.appendChild(fallback);
                mapgroupSelect.disabled = true;
                return;
            }

            mapgroupSelect.disabled = false;
            const requestedValue = preferredValue || mapgroupSelect.value;
            mapgroupSelect.value = availableGroups.some(function (group) {
                return group.value === requestedValue;
            }) ? requestedValue : availableGroups[0].value;
        }

        function validateLocalFields() {
            const mapValue = mapInput.value.trim();
            mapInput.setCustomValidity(
                /^[A-Za-z0-9_-]+$/.test(mapValue)
                    ? ''
                    : 'فقط حروف انگلیسی، عدد، خط تیره و زیرخط مجاز است.'
            );

            const players = Number(playersInput.value);
            const validPlayers = Number.isInteger(players) && players >= 5 && players <= 64;
            playersInput.setCustomValidity(
                validPlayers ? '' : 'ظرفیت باید یک عدد صحیح بین 5 تا 64 باشد.'
            );

            const guardMinutes = Number(guardMinutesInput.value);
            guardMinutesInput.setCustomValidity(
                !guardSelect.value || guardSelect.value === '0' ||
                (Number.isInteger(guardMinutes) && guardMinutes >= 1)
                    ? ''
                    : 'مدت مسدودسازی باید حداقل یک دقیقه باشد.'
            );
        }

        function formatGigabytes(valueInMb) {
            const gigabytes = valueInMb / 1024;
            const formatted = Number(gigabytes.toFixed(2)).toString();
            return formatted + ' GB';
        }

        function syncRangeProgress(input) {
            const min = Number(input.min);
            const max = Number(input.max);
            const value = Number(input.value);
            const progress = max > min ? ((value - min) / (max - min)) * 100 : 0;
            input.style.setProperty('--range-progress', progress + '%');
        }

        function updateSummary() {
            validateLocalFields();

            const memoryMb = Number(memoryInput.value);
            const diskMb = Number(diskInput.value);
            const cpuPercent = Number(cpuInput.value);
            const cpuVcores = cpuPercent / 100;
            const cpuUnit = cpuVcores === 1 ? 'vCore' : 'vCores';

            memoryOutput.textContent = formatGigabytes(memoryMb);
            diskOutput.textContent = formatGigabytes(diskMb);
            cpuOutput.textContent = cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit;

            syncRangeProgress(memoryInput);
            syncRangeProgress(diskInput);
            syncRangeProgress(cpuInput);
            syncRangeProgress(playersInput);
            playersOutput.textContent = String(Number(playersInput.value));
            playerPresetButtons.forEach(function (button) {
                const selected = Number(button.dataset.playerPreset) === Number(playersInput.value);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            setSummary('memory', formatGigabytes(memoryMb) + ' · ' + memoryMb + ' MB');
            setSummary('disk', formatGigabytes(diskMb) + ' · ' + diskMb + ' MB');
            setSummary('cpu', cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit);

            const mapValue = mapInput.value.trim();
            const playersValue = playersInput.value.trim();
            const tickrate = tickrateSelect.value;
            const modeLabel = execSelect.selectedOptions[0]?.textContent.trim() || '—';
            const mapgroupLabel = mapgroupSelect.selectedOptions[0]?.textContent.trim() || '—';
            const cooldownLabel = cooldownSelect.selectedOptions[0]?.textContent.trim() || '—';
            const guardEnabled = guardSelect.value === '1';
            const guardMinutes = guardMinutesInput.value.trim();

            setSummary('map', mapValue || '—');
            setSummary('players', playersValue ? playersValue + ' اسلات' : '—');

            const selectedSlots = Number(playersValue);
            const extraSlots = Math.max(0, selectedSlots - capacityPricing.baseSlots);
            const extraSlotsPrice = extraSlots * capacityPricing.extraSlotPrice;
            const capacityPrice = capacityPricing.basePrice + extraSlotsPrice;
            const ramSurcharge = capacityPricing.ramPrices[memoryMb];
            const monthlyPrice = capacityPrice + ramSurcharge;
            const selectedDurationInput = durationInputs.find(function (input) {
                return input.checked;
            });
            const durationDays = Number(selectedDurationInput ? selectedDurationInput.value : 30);
            const periodPrice = Math.round(
                (monthlyPrice * durationDays / 30) / capacityPricing.priceRoundingStep
            ) * capacityPricing.priceRoundingStep;

            durationPriceOutputs.forEach(function (output) {
                const days = Number(output.dataset.durationPrice);
                const price = Math.round(
                    (monthlyPrice * days / 30) / capacityPricing.priceRoundingStep
                ) * capacityPricing.priceRoundingStep;
                output.textContent = formatToman(price);
            });

            setSummary('duration', durationDays + ' روز');
            setSummary('capacity-base-price', formatToman(capacityPricing.basePrice));
            setSummary(
                'extra-slots-price',
                extraSlots > 0
                    ? extraSlots + ' × ' + tomanFormatter.format(capacityPricing.extraSlotPrice) +
                        ' = ' + formatToman(extraSlotsPrice)
                    : 'بدون اسلات اضافه'
            );
            setSummary('memory-price', ramSurcharge > 0 ? '+ ' + formatToman(ramSurcharge) : 'بدون هزینه اضافه');
            setSummary('monthly-price', formatToman(monthlyPrice) + ' / ماه');
            setSummary('period-price', formatToman(periodPrice));
            setSummary('tickrate', tickrate === '128' ? '128 Tick · به‌زودی' : '64 Tick');
            setSummary('exec', modeLabel);
            setSummary('mapgroup', mapgroupLabel);
            setSummary('cooldown', cooldownLabel);
            setSummary('guard', guardEnabled ? 'فعال · رایگان' : 'غیرفعال');
            setSummary(
                'guard-minutes',
                guardEnabled ? (guardMinutes ? guardMinutes + ' دقیقه' : 'تعیین نشده') : 'فقط هنگام فعال بودن'
            );

            guardMinutesField.hidden = !guardEnabled;
            guardMinutesInput.disabled = !guardEnabled;
        }

        root.addEventListener('input', function (event) {
            if (
                event.target === memoryInput ||
                event.target === diskInput ||
                event.target === cpuInput ||
                event.target === mapInput ||
                event.target === playersInput ||
                event.target === guardMinutesInput
            ) {
                updateSummary();
            }
        });

        root.addEventListener('click', function (event) {
            const presetButton = event.target.closest('[data-player-preset]');
            if (!presetButton || !root.contains(presetButton)) return;

            const requestedCapacity = Number(presetButton.dataset.playerPreset);
            if (!Number.isInteger(requestedCapacity) || requestedCapacity < 5 || requestedCapacity > 64) return;

            playersInput.value = String(requestedCapacity);
            updateSummary();
        });

        root.addEventListener('change', function (event) {
            if (event.target === execSelect) {
                const currentGroup = mapgroupSelect.value;
                syncMapGroups(currentGroup);
            }
            updateSummary();
        });

        const initialGroup = mapgroupSelect.value || 'mg_comp';
        syncMapGroups(initialGroup);
        updateSummary();

        const configForm = root.querySelector('#serverStoreConfigForm');
        if (configForm) {
            configForm.addEventListener('submit', function (event) {
                event.preventDefault();
            });
        }
    }

    window.initBuyServerPage = initServerStorePage;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServerStorePage, { once: true });
    } else {
        initServerStorePage();
    }

    window.addEventListener('content-loaded', function (event) {
        if (!event.detail || event.detail.page === 'buyserveropen') {
            initServerStorePage();
        }
    });
})();