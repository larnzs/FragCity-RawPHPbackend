(function () {
    'use strict';

    const ROOT_SELECTOR = '.server-store-page';
    const SESSION_ORDER_KEY = 'fragcity.cs2.checkout';

    function initServerStorePage() {
        const root = document.querySelector(ROOT_SELECTOR);
        if (!root || root.dataset.serverStoreInitialized === '1') return false;

        const form = root.querySelector('#serverStoreConfigForm');
        const memoryInput = root.querySelector('#serverStoreMemory');
        const diskInput = root.querySelector('#serverStoreDisk');
        const cpuInput = root.querySelector('#serverStoreCpu');
        const playersInput = root.querySelector('#serverStorePlayers');
        const capacityPanel = root.querySelector('.server-store-capacity-panel');
        const submitButton = root.querySelector('#serverStoreSubmit');
        const quoteStatus = root.querySelector('#serverStoreQuoteStatus');
        const quoteLines = root.querySelector('#serverStoreQuoteLines');

        const required = { form, memoryInput, diskInput, cpuInput, playersInput, capacityPanel, submitButton, quoteStatus };
        const missing = Object.keys(required).filter(function (key) { return !required[key]; });
        if (missing.length) {
            const signature = missing.join(',');
            if (root.dataset.serverStoreMissingElements !== signature) {
                console.error('[FragCity] Server store is waiting for required elements:', missing);
                root.dataset.serverStoreMissingElements = signature;
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
        const paymentInputs = Array.from(root.querySelectorAll('input[name="payment_method"]'));
        const walletBalanceOutput = root.querySelector('#serverStoreWalletBalance');

        const apiUrl = root.dataset.apiUrl || '/api/server-store.php';
        const csrfToken = root.dataset.csrfToken || '';
        const authenticated = root.dataset.authenticated === '1';
        const tomanFormatter = new Intl.NumberFormat('fa-IR');
        const numberFormatter = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });

        let currentQuote = null;
        let catalogLoaded = false;
        let catalogFailed = false;
        let currentQuoteKey = '';
        let quoteSequence = 0;
        let quoteTimer = null;
        let quoteBusy = false;
        let checkoutBusy = false;
        let checkoutCompleted = false;
        let checkoutLocked = false;
        let walletEnabled = false;
        let walletBalanceToman = null;
        let checkoutRef = '';
        let checkoutSelectionKey = '';
        let activeQuoteController = null;

        function formatToman(amount) {
            const value = Number(amount);
            if (!Number.isFinite(value) || value < 0) return '—';
            return tomanFormatter.format(Math.round(value)) + ' تومان';
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
            if (input && input.dataset.catalogValues) {
                try {
                    const values = JSON.parse(input.dataset.catalogValues);
                    const index = Number(input.value);
                    if (Array.isArray(values) && Number.isInteger(index) && values[index] !== undefined) {
                        return Number(values[index]);
                    }
                } catch (error) {
                    console.error('[FragCity] Invalid Paymenter option values:', error);
                }
            }
            const value = Number(input.value);
            const min = Number(input.min);
            const max = Number(input.max);
            if (!Number.isFinite(value)) return fallback;
            if (!Number.isFinite(min) || !Number.isFinite(max) || max < min) return value;
            return Math.min(max, Math.max(min, value));
        }

        function setRangeToCatalogValue(input, requestedValue) {
            if (!input || !input.dataset.catalogValues) return false;
            const values = JSON.parse(input.dataset.catalogValues);
            if (!Array.isArray(values) || !values.length) return false;
            const exact = values.findIndex(function (value) { return Number(value) === Number(requestedValue); });
            let index = exact;
            if (index < 0) {
                index = values.reduce(function (bestIndex, value, candidateIndex) {
                    return Math.abs(Number(value) - Number(requestedValue)) < Math.abs(Number(values[bestIndex]) - Number(requestedValue))
                        ? candidateIndex
                        : bestIndex;
                }, 0);
            }
            input.value = String(index);
            return true;
        }

        function normalizeOptionToken(value) {
            return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        function findCatalogOption(options, aliases) {
            const normalizedAliases = aliases.map(normalizeOptionToken);
            const matches = options.filter(function (option) {
                return normalizedAliases.includes(normalizeOptionToken(option.env_variable))
                    || normalizedAliases.includes(normalizeOptionToken(option.name));
            });
            return matches.length === 1 ? matches[0] : null;
        }

        function catalogNumericValues(option) {
            if (!option || !Array.isArray(option.children)) return [];
            return Array.from(new Set(option.children.map(function (child) {
                const raw = child && child.env_variable !== undefined ? String(child.env_variable).trim() : '';
                return /^\d+$/.test(raw) ? Number(raw) : null;
            }).filter(function (value) {
                return Number.isSafeInteger(value) && value > 0;
            }))).sort(function (a, b) { return a - b; });
        }

        function bindRangeToCatalog(input, option, formatter) {
            if (!option || option.upgradable !== true) throw new Error('cs2_config_option_mapping_invalid');
            const values = catalogNumericValues(option);
            if (!values.length) throw new Error('cs2_config_option_mapping_invalid');
            const preferred = Number(input.value);
            input.dataset.catalogValues = JSON.stringify(values);
            input.min = '0';
            input.max = String(values.length - 1);
            input.step = '1';
            setRangeToCatalogValue(input, preferred);
            const meta = input.closest('.server-store-range-wrap')?.querySelector('.server-store-range-meta');
            if (meta && meta.children.length >= 2) {
                meta.children[0].textContent = formatter(values[0]);
                meta.children[meta.children.length - 1].textContent = formatter(values[values.length - 1]);
            }
        }

        function checkoutField(catalog, name) {
            return Array.isArray(catalog.checkout_fields)
                ? catalog.checkout_fields.find(function (field) { return field && field.name === name; })
                : null;
        }

        function checkoutOptionEntries(field) {
            if (!field || field.options === undefined || field.options === null) return [];
            if (Array.isArray(field.options)) {
                return field.options.map(function (option) {
                    return option && typeof option === 'object'
                        ? { value: String(option.value ?? ''), label: String(option.label ?? option.value ?? '') }
                        : { value: String(option), label: String(option) };
                }).filter(function (option) { return option.value !== ''; });
            }
            if (typeof field.options === 'object') {
                return Object.entries(field.options).map(function (entry) {
                    return { value: String(entry[0]), label: String(entry[1]) };
                }).filter(function (option) { return option.value !== ''; });
            }
            return [];
        }

        function replaceSelectOptions(select, entries, defaultValue) {
            if (!select) throw new Error('cs2_checkout_manifest_unavailable');
            const currentValue = select.value;
            select.replaceChildren();
            entries.forEach(function (entry) {
                const option = document.createElement('option');
                option.value = entry.value;
                option.textContent = entry.label;
                select.appendChild(option);
            });
            if (!entries.length) throw new Error('cs2_checkout_manifest_unavailable');
            const preferred = [currentValue, defaultValue].find(function (candidate) {
                return candidate && entries.some(function (entry) { return entry.value === candidate; });
            });
            select.value = preferred || entries[0].value;
            select.disabled = false;
        }

        function applyPaymenterCatalog(catalog) {
            if (!catalog || !Array.isArray(catalog.config_options) || !Array.isArray(catalog.plans)
                || !catalog.game_mode_manager || !Array.isArray(catalog.game_mode_manager.cfg_options)
                || !catalog.game_mode_manager.map_group_options || typeof catalog.game_mode_manager.map_group_options !== 'object') {
                throw new Error('paymenter_invalid_response');
            }
            const options = catalog.config_options;
            const gameModeManifest = catalog.game_mode_manager;
            const cfgOptions = gameModeManifest.cfg_options;
            const mapGroupOptions = gameModeManifest.map_group_options;
            const execField = checkoutField(catalog, 'EXEC');
            const mapGroupField = checkoutField(catalog, 'MAPGROUP');
            const cooldownField = checkoutField(catalog, 'COOLDOWN');
            const mapField = checkoutField(catalog, 'SRCDS_MAP');
            const guardField = checkoutField(catalog, 'ENABLE_GUARD');
            const allowedExecs = new Set(checkoutOptionEntries(execField).map(function (option) { return option.value; }));
            const mapGroupEntries = checkoutOptionEntries(mapGroupField);
            const allowedMapGroups = new Set(mapGroupEntries.map(function (option) { return option.value; }));
            const existingMapLabels = new Map(mapGroupEntries.map(function (option) {
                return [option.value, option.label];
            }));
            const validCfgs = cfgOptions.filter(function (mode) {
                return mode && typeof mode.config === 'string' && typeof mode.name === 'string'
                    && allowedExecs.has(mode.config)
                    && Array.isArray(mode.mapGroups) && mode.mapGroups.length > 0;
            });
            if (!validCfgs.length) throw new Error('cs2_checkout_manifest_unavailable');

            execSelect.replaceChildren();
            validCfgs.forEach(function (mode) {
                const option = document.createElement('option');
                option.value = mode.config;
                option.textContent = mode.name;
                option.dataset.mapGroups = mode.mapGroups.join('|');
                option.dataset.defaultMap = String(mode.defaultMap || '');
                execSelect.appendChild(option);
            });
            execSelect.disabled = false;
            execSelect.value = validCfgs.some(function (mode) { return mode.config === 'comp.cfg'; })
                ? 'comp.cfg'
                : validCfgs[0].config;

            const cfgNameToId = new Map(validCfgs.map(function (mode) {
                return [mode.name, mode.config];
            }));
            allMapGroups = Object.entries(mapGroupOptions).map(function (entry) {
                const groupId = entry[0];
                const labels = Array.isArray(entry[1]) ? entry[1] : [];
                return {
                    value: groupId,
                    label: existingMapLabels.get(groupId) || groupId,
                    configs: labels.map(function (label) { return cfgNameToId.get(label); }).filter(Boolean)
                };
            }).filter(function (group) { return allowedMapGroups.has(group.value) && group.configs.length > 0; });
            if (!allMapGroups.length) throw new Error('cs2_checkout_manifest_unavailable');

            const defaultMode = validCfgs.find(function (mode) { return mode.config === execSelect.value; }) || validCfgs[0];
            const preferredGroup = Array.isArray(defaultMode.mapGroups) && defaultMode.mapGroups.includes('mg_comp')
                ? 'mg_comp'
                : defaultMode.mapGroups[0];
            syncMapGroups(preferredGroup);
            if (mapInput) {
                const defaultMap = String(mapField && mapField.default ? mapField.default : defaultMode.defaultMap || '');
                if (defaultMap && (!mapInput.value.trim() || mapInput.value === 'de_dust2')) {
                    mapInput.value = defaultMap;
                }
            }
            const cooldownOptions = checkoutOptionEntries(cooldownField);
            replaceSelectOptions(cooldownSelect, cooldownOptions, cooldownField && cooldownField.default ? String(cooldownField.default) : '0');
            const guardValues = checkoutOptionEntries(guardField);
            if (!guardValues.some(function (option) { return option.value === '0'; })) {
                throw new Error('cs2_checkout_manifest_unavailable');
            }

            bindRangeToCatalog(memoryInput, findCatalogOption(options, ['memory', 'memory_mb', 'ram', 'ram_mb']), formatGigabytes);
            bindRangeToCatalog(diskInput, findCatalogOption(options, ['disk', 'disk_mb', 'storage', 'storage_mb', 'ssd']), formatGigabytes);
            bindRangeToCatalog(cpuInput, findCatalogOption(options, ['cpu', 'cpu_percent']), function (value) { return value + '%'; });
            bindRangeToCatalog(playersInput, findCatalogOption(options, ['SRCDS_MAXPLAYERS']), function (value) { return String(value); });

            const tickrateOption = findCatalogOption(options, ['TICKRATE']);
            if (!tickrateOption || tickrateOption.upgradable !== true) throw new Error('cs2_config_option_mapping_invalid');
            const tickrateValues = catalogNumericValues(tickrateOption).map(String);
            if (!tickrateValues.length) throw new Error('cs2_config_option_mapping_invalid');
            Array.from(tickrateSelect.options).forEach(function (option) {
                option.disabled = !tickrateValues.includes(option.value);
                option.hidden = option.disabled;
            });
            if (!tickrateValues.includes(tickrateSelect.value)) tickrateSelect.value = tickrateValues[0];

            const availableDurations = new Set(catalog.plans.filter(function (plan) {
                return plan && plan.available === true;
            }).map(function (plan) { return Number(plan.duration_days); }));
            durationInputs.forEach(function (input) {
                input.disabled = !availableDurations.has(Number(input.value));
                const card = input.closest('.server-store-duration-card');
                if (card) card.classList.toggle('is-unavailable', input.disabled);
            });
            if (durationInputs.every(function (input) { return input.disabled || !input.checked; })) {
                const firstAvailable = durationInputs.find(function (input) { return !input.disabled; });
                if (firstAvailable) firstAvailable.checked = true;
            }
            if (!durationInputs.some(function (input) { return input.checked && !input.disabled; })) {
                const firstAvailable = durationInputs.find(function (input) { return !input.disabled; });
                if (firstAvailable) firstAvailable.checked = true;
            }

            catalogLoaded = true;
            catalogFailed = false;
        }

        async function loadPaymenterCatalog() {
            try {
                const url = new URL(apiUrl, window.location.origin);
                url.searchParams.set('action', 'catalog');
                const response = await fetch(url.href, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                const body = await response.json();
                if (!response.ok || body.success !== true || !body.data) {
                    throw new Error(body.error || 'server_catalog_unavailable');
                }
                applyPaymenterCatalog(body.data);
                updateSummary();
            } catch (error) {
                catalogFailed = true;
                setQuoteStatus(humanizeError(error && error.message ? error.message : 'server_catalog_unavailable'), 'error');
                submitButton.disabled = true;
                console.error('[FragCity] Paymenter storefront catalog could not be loaded:', error);
            }
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

        let allMapGroups = mapgroupSelect
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
            const allowedValues = (selectedMode?.dataset.mapGroups || '').split('|').filter(Boolean);
            const availableGroups = allMapGroups.filter(function (group) {
                return group.configs.includes(execSelect.value) && allowedValues.includes(group.value);
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

        function selectedDuration() {
            const selected = durationInputs.find(function (input) { return input.checked; });
            return selected ? Number(selected.value) : 30;
        }

        function selectedPaymentMethod() {
            const selected = paymentInputs.find(function (input) { return input.checked; });
            return selected ? selected.value : 'zarinpal';
        }

        function buildSelectionPayload() {
            return {
                duration_days: selectedDuration(),
                server_name: serverNameInput ? serverNameInput.value.trim() : '',
                resources: {
                    memory_mb: readRangeValue(memoryInput, 1024),
                    disk_mb: readRangeValue(diskInput, 10240),
                    cpu_percent: readRangeValue(cpuInput, 100)
                },
                egg: {
                    SRCDS_MAP: mapInput ? mapInput.value.trim() : '',
                    SRCDS_MAXPLAYERS: String(readRangeValue(playersInput, 10)),
                    TICKRATE: tickrateSelect ? tickrateSelect.value : '64',
                    EXEC: execSelect ? execSelect.value : '',
                    MAPGROUP: mapgroupSelect ? mapgroupSelect.value : '',
                    COOLDOWN: cooldownSelect ? cooldownSelect.value : '0',
                    ENABLE_GUARD: '0',
                    ...(guardMinutesInput && guardMinutesInput.value.trim() !== ''
                        ? { GUARD_BAN_MINUTES: guardMinutesInput.value.trim() }
                        : {})
                }
            };
        }

        function selectionKey(selection) {
            return JSON.stringify(selection);
        }

        function getCheckoutReference(key) {
            if (checkoutSelectionKey === key && checkoutRef) return checkoutRef;
            checkoutSelectionKey = key;
            checkoutRef = '';

            try {
                const saved = JSON.parse(sessionStorage.getItem(SESSION_ORDER_KEY) || 'null');
                if (saved && saved.key === key && /^[a-f0-9]{32}$/.test(saved.ref || '')) {
                    checkoutRef = saved.ref;
                }
            } catch (error) {
                // Private browsing may disable storage; an in-memory id remains safe for this page.
            }

            if (!checkoutRef) {
                const bytes = new Uint8Array(16);
                if (window.crypto && window.crypto.getRandomValues) {
                    window.crypto.getRandomValues(bytes);
                } else {
                    for (let i = 0; i < bytes.length; i += 1) bytes[i] = Math.floor(Math.random() * 256);
                }
                checkoutRef = Array.from(bytes).map(function (byte) {
                    return byte.toString(16).padStart(2, '0');
                }).join('');
                try {
                    sessionStorage.setItem(SESSION_ORDER_KEY, JSON.stringify({ key: key, ref: checkoutRef }));
                } catch (error) {
                    // The request remains idempotent for the lifetime of this page.
                }
            }
            return checkoutRef;
        }

        function clearCheckoutReference() {
            checkoutRef = '';
            checkoutSelectionKey = '';
            try { sessionStorage.removeItem(SESSION_ORDER_KEY); } catch (error) { /* optional */ }
        }

        function setQuoteStatus(message, state) {
            quoteStatus.textContent = message;
            quoteStatus.dataset.state = state || 'info';
        }

        function updateDurationCards(quote) {
            durationPriceOutputs.forEach(function (output) {
                const days = Number(output.dataset.durationPrice);
                if (days === Number(quote.duration_days)) {
                    output.textContent = formatToman(quote.amount_toman);
                    output.dataset.quoteAvailable = '1';
                } else {
                    output.textContent = 'برای مشاهده قیمت انتخاب کنید';
                    delete output.dataset.quoteAvailable;
                }
            });
        }

        function renderQuote(quote) {
            currentQuote = quote;
            setSummary('period-price', formatToman(quote.amount_toman));
            setSummary('monthly-price', Number(quote.duration_days) === 30
                ? formatToman(quote.amount_toman) + ' / دوره ۳۰ روزه'
                : 'فقط قیمت دوره انتخاب‌شده محاسبه شده است');
            setSummary('discount', 'طبق Plan و تخفیف‌های Paymenter');
            setSummary('capacity-base-price', 'در قیمت قطعی لحاظ شده');
            setSummary('extra-slots-price', 'در قیمت قطعی لحاظ شده');
            setSummary('memory-price', 'در قیمت قطعی لحاظ شده');
            updateDurationCards(quote);

            if (quoteLines) {
                quoteLines.replaceChildren();
                const addLine = function (label, value, emphasized) {
                    const row = document.createElement('div');
                    row.className = emphasized ? 'server-store-quote-line is-total' : 'server-store-quote-line';
                    const labelNode = document.createElement('span');
                    labelNode.textContent = label;
                    const valueNode = document.createElement('strong');
                    valueNode.textContent = value;
                    row.append(labelNode, valueNode);
                    quoteLines.appendChild(row);
                };

                (Array.isArray(quote.price_lines) ? quote.price_lines : []).forEach(function (line) {
                    const recurring = Number(line.recurring_toman) || 0;
                    const setup = Number(line.setup_fee_toman) || 0;
                    const parts = [];
                    if (recurring > 0) parts.push('دوره‌ای: ' + formatToman(recurring));
                    if (setup > 0) parts.push('راه‌اندازی: ' + formatToman(setup));
                    addLine(
                        [line.option, line.value].filter(Boolean).join(' · '),
                        parts.length ? parts.join(' + ') : 'بدون هزینه اضافه',
                        false
                    );
                });
                addLine('مالیات', formatToman(quote.tax_toman), false);
                addLine('مبلغ نهایی پرداخت', formatToman(quote.amount_toman), true);
            }

            setQuoteStatus(
                'قیمت قطعی از Paymenter دریافت شد · اعتبار نمایشی کوتاه‌مدت است و هنگام ثبت سفارش دوباره محاسبه می‌شود.',
                'success'
            );
            submitButton.disabled = !authenticated || !walletMethodAllowed() || checkoutCompleted || checkoutLocked;
            submitButton.innerHTML = '<i class="fa-solid fa-lock" aria-hidden="true"></i> ثبت سفارش · ' + formatToman(quote.amount_toman);
        }

        function walletMethodAllowed() {
            return selectedPaymentMethod() !== 'wallet' || walletEnabled;
        }

        function validateLocalFields() {
            if (mapInput) {
                const mapValue = mapInput.value.trim();
                mapInput.setCustomValidity(/^[A-Za-z0-9_-]{1,64}$/.test(mapValue)
                    ? ''
                    : 'نام نقشه فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.');
            }
            if (serverNameInput) {
                const name = serverNameInput.value.trim();
                serverNameInput.setCustomValidity(name.length > 0 && /^[A-Za-z0-9 ]{1,64}$/.test(name)
                    ? ''
                    : 'نام سرور الزامی است و فقط می‌تواند شامل حروف انگلیسی، عدد و فاصله باشد.');
            }
            const players = Number(playersInput.value);
            playersInput.setCustomValidity(Number.isInteger(players) && players >= 5 && players <= 64
                ? ''
                : 'ظرفیت باید یک عدد صحیح بین ۵ تا ۶۴ باشد.');
            if (guardMinutesInput) guardMinutesInput.setCustomValidity('');
        }

        function scheduleQuote() {
            if (quoteTimer) window.clearTimeout(quoteTimer);
            quoteSequence += 1;
            const sequence = quoteSequence;
            currentQuote = null;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fa-solid fa-lock" aria-hidden="true"></i> در انتظار قیمت قطعی';
            setSummary('period-price', 'در حال استعلام از Paymenter…');
            if (quoteLines) quoteLines.replaceChildren();

            if (!catalogLoaded) {
                setQuoteStatus(catalogFailed ? 'کاتالوگ Paymenter در دسترس نیست؛ سفارش غیرفعال است.' : 'در حال دریافت گزینه‌های مجاز از Paymenter…', catalogFailed ? 'error' : 'loading');
                return;
            }
            const selection = buildSelectionPayload();
            const key = selectionKey(selection);
            currentQuoteKey = key;
            getCheckoutReference(key);

            if (!authenticated) {
                setQuoteStatus('برای دریافت قیمت شخصی‌سازی‌شده و ثبت سفارش، ابتدا با Steam وارد FragCity شوید.', 'warning');
                return;
            }
            if (!csrfToken) {
                setQuoteStatus('توکن امنیتی صفحه موجود نیست. صفحه را تازه‌سازی کنید.', 'error');
                return;
            }
            validateLocalFields();
            if (!form.checkValidity()) {
                setQuoteStatus('برای دریافت قیمت، ابتدا فیلدهای نام سرور و نقشه را کامل و معتبر کنید.', 'warning');
                return;
            }

            setQuoteStatus('در حال دریافت قیمت قطعی از Paymenter…', 'loading');
            quoteTimer = window.setTimeout(async function () {
                if (sequence !== quoteSequence) return;
                if (activeQuoteController) activeQuoteController.abort();
                activeQuoteController = new AbortController();
                quoteBusy = true;
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-FragCity-CSRF': csrfToken
                        },
                        body: JSON.stringify({ action: 'quote', ...selection }),
                        signal: activeQuoteController.signal
                    });
                    const body = await response.json();
                    if (sequence !== quoteSequence) return;
                    if (!response.ok || body.success !== true || !body.data) {
                        const error = body.error || 'quote_unavailable';
                        setQuoteStatus(humanizeError(error), 'error');
                        submitButton.disabled = true;
                        return;
                    }
                    if (
                        !Number.isSafeInteger(Number(body.data.amount_rial))
                        || !Number.isSafeInteger(Number(body.data.amount_toman))
                        || Number(body.data.amount_rial) !== Number(body.data.amount_toman) * 10
                        || Number(body.data.duration_days) !== Number(selection.duration_days)
                    ) {
                        throw new Error('paymenter_invalid_response');
                    }
                    renderQuote(body.data);
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                    if (sequence !== quoteSequence) return;
                    setQuoteStatus(humanizeError(error && error.message ? error.message : 'quote_unavailable'), 'error');
                    submitButton.disabled = true;
                } finally {
                    if (sequence === quoteSequence) quoteBusy = false;
                }
            }, 700);
        }

        function humanizeError(error) {
            const messages = {
                authentication_required: 'برای خرید باید وارد حساب FragCity شوید.',
                invalid_session: 'نشست کاربری معتبر نیست؛ دوباره وارد شوید.',
                csrf_failed: 'درخواست امنیتی منقضی شده است. صفحه را تازه‌سازی کنید.',
                authentication_or_csrf_failed: 'نشست یا توکن امنیتی معتبر نیست؛ صفحه را تازه‌سازی کنید.',
                paymenter_account_not_linked: 'حساب FragCity شما هنوز با Paymenter همگام نشده است.',
                cs2_checkout_not_configured: 'اتصال فروشگاه CS2 در Paymenter کامل پیکربندی نشده است.',
                cs2_checkout_manifest_unavailable: 'Manifest تنظیمات CS2 در Paymenter در دسترس نیست.',
                cs2_config_option_mapping_invalid: 'یکی از گزینه‌های RAM، SSD، CPU، ظرفیت یا Tickrate در Paymenter با تنظیم مورد انتظار مطابقت ندارد.',
                cs2_config_option_price_missing: 'برای یکی از مقادیر انتخاب‌شده در Paymenter قیمت معتبر تعریف نشده است.',
                cs2_plan_price_missing: 'قیمت Plan انتخاب‌شده در Paymenter تعریف نشده است.',
                cs2_plan_invalid: 'Plan این مدت در Paymenter فعال یا معتبر نیست.',
                cs2_product_invalid: 'محصول CS2 در Paymenter فعال یا متصل به Extension صحیح نیست.',
                cs2_product_out_of_stock: 'ظرفیت فروش این محصول در حال حاضر تکمیل شده است.',
                cs2_product_user_limit: 'محدودیت تعداد سرویس برای حساب شما تکمیل شده است.',
                egg_combination_invalid: 'ترکیب CFG و Map Group انتخاب‌شده در Manifest سازگار نیست.',
                paid_option_pricing_not_configured: 'Bot Guard تا زمان تعریف تعرفه رسمی در دسترس نیست.',
                quote_changed: 'قیمت در Paymenter تغییر کرده است. مبلغ جدید دریافت شد؛ پیش از پرداخت آن را بررسی کنید.',
                wallet_insufficient_credit: 'موجودی کیف پول Paymenter برای این خرید کافی نیست.',
                wallet_not_enabled: 'کیف پول در Paymenter غیرفعال است.',
                zarinpal_not_configured: 'تنظیمات زرین‌پال در نسخه عملیاتی سایت کامل نیست.',
                zarinpal_request_rejected: 'زرین‌پال درخواست پرداخت را نپذیرفت.',
                zarinpal_request_unknown: 'وضعیت ایجاد درخواست زرین‌پال نامشخص است؛ خرید جدید ایجاد نکنید و با پشتیبانی تماس بگیرید.',
                payment_verified_provisioning_pending: 'پرداخت تأیید شده اما ثبت سفارش در Paymenter هنوز تکمیل نشده است؛ از پرداخت مجدد خودداری کنید.',
                payment_request_status_unknown: 'وضعیت درخواست پرداخت مشخص نیست؛ از تلاش مجدد و پرداخت دوباره خودداری کنید.',
                checkout_reference_conflict: 'شناسه سفارش با انتخاب‌های فعلی تطبیق ندارد. صفحه را تازه‌سازی کنید.',
                paymenter_unreachable: 'ارتباط امن با Paymenter برقرار نشد.',
                paymenter_invalid_response: 'پاسخ Paymenter معتبر نبود.',
                server_catalog_unavailable: 'کاتالوگ فروشگاه در دسترس نیست.'
            };
            return messages[error] || 'خطا در آماده‌سازی سفارش (' + String(error).replace(/[^a-z0-9_-]/gi, '').slice(0, 64) + '). لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.';
        }

        async function loadWalletBalance() {
            if (!authenticated || !csrfToken) return;
            const walletInput = paymentInputs.find(function (input) { return input.value === 'wallet'; });
            if (!walletInput) return;

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-FragCity-CSRF': csrfToken
                    },
                    body: JSON.stringify({ action: 'wallet_balance' })
                });
                const body = await response.json();
                if (!response.ok || body.success !== true || !body.data) {
                    walletInput.disabled = true;
                    walletEnabled = false;
                    if (body.error === 'paymenter_account_not_linked') {
                        if (walletBalanceOutput) walletBalanceOutput.textContent = 'حساب Paymenter همگام نشده است';
                    }
                    if (selectedPaymentMethod() === 'wallet') {
                        const online = paymentInputs.find(function (input) { return input.value === 'zarinpal'; });
                        if (online) online.checked = true;
                    }
                    return;
                }

                walletEnabled = body.data.wallet_enabled === true;
                const balanceRial = Number(body.data.balance);
                walletBalanceToman = Number.isFinite(balanceRial) ? balanceRial / 10 : null;
                walletInput.disabled = !walletEnabled;
                if (walletBalanceOutput) {
                    walletBalanceOutput.textContent = walletEnabled
                        ? 'موجودی: ' + formatToman(walletBalanceToman)
                        : 'کیف پول در Paymenter غیرفعال است';
                }
                if (!walletEnabled && walletInput.checked) {
                    const online = paymentInputs.find(function (input) { return input.value === 'zarinpal'; });
                    if (online) online.checked = true;
                }
                if (currentQuote) submitButton.disabled = !walletMethodAllowed() || checkoutCompleted || checkoutLocked;
            } catch (error) {
                walletInput.disabled = true;
                walletEnabled = false;
                if (walletBalanceOutput) walletBalanceOutput.textContent = 'موجودی کیف پول دریافت نشد';
                if (walletInput.checked) {
                    const online = paymentInputs.find(function (input) { return input.value === 'zarinpal'; });
                    if (online) online.checked = true;
                }
            }
        }

        async function submitCheckout() {
            if (checkoutBusy) return;
            if (!authenticated) {
                setQuoteStatus('برای خرید ابتدا وارد حساب FragCity شوید.', 'warning');
                return;
            }
            if (!form.reportValidity()) return;
            if (!currentQuote || currentQuoteKey !== selectionKey(buildSelectionPayload())) {
                setQuoteStatus('تنظیمات تغییر کرده است؛ ابتدا قیمت جدید را دریافت می‌کنیم.', 'warning');
                scheduleQuote();
                return;
            }
            if (!walletMethodAllowed()) {
                setQuoteStatus('کیف پول در Paymenter فعال نیست؛ روش پرداخت آنلاین را انتخاب کنید.', 'warning');
                return;
            }

            const selection = buildSelectionPayload();
            const key = selectionKey(selection);
            const method = selectedPaymentMethod();
            const reference = getCheckoutReference(key);
            const quote = currentQuote;

            checkoutBusy = true;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> در حال ثبت سفارش…';
            setQuoteStatus('در حال اعتبارسنجی مجدد قیمت و ثبت سفارش در Paymenter…', 'loading');

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-FragCity-CSRF': csrfToken
                    },
                    body: JSON.stringify({
                        action: 'checkout',
                        checkout_ref: reference,
                        payment_method: method,
                        expected_amount_rial: Number(quote.amount_rial),
                        ...selection
                    })
                });
                const body = await response.json();

                if (body.error === 'quote_changed') {
                    setQuoteStatus(humanizeError(body.error), 'warning');
                    scheduleQuote();
                    return;
                }
                if (!response.ok || body.success !== true) {
                    const errorCode = body.error || 'checkout_unavailable';
                    if (errorCode === 'checkout_retry_with_new_reference') {
                        clearCheckoutReference();
                        checkoutLocked = false;
                        setQuoteStatus('پرداخت قبلی توسط زرین‌پال تأیید نشد. شناسه سفارش جدید ساخته می‌شود؛ قیمت دوباره استعلام خواهد شد.', 'warning');
                        scheduleQuote();
                        return;
                    }
                    setQuoteStatus(humanizeError(errorCode), 'error');
                    if (['payment_verified_provisioning_pending', 'payment_request_status_unknown', 'zarinpal_request_unknown', 'checkout_in_progress', 'checkout_not_retryable', 'checkout_cancelled_use_new_reference'].includes(errorCode)) {
                        checkoutLocked = true;
                    }
                    submitButton.disabled = checkoutLocked || !(currentQuote && walletMethodAllowed());
                    return;
                }

                if (body.status === 'awaiting_payment' && typeof body.payment_url === 'string') {
                    const paymentUrl = new URL(body.payment_url, window.location.origin);
                    if (paymentUrl.protocol !== 'https:') throw new Error('invalid_payment_url');
                    setQuoteStatus('در حال انتقال امن به درگاه زرین‌پال…', 'success');
                    window.location.assign(paymentUrl.href);
                    return;
                }

                if (body.status === 'accepted' && body.data) {
                    setQuoteStatus(
                        'سفارش در Paymenter ثبت شد. شناسه سرویس: ' + String(body.data.service_id) +
                        ' · وضعیت ساخت: در صف Provisioning',
                        'success'
                    );
                    checkoutCompleted = true;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> سفارش ثبت شد';
                    clearCheckoutReference();
                    return;
                }

                setQuoteStatus('پاسخ ثبت سفارش نامشخص است؛ از پرداخت مجدد خودداری کنید و وضعیت سفارش را از پشتیبانی پیگیری کنید.', 'warning');
                submitButton.disabled = true;
            } catch (error) {
                setQuoteStatus(humanizeError(error && error.message ? error.message : 'checkout_unavailable'), 'error');
                // Keep the same checkout reference. Paymenter and the website
                // ledger both make an exact retry safe after a lost response.
                submitButton.disabled = checkoutLocked || !(currentQuote && walletMethodAllowed());
            } finally {
                checkoutBusy = false;
                if (checkoutCompleted) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> سفارش ثبت شد';
                } else if (currentQuote && !checkoutLocked) {
                    submitButton.disabled = !walletMethodAllowed();
                    submitButton.innerHTML = '<i class="fa-solid fa-lock" aria-hidden="true"></i> ثبت سفارش · ' + formatToman(currentQuote.amount_toman);
                } else if (checkoutLocked) {
                    submitButton.disabled = true;
                }
            }
        }

        function updateSummary() {
            validateLocalFields();
            const players = readRangeValue(playersInput, 10);
            const memoryMb = readRangeValue(memoryInput, 1024);
            const diskMb = readRangeValue(diskInput, 10240);
            const cpuPercent = readRangeValue(cpuInput, 100);
            const cpuVcores = cpuPercent / 100;
            const cpuUnit = cpuVcores === 1 ? 'vCore' : 'vCores';

            if (playersOutput) playersOutput.textContent = String(players);
            if (memoryOutput) memoryOutput.textContent = formatGigabytes(memoryMb);
            if (diskOutput) diskOutput.textContent = formatGigabytes(diskMb);
            if (cpuOutput) cpuOutput.textContent = cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit;
            [memoryInput, diskInput, cpuInput, playersInput].forEach(syncRangeProgress);

            playerPresetButtons.forEach(function (button) {
                button.setAttribute('aria-pressed', Number(button.dataset.playerPreset) === players ? 'true' : 'false');
                button.disabled = playersInput.dataset.catalogValues
                    ? !JSON.parse(playersInput.dataset.catalogValues).map(Number).includes(Number(button.dataset.playerPreset))
                    : false;
            });

            setSummary('memory', formatGigabytes(memoryMb) + ' · ' + memoryMb + ' MB');
            setSummary('disk', formatGigabytes(diskMb) + ' · ' + diskMb + ' MB');
            setSummary('cpu', cpuPercent + '% · ' + cpuVcores + ' ' + cpuUnit);
            setSummary('players', players + ' اسلات');
            setSummary('map', mapInput ? mapInput.value.trim() || '—' : '—');
            setSummary('server-name', serverNameInput ? serverNameInput.value.trim() || 'وارد نشده' : 'وارد نشده');
            setSummary('duration', selectedDuration() + ' روز');
            setSummary('tickrate', tickrateSelect && tickrateSelect.value === '128' ? '128 Tick' : '64 Tick · Subtick');
            setSummary('exec', execSelect?.selectedOptions[0]?.textContent.trim() || '—');
            setSummary('mapgroup', mapgroupSelect?.selectedOptions[0]?.textContent.trim() || '—');
            setSummary('cooldown', cooldownSelect?.selectedOptions[0]?.textContent.trim() || '—');
            setSummary('guard', 'غیرفعال');
            setSummary('guard-minutes', 'در این سفارش غیرفعال');
            setSummary('discount', 'طبق قیمت‌گذاری Paymenter');

            if (guardSelect) {
                guardSelect.value = '0';
                guardSelect.disabled = true;
            }
            if (guardMinutesField) guardMinutesField.hidden = true;
            if (guardMinutesInput) guardMinutesInput.disabled = true;

            scheduleQuote();
        }

        const liveControls = [
            memoryInput, diskInput, cpuInput, playersInput, mapInput, serverNameInput,
            tickrateSelect, execSelect, mapgroupSelect, cooldownSelect, ...durationInputs
        ].filter(Boolean);

        liveControls.forEach(function (control) {
            ['input', 'change'].forEach(function (eventName) {
                control.addEventListener(eventName, function () {
                    if (control === execSelect && mapgroupSelect) syncMapGroups(mapgroupSelect.value);
                    updateSummary();
                });
            });
        });

        playerPresetButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const value = Number(button.dataset.playerPreset);
                if (!Number.isInteger(value) || value < 5 || value > 64) return;
                playersInput.value = String(value);
                updateSummary();
            });
        });

        paymentInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                if (!currentQuote) return;
                submitButton.disabled = !walletMethodAllowed();
                if (input.value === 'wallet') loadWalletBalance();
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

        if (form.dataset.serverStoreSubmitBound !== '1') {
            form.dataset.serverStoreSubmitBound = '1';
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCheckout();
            });
        }

        if (execSelect && mapgroupSelect) syncMapGroups(mapgroupSelect.value || 'mg_comp');

        root.dataset.serverStoreInitialized = '1';
        setSummary('period-price', 'در انتظار استعلام قیمت');
        setSummary('monthly-price', 'طبق تعرفه Paymenter');
        setSummary('capacity-base-price', 'طبق Config Optionهای Paymenter');
        setSummary('extra-slots-price', 'در Quote رسمی مشخص می‌شود');
        setSummary('memory-price', 'در Quote رسمی مشخص می‌شود');
        if (!authenticated) {
            setQuoteStatus('برای دریافت قیمت قطعی و خرید، ابتدا با حساب Steam وارد شوید.', 'warning');
        }
        setQuoteStatus('در حال دریافت گزینه‌های مجاز از Paymenter…', 'loading');
        loadPaymenterCatalog();
        if (walletBalanceOutput) walletBalanceOutput.textContent = 'برای مشاهده موجودی، روش کیف پول را انتخاب کنید.';
        if (authenticated) loadWalletBalance();
        root.dataset.serverStoreStatus = 'ready';
        delete root.dataset.serverStoreError;
        return true;
    }

    window.initBuyServerPage = initServerStorePage;

    function ensureInsertionObserver() {
        if (!document.body || !('MutationObserver' in window) || window.__fragCityBuyStoreObserver) return;
        const observer = new MutationObserver(function () {
            const root = document.querySelector(ROOT_SELECTOR);
            if (!root || root.dataset.serverStoreInitialized === '1') return;
            initServerStorePage();
        });
        observer.observe(document.body, { childList: true, subtree: true });
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

    window.addEventListener('content-loaded', tryInitializeWhenReady);
})();
