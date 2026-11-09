(function () {
    'use strict';

    const ROOT_SELECTOR = '.server-store-page';

    function initServerStorePage() {
        const root = document.querySelector(ROOT_SELECTOR);
        if (!root || root.dataset.serverStoreInitialized === '1') return false;

        const memoryInput = root.querySelector('#serverStoreMemory');
        const diskInput = root.querySelector('#serverStoreDisk');
        const cpuInput = root.querySelector('#serverStoreCpu');
        const playersInput = root.querySelector('#serverStorePlayers');
        const capacityPricingPanel = root.querySelector('.server-store-capacity-panel');

        // These are the only fields required to bring up the sliders and price
        // calculator. Optional configuration fields must never disable the
        // entire storefront when HTML and JS are deployed at slightly different times.
        const required = {
            memoryInput,
            diskInput,
            cpuInput,
            playersInput,
            capacityPricingPanel
        };
        const missing = Object.keys(required).filter(function (key) {
            return !required[key];
        });

        if (missing.length) {
            const missingSignature = missing.join(',');
            if (root.dataset.serverStoreMissingElements !== missingSignature) {
                console.error('[FragCity] Server store initialization is waiting for required elements:', missing);
                root.dataset.serverStoreMissingElements = missingSignature;
            }
            return false;
        }

        delete root.dataset.serverStoreMissingElements;

        const memoryOutput = root.querySelector('#serverStoreMemoryValue');
        const diskOutput = root.querySelector('#serverStoreDiskValue');
        const cpuOutput = root.querySelector('#serverStoreCpuValue');
        const playersOutput = root.querySelector('#serverStorePlayersValue');

        const mapInput = root.querySelector('#serverStoreMap');
        const serverNameInput = root.querySelector('#serverStoreName');
        const playerPresetButtons = Array.from(root.querySelectorAll('[data-player-preset]'));
        const tickrateSelect = root.querySelector('#serverStoreTickrate');
        const execSelect = root.querySelector('#serverStoreExec');
        const mapgroupSelect = root.querySelector('#serverStoreMapgroup');
        const cooldownSelect = root.querySelector('#serverStoreCooldown');
        const guardSelect = root.querySelector('#serverStoreGuard');
        const guardMinutesField = root.querySelector('#serverStoreGuardMinutesField');
        const guardMinutesInput = root.querySelector('#serverStoreGuardMinutes');
        const durationInputs = Array.from(root.querySelectorAll('input[name="duration_days"]'));
        const durationPriceOutputs = Array.from(root.querySelectorAll('[data-duration-price]'));

        const capacityPricing = {
            baseSlots: Number(capacityPricingPanel.dataset.baseSlots),
            basePrice: Number(capacityPricingPanel.dataset.basePrice),
            extraSlotPrice: Number(capacityPricingPanel.dataset.extraSlotPrice),
            priceRoundingStep: Number(capacityPricingPanel.dataset.priceRoundingStep || 100),
            // Data attributes ending in digits retain the hyphen in DOMStringMap.
            // Read them by their exact HTML attribute names to avoid NaN pricing.
            ramPrices: {
                1024: Number(capacityPricingPanel.getAttribute('data-ram-price-1024')),
                1280: Number(capacityPricingPanel.getAttribute('data-ram-price-1280')),
                1536: Number(capacityPricingPanel.getAttribute('data-ram-price-1536'))
            }
        };

        const invalidPricing =
            !Number.isInteger(capacityPricing.baseSlots) ||
            !Number.isFinite(capacityPricing.basePrice) ||
            !Number.isFinite(capacityPricing.extraSlotPrice) ||
            !Number.isInteger(capacityPricing.priceRoundingStep) ||
            capacityPricing.baseSlots < 1 ||
            capacityPricing.basePrice < 0 ||
            capacityPricing.extraSlotPrice < 0 ||
            capacityPricing.priceRoundingStep < 1 ||
            Object.values(capacityPricing.ramPrices).some(function (price) {
                return !Number.isFinite(price) || price < 0;
            });

        if (invalidPricing) {
            console.error('[FragCity] Server store pricing data is missing or invalid.');
            return false;
        }

        // Mark the page only after its essential DOM and pricing data pass validation.
        // A failed/partial render can therefore be retried on the next content-loaded event.
        root.dataset.serverStoreInitialized = '1';

        const tomanFormatter = new Intl.NumberFormat('fa-IR');
        const numberFormatter = new Intl.NumberFormat('fa-IR', {
            maximumFractionDigits: 2
        });

        function formatToman(amount) {
            const safeAmount = Number.isFinite(Number(amount))
                ? Math.max(0, Math.round(Number(amount)))
                : 0;
            return tomanFormatter.format(safeAmount) + ' تومان';
        }

        function formatGigabytes(valueInMb) {
            const gigabytes = Number(valueInMb) / 1024;
            return Number(gigabytes.toFixed(2)).toString() + ' GB';
        }

        function setSummary(key, value) {
            const target = root.querySelector('[data-server-summary="' + key + '"]');
            if (target) target.textContent = String(value ?? '');
        }

        function readRangeValue(input, fallback) {
            const rawValue = Number(input.value);
            const value = Number.isFinite(rawValue) ? rawValue : fallback;
            const min = Number(input.min);
            const max = Number(input.max);

            if (!Number.isFinite(min) || !Number.isFinite(max) || max < min) {
                return value;
            }

            return Math.min(max, Math.max(min, value));
        }

        function roundToPricingStep(amount) {
            return Math.round(amount / capacityPricing.priceRoundingStep)
                * capacityPricing.priceRoundingStep;
        }

        function syncRangeProgress(input) {
            if (!input) return;

            const min = Number(input.min);
            const max = Number(input.max);
            const value = Number(input.value);
            const progress = Number.isFinite(min) && Number.isFinite(max) && max > min
                ? ((value - min) / (max - min)) * 100
                : 0;

            input.style.setProperty('--range-progress', Math.min(100, Math.max(0, progress)) + '%');
        }

        const allMapGroups = mapgroupSelect
            ? Array.from(mapgroupSelect.options).map(function (option) {
                return {
                    value: option.value,
                    label: option.textContent.trim(),
                    configs: (option.dataset.gameModes || '').split('|').filter(Boolean)
                };
            })
            : [];

        function syncMapGroups(preferredValue) {
            if (!execSelect || !mapgroupSelect) return;

            const selectedMode = execSelect.selectedOptions[0];
            const allowedValues = (selectedMode?.dataset.mapGroups || '')
                .split('|')
                .filter(Boolean);

            const availableGroups = allMapGroups.filter(function (group) {
                return group.configs.includes(execSelect.value) &&
                    allowedValues.includes(group.value);
            });

            const requestedValue = preferredValue || mapgroupSelect.value;
            mapgroupSelect.replaceChildren();

            if (!availableGroups.length) {
                const fallback = document.createElement('option');
                fallback.value = '';
                fallback.textContent = 'برای این CFG گروه نقشه‌ای تعریف نشده است';
                fallback.disabled = true;
                fallback.selected = true;
                mapgroupSelect.appendChild(fallback);
                mapgroupSelect.disabled = true;
                return;
            }

            availableGroups.forEach(function (group) {
                const option = document.createElement('option');
                option.value = group.value;
                option.textContent = group.label;
                mapgroupSelect.appendChild(option);
            });

            mapgroupSelect.disabled = false;
            mapgroupSelect.value = availableGroups.some(function (group) {
                return group.value === requestedValue;
            }) ? requestedValue : availableGroups[0].value;
        }

        function validateLocalFields() {
            if (mapInput) {
                const mapValue = mapInput.value.trim();
                mapInput.setCustomValidity(
                    /^[A-Za-z0-9_-]+$/.test(mapValue)
                        ? ''
                        : 'فقط حروف انگلیسی، عدد، خط تیره و زیرخط مجاز است.'
                );
            }

            if (serverNameInput) {
                const serverName = serverNameInput.value.trim();
                serverNameInput.setCustomValidity(
                    serverName.length > 0 && /^[A-Za-z0-9 ]+$/.test(serverName)
                        ? ''
                        : 'نام سرور الزامی است و فقط می‌تواند شامل حروف انگلیسی، عدد و فاصله باشد.'
                );
            }

            const players = Number(playersInput.value);
            playersInput.setCustomValidity(
                Number.isInteger(players) && players >= 5 && players <= 64
                    ? ''
                    : 'ظرفیت باید یک عدد صحیح بین 5 تا 64 باشد.'
            );

            if (guardMinutesInput) {
                const guardMinutes = Number(guardMinutesInput.value);
                const guardEnabled = Boolean(guardSelect && guardSelect.value === '1');
                guardMinutesInput.setCustomValidity(
                    !guardEnabled || (Number.isInteger(guardMinutes) && guardMinutes >= 1)
                        ? ''
                        : 'مدت مسدودسازی باید حداقل یک دقیقه باشد.'
                );
            }
        }

        function updateSummary() {
            validateLocalFields();

            const players = readRangeValue(playersInput, capacityPricing.baseSlots);
            const memoryMb = readRangeValue(memoryInput, 1024);
            const diskMb = readRangeValue(diskInput, 10240);
            const cpuPercent = readRangeValue(cpuInput, 100);
            const cpuVcores = cpuPercent / 100;
            const cpuUnit = cpuVcores === 1 ? 'vCore' : 'vCores';

            if (playersOutput) playersOutput.textContent = String(players);
            if (memoryOutput) memoryOutput.textContent = formatGigabytes(memoryMb);
            if (diskOutput) diskOutput.textContent = formatGigabytes(diskMb);
            if (cpuOutput) {
                cpuOutput.textContent = cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit;
            }

            [memoryInput, diskInput, cpuInput, playersInput].forEach(syncRangeProgress);

            playerPresetButtons.forEach(function (button) {
                const selected = Number(button.dataset.playerPreset) === players;
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            setSummary('memory', formatGigabytes(memoryMb) + ' · ' + memoryMb + ' MB');
            setSummary('disk', formatGigabytes(diskMb) + ' · ' + diskMb + ' MB');
            setSummary('cpu', cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit);
            setSummary('players', players + ' اسلات');

            const mapValue = mapInput ? mapInput.value.trim() : '';
            const serverName = serverNameInput ? serverNameInput.value.trim() : '';
            const tickrate = tickrateSelect ? tickrateSelect.value : '64';
            const modeLabel = execSelect?.selectedOptions[0]?.textContent.trim() || '—';
            const mapgroupLabel = mapgroupSelect?.selectedOptions[0]?.textContent.trim() || '—';
            const cooldownLabel = cooldownSelect?.selectedOptions[0]?.textContent.trim() || '—';
            const guardEnabled = Boolean(guardSelect && guardSelect.value === '1');
            const guardMinutes = guardMinutesInput ? guardMinutesInput.value.trim() : '';

            setSummary('map', mapValue || '—');
            setSummary('server-name', serverName || 'وارد نشده');

            const extraSlots = Math.max(0, players - capacityPricing.baseSlots);
            const extraSlotsPrice = extraSlots * capacityPricing.extraSlotPrice;
            const capacityPrice = capacityPricing.basePrice + extraSlotsPrice;
            const ramSurcharge = capacityPricing.ramPrices[memoryMb] ?? 0;
            const monthlyPrice = capacityPrice + ramSurcharge;

            const selectedDurationInput = durationInputs.find(function (input) {
                return input.checked;
            }) || durationInputs.find(function (input) {
                return Number(input.value) === 30;
            }) || null;

            const durationDays = selectedDurationInput
                ? Math.max(1, Number(selectedDurationInput.value) || 30)
                : 30;
            const rawPriceFactor = selectedDurationInput
                ? Number(selectedDurationInput.dataset.priceFactor)
                : 1;
            const selectedPriceFactor = Number.isFinite(rawPriceFactor) && rawPriceFactor > 0
                ? rawPriceFactor
                : durationDays / 30;
            const discountPercent = selectedDurationInput
                ? Math.max(0, Number(selectedDurationInput.dataset.discountPercent) || 0)
                : 0;
            const durationLabel = selectedDurationInput?.dataset.durationLabel || '۳۰ روزه';

            const periodPrice = roundToPricingStep(monthlyPrice * selectedPriceFactor);
            const undiscountedPeriodPrice = monthlyPrice * (durationDays / 30);
            const discountAmount = discountPercent > 0
                ? Math.max(0, undiscountedPeriodPrice - periodPrice)
                : 0;

            durationPriceOutputs.forEach(function (output) {
                const outputFactor = Number(output.dataset.priceFactor);
                const outputDays = Number(output.dataset.durationPrice);
                const priceFactor = Number.isFinite(outputFactor) && outputFactor > 0
                    ? outputFactor
                    : (Number.isFinite(outputDays) && outputDays > 0 ? outputDays / 30 : 1);
                output.textContent = formatToman(roundToPricingStep(monthlyPrice * priceFactor));
            });

            root.querySelectorAll('[data-duration-caption]').forEach(function (caption) {
                const days = Number(caption.dataset.durationCaption);
                if (days > 30) {
                    const months = days / 30;
                    const cardInput = caption.closest('.server-store-duration-card')
                        ?.querySelector('input[name="duration_days"]');
                    const rawFactor = Number(cardInput?.dataset.priceFactor);
                    const factor = Number.isFinite(rawFactor) && rawFactor > 0
                        ? rawFactor
                        : months;
                    const total = roundToPricingStep(monthlyPrice * factor);
                    const monthlyEquivalent = roundToPricingStep(total / months);
                    caption.textContent = 'ماهی ' + formatToman(monthlyEquivalent);
                } else if (days === 30) {
                    caption.textContent = 'قیمت پایه ماهانه';
                }
            });

            setSummary('duration', durationLabel + ' · ' + durationDays + ' روز');
            setSummary(
                'discount',
                discountPercent > 0
                    ? discountPercent + '٪ · ' + formatToman(discountAmount) + ' صرفه‌جویی'
                    : 'بدون تخفیف'
            );
            setSummary('capacity-base-price', formatToman(capacityPricing.basePrice));
            setSummary(
                'extra-slots-price',
                extraSlots > 0
                    ? extraSlots + ' × ' + tomanFormatter.format(capacityPricing.extraSlotPrice) +
                        ' = ' + formatToman(extraSlotsPrice)
                    : 'بدون اسلات اضافه'
            );
            setSummary(
                'memory-price',
                ramSurcharge > 0 ? '+ ' + formatToman(ramSurcharge) : 'بدون هزینه اضافه'
            );
            setSummary('monthly-price', formatToman(monthlyPrice) + ' / ماه');
            setSummary('period-price', formatToman(periodPrice));
            setSummary('tickrate', tickrate === '128' ? '128 Tick · آزمایشی / نیازمند تأیید نود' : '64 Tick · Subtick فعال');
            setSummary('exec', modeLabel);
            setSummary('mapgroup', mapgroupLabel);
            setSummary('cooldown', cooldownLabel);
            setSummary('guard', guardEnabled ? 'فعال · افزونه پولی' : 'غیرفعال');
            setSummary(
                'guard-minutes',
                guardEnabled
                    ? (guardMinutes ? guardMinutes + ' دقیقه' : 'تعیین نشده')
                    : 'فقط هنگام فعال بودن'
            );

            if (guardMinutesField) guardMinutesField.hidden = !guardEnabled;
            if (guardMinutesInput) guardMinutesInput.disabled = !guardEnabled;
        }

        // Bind each input directly. This avoids any dependency on bubbling
        // through the form/layout wrappers and keeps every slider responsive.
        const liveControls = [
            memoryInput,
            diskInput,
            cpuInput,
            playersInput,
            mapInput,
            serverNameInput,
            tickrateSelect,
            execSelect,
            mapgroupSelect,
            cooldownSelect,
            guardSelect,
            guardMinutesInput,
            ...durationInputs
        ].filter(Boolean);

        liveControls.forEach(function (control) {
            control.addEventListener('input', function () {
                if (control === execSelect && mapgroupSelect) {
                    syncMapGroups(mapgroupSelect.value);
                }
                updateSummary();
            });

            control.addEventListener('change', function () {
                if (control === execSelect && mapgroupSelect) {
                    syncMapGroups(mapgroupSelect.value);
                }
                updateSummary();
            });
        });

        playerPresetButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const requestedCapacity = Number(button.dataset.playerPreset);
                if (!Number.isInteger(requestedCapacity) || requestedCapacity < 5 || requestedCapacity > 64) {
                    return;
                }

                playersInput.value = String(requestedCapacity);
                updateSummary();
            });
        });

        const gameTabButtons = Array.from(root.querySelectorAll('[data-server-game-tab]'));
        const gamePanels = Array.from(root.querySelectorAll('[data-server-game-panel]'));

        gameTabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const selectedGame = button.dataset.serverGameTab;

                gameTabButtons.forEach(function (tab) {
                    const selected = tab === button;
                    tab.classList.toggle('is-active', selected);
                    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                    tab.tabIndex = selected ? 0 : -1;
                });

                gamePanels.forEach(function (panel) {
                    panel.hidden = panel.dataset.serverGamePanel !== selectedGame;
                });
            });
        });

        const configForm = root.querySelector('#serverStoreConfigForm');
        if (configForm && configForm.dataset.serverStoreSubmitBound !== '1') {
            configForm.dataset.serverStoreSubmitBound = '1';
            configForm.addEventListener('submit', function (event) {
                event.preventDefault();
            });
        }

        if (execSelect && mapgroupSelect) {
            const initialGroup = mapgroupSelect.value || 'mg_comp';
            syncMapGroups(initialGroup);
        }

        try {
            updateSummary();
            root.dataset.serverStoreStatus = 'ready';
            delete root.dataset.serverStoreError;
        } catch (error) {
            root.dataset.serverStoreStatus = 'error';
            root.dataset.serverStoreError = String(error && error.message ? error.message : error);
            console.error('[FragCity] Server store invoice update failed:', error);
            return false;
        }

        return true;
    }

    window.initBuyServerPage = initServerStorePage;

    function ensureInsertionObserver() {
        if (
            !document.body ||
            !('MutationObserver' in window) ||
            window.__fragCityBuyStoreObserver
        ) {
            return;
        }

        // Keep observing across AJAX page swaps. The same JS file is loaded only
        // once, while each newly inserted storefront root needs its own bindings.
        const observer = new MutationObserver(function () {
            const root = document.querySelector(ROOT_SELECTOR);
            if (!root || root.dataset.serverStoreInitialized === '1') return;
            initServerStorePage();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        window.__fragCityBuyStoreObserver = observer;
    }

    function tryInitializeWhenReady() {
        initServerStorePage();
        ensureInsertionObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInitializeWhenReady, { once: true });
    } else {
        tryInitializeWhenReady();
    }

    window.addEventListener('content-loaded', function () {
        tryInitializeWhenReady();
    });
})();