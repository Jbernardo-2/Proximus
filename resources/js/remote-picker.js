const debounce = (callback, delay = 250) => {
    let timeout;

    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => callback(...args), delay);
    };
};

document.querySelectorAll('[data-remote-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-remote-picker-search]');
    const value = picker.querySelector('[data-remote-picker-value]');
    const results = picker.querySelector('[data-remote-picker-results]');
    const selected = picker.querySelector('[data-remote-picker-selected]');
    const selectedName = picker.querySelector('[data-picker-selected-name]');
    const selectedMeta = picker.querySelector('[data-picker-selected-meta]');
    const selectedImage = picker.querySelector('[data-picker-selected-image]');
    const selectedImageWrap = picker.querySelector('[data-picker-selected-image-wrap]');
    const loading = picker.querySelector('[data-remote-picker-loading]');
    const error = picker.querySelector('[data-remote-picker-error]');
    const type = picker.dataset.pickerType;
    let controller;
    let page = 1;
    let latestResults = [];

    const runSearch = debounce(() => fetchResults(1));

    search.addEventListener('focus', () => fetchResults(1));
    search.addEventListener('input', runSearch);
    search.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        await fetchResults(1, true);
    });

    picker.querySelector('[data-remote-picker-clear]')?.addEventListener('click', () => {
        value.value = '';
        selected.classList.add('hidden');
        search.value = '';
        search.focus();
        picker.dispatchEvent(new CustomEvent('remote-picker:cleared', { bubbles: true }));
        fetchResults(1);
    });

    picker.querySelector('[data-remote-picker-scan]')?.addEventListener('click', () => startBarcodeScanner(search, async (code) => {
        search.value = code;
        await fetchResults(1, true);
    }, showError));

    document.addEventListener('click', (event) => {
        if (! picker.contains(event.target)) {
            hideResults();
        }
    });

    async function fetchResults(nextPage = 1, chooseExact = false) {
        controller?.abort();
        controller = new AbortController();
        page = nextPage;
        setLoading(true);
        showError('');

        try {
            const endpoint = new URL(picker.dataset.pickerEndpoint, window.location.origin);
            endpoint.searchParams.set('q', search.value.trim());
            endpoint.searchParams.set('page', String(page));

            if (picker.dataset.pickerMode) {
                endpoint.searchParams.set('mode', picker.dataset.pickerMode);
            }

            const warehouseId = picker.dataset.pickerWarehouseSelector
                ? document.querySelector(picker.dataset.pickerWarehouseSelector)?.value
                : picker.dataset.pickerWarehouseId;

            if (warehouseId) {
                endpoint.searchParams.set('warehouse_id', warehouseId);
            }

            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const payload = await response.json();

            if (! response.ok) {
                throw new Error(payload.message ?? 'No fue posible buscar.');
            }

            latestResults = nextPage === 1 ? payload.data : [...latestResults, ...payload.data];
            renderResults(latestResults, payload.meta);

            if (chooseExact && payload.data.length) {
                const query = search.value.trim().toLocaleLowerCase();
                const exact = payload.data.find((item) => [item.barcode, item.sku, item.code]
                    .filter(Boolean)
                    .some((candidate) => String(candidate).toLocaleLowerCase() === query));

                if (exact || payload.data.length === 1) {
                    choose(exact ?? payload.data[0]);
                }
            }
        } catch (exception) {
            if (exception.name !== 'AbortError') {
                showError(exception.message);
            }
        } finally {
            setLoading(false);
        }
    }

    function renderResults(items, meta) {
        results.replaceChildren();

        if (! items.length) {
            const empty = document.createElement('p');
            empty.className = 'p-4 text-center text-sm text-ink-600';
            empty.textContent = search.value.trim()
                ? 'No encontramos coincidencias. Revisa el nombre o código.'
                : 'No hay registros disponibles.';
            results.append(empty);
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mb-1 flex w-full items-start gap-3 rounded-xl p-3 text-left transition last:mb-0 hover:bg-mint-50 focus:bg-mint-50';
            button.setAttribute('role', 'option');

            if (type === 'product') {
                button.append(productResult(item));
            } else {
                button.append(customerResult(item));
            }

            button.addEventListener('click', () => choose(item));
            results.append(button);
        });

        if (meta?.has_more) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'btn-secondary mt-2 w-full';
            more.textContent = 'Mostrar más resultados';
            more.addEventListener('click', () => fetchResults(page + 1));
            results.append(more);
        }

        results.classList.remove('hidden');
        search.setAttribute('aria-expanded', 'true');
    }

    function choose(item) {
        value.value = item.id;
        selectedName.textContent = type === 'product' ? `${item.name} · ${item.presentation}` : item.name;
        selectedMeta.textContent = type === 'product'
            ? [item.sku, item.barcode, formatMoney(item.sale_price), item.stock ? `Disponible: ${formatQuantity(item.stock.available)} ${item.base_unit}` : null].filter(Boolean).join(' · ')
            : [item.code, item.business_type, item.phone, item.address].filter(Boolean).join(' · ');

        if (selectedImage && selectedImageWrap) {
            selectedImage.src = item.image_url ?? '';
            selectedImageWrap.classList.toggle('hidden', ! item.image_url);
        }

        selected.classList.remove('hidden');
        search.value = '';
        hideResults();
        value.dispatchEvent(new Event('change', { bubbles: true }));
        picker.dispatchEvent(new CustomEvent('remote-picker:selected', { detail: item, bubbles: true }));
    }

    function hideResults() {
        results.classList.add('hidden');
        search.setAttribute('aria-expanded', 'false');
    }

    function setLoading(active) {
        loading.classList.toggle('hidden', ! active);
    }

    function showError(message) {
        error.textContent = message;
        error.classList.toggle('hidden', ! message);
    }
});

function productResult(item) {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex min-w-0 flex-1 gap-3';
    const image = document.createElement(item.image_url ? 'img' : 'div');
    image.className = 'size-16 shrink-0 rounded-xl border border-stone-200 bg-white object-contain';

    if (item.image_url) {
        image.src = item.image_url;
        image.alt = '';
        image.loading = 'lazy';
    } else {
        image.classList.add('grid', 'place-items-center', 'text-xl', 'font-black', 'text-stone-400');
        image.textContent = item.name.slice(0, 1).toLocaleUpperCase();
    }

    const content = document.createElement('div');
    content.className = 'min-w-0 flex-1';
    const title = document.createElement('p');
    title.className = 'font-bold text-ink-950';
    title.textContent = item.name;
    const presentation = document.createElement('p');
    presentation.className = 'text-sm font-semibold text-leaf-700';
    presentation.textContent = `${item.presentation} · ${formatMoney(item.sale_price)}`;
    const meta = document.createElement('p');
    meta.className = 'mt-1 text-xs text-ink-600';
    meta.textContent = [item.sku, item.barcode ? `CB: ${item.barcode}` : null, item.brand, item.category].filter(Boolean).join(' · ');
    const detail = document.createElement('p');
    detail.className = 'mt-1 text-xs text-ink-600';
    detail.textContent = `1 ${item.presentation} = ${formatQuantity(item.conversion_factor)} ${item.base_unit}`
        + (item.stock ? ` · Disponible: ${formatQuantity(item.stock.available)} ${item.base_unit}` : '');
    content.append(title, presentation, meta, detail);

    if (item.description) {
        const description = document.createElement('p');
        description.className = 'mt-1 line-clamp-2 text-xs text-ink-600';
        description.textContent = item.description;
        content.append(description);
    }

    const controls = [
        item.tracks_lots ? 'Control por lote' : null,
        item.tracks_expiration ? 'Control de vencimiento' : null,
        item.allows_decimal ? 'Acepta decimales' : null,
    ].filter(Boolean);

    if (controls.length) {
        const flags = document.createElement('p');
        flags.className = 'mt-1 text-xs font-semibold text-indigo-700';
        flags.textContent = controls.join(' · ');
        content.append(flags);
    }

    if (item.price_tiers?.length) {
        const tiers = document.createElement('p');
        tiers.className = 'mt-1 text-xs font-semibold text-amber-700';
        tiers.textContent = item.price_tiers.slice(0, 2).map((tier) => {
            const range = tier.max_quantity
                ? `${formatQuantity(tier.min_quantity)}–${formatQuantity(tier.max_quantity)}`
                : `desde ${formatQuantity(tier.min_quantity)}`;

            return `${range}: ${formatMoney(tier.unit_price)}`;
        }).join(' · ');
        content.append(tiers);
    }

    wrapper.append(image, content);

    return wrapper;
}

function customerResult(item) {
    const content = document.createElement('div');
    content.className = 'min-w-0 flex-1';
    const title = document.createElement('p');
    title.className = 'font-bold text-ink-950';
    title.textContent = item.name;
    const meta = document.createElement('p');
    meta.className = 'text-xs font-semibold text-leaf-700';
    meta.textContent = [item.code, item.business_type, item.phone].filter(Boolean).join(' · ');
    const address = document.createElement('p');
    address.className = 'mt-1 line-clamp-2 text-xs text-ink-600';
    address.textContent = item.address;
    content.append(title, meta, address);

    if (item.route_stops?.length) {
        const routes = document.createElement('p');
        routes.className = 'mt-1 text-xs text-ink-600';
        routes.textContent = item.route_stops.slice(0, 2)
            .map((stop) => `${stop.route_name} · ${stop.visit_day_label} #${stop.visit_order}`)
            .join(' | ');
        content.append(routes);
    }

    return content;
}

async function startBarcodeScanner(search, onDetected, showError) {
    search.focus();

    if (! ('BarcodeDetector' in window) || ! navigator.mediaDevices?.getUserMedia) {
        showError('La cámara de este navegador no puede leer códigos. Usa un lector USB/Bluetooth o escribe el código y presiona Enter.');

        return;
    }

    let stream;
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[100] grid place-items-center bg-ink-950/80 p-4';
    overlay.innerHTML = '<div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-2xl"><div class="mb-3 flex items-center justify-between gap-3"><div><p class="font-black text-ink-950">Escanear código</p><p class="text-sm text-ink-600">Centra el código de barras dentro de la cámara.</p></div><button class="btn-secondary" type="button" data-scanner-close>Cerrar</button></div><video class="aspect-video w-full rounded-xl bg-black object-cover" autoplay playsinline muted></video><p class="mt-3 text-center text-sm text-ink-600" data-scanner-status>Iniciando cámara…</p></div>';
    document.body.append(overlay);
    const video = overlay.querySelector('video');
    const scannerStatus = overlay.querySelector('[data-scanner-status]');
    let active = true;

    const close = () => {
        active = false;
        stream?.getTracks().forEach((track) => track.stop());
        overlay.remove();
        search.focus();
    };

    overlay.querySelector('[data-scanner-close]').addEventListener('click', close);

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        video.srcObject = stream;
        await video.play();
        const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'itf'] });
        scannerStatus.textContent = 'Buscando código…';

        const detect = async () => {
            if (! active) {
                return;
            }

            try {
                const codes = await detector.detect(video);

                if (codes[0]?.rawValue) {
                    const code = codes[0].rawValue;
                    close();
                    await onDetected(code);

                    return;
                }
            } catch {
                scannerStatus.textContent = 'No se pudo leer este cuadro. Mantén el código estable.';
            }

            window.setTimeout(detect, 180);
        };

        detect();
    } catch {
        close();
        showError('No fue posible abrir la cámara. Revisa el permiso del navegador o usa un lector externo.');
    }
}

function formatMoney(value) {
    return new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value ?? 0));
}

function formatQuantity(value) {
    return new Intl.NumberFormat('es-HN', { maximumFractionDigits: 6 }).format(Number(value ?? 0));
}
