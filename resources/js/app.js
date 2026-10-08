import './customer-location.js';
import './remote-picker.js';

const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    mobileMenu?.classList.toggle('hidden');
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const passwordInput = document.getElementById(button.dataset.passwordToggle);
    const visibleIcon = button.querySelector('[data-password-visible-icon]');
    const hiddenIcon = button.querySelector('[data-password-hidden-icon]');

    button.addEventListener('click', () => {
        const passwordIsVisible = passwordInput.type === 'text';

        passwordInput.type = passwordIsVisible ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(! passwordIsVisible));
        button.setAttribute('aria-label', passwordIsVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        visibleIcon?.classList.toggle('hidden', ! passwordIsVisible);
        hiddenIcon?.classList.toggle('hidden', passwordIsVisible);
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (! window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const productImagePicker = document.querySelector('[data-product-image-picker]');

if (productImagePicker) {
    const input = productImagePicker.querySelector('[data-product-image-input]');
    const preview = productImagePicker.querySelector('[data-product-image-preview]');
    const previewImage = productImagePicker.querySelector('[data-product-image-preview-image]');
    const previewName = productImagePicker.querySelector('[data-product-image-preview-name]');
    const previewDescription = productImagePicker.querySelector('[data-product-image-preview-description]');
    const processingStatus = productImagePicker.querySelector('[data-product-image-processing-status]');
    const toggleVersionButton = productImagePicker.querySelector('[data-product-image-toggle-version]');
    const removeImage = productImagePicker.querySelector('[data-remove-product-image]');
    const productForm = productImagePicker.closest('[data-product-form]');
    const submitButton = productForm?.querySelector('[data-product-submit]');
    const initialPreviewSource = previewImage.getAttribute('src');
    const isCreate = productForm?.dataset.productCreate === 'true';
    let originalFile;
    let processedFile;
    let selectedVersion = 'original';
    let processingPromise;
    let selectionId = 0;
    let waitingToSubmit = false;
    let previewUrl;

    productImagePicker.querySelector('[data-image-source="file"]')?.addEventListener('click', () => {
        input.removeAttribute('capture');
        input.click();
    });

    productImagePicker.querySelector('[data-image-source="camera"]')?.addEventListener('click', () => {
        input.setAttribute('capture', 'environment');
        input.click();
    });

    input.addEventListener('change', () => {
        input.removeAttribute('capture');

        const [file] = input.files;

        if (! file) {
            return;
        }

        originalFile = file;
        processedFile = undefined;
        selectedVersion = 'original';
        selectionId += 1;
        showPreview(
            file,
            file.name,
            isCreate
                ? 'Preparando una versión uniforme con fondo blanco…'
                : 'Revisa la fotografía antes de guardar los cambios.',
        );
        toggleVersionButton?.classList.add('hidden');

        if (removeImage) {
            removeImage.checked = false;
        }

        if (isCreate) {
            startBackgroundRemoval(file, selectionId);
        }
    });

    removeImage?.addEventListener('change', () => {
        if (! removeImage.checked && initialPreviewSource) {
            previewImage.src = initialPreviewSource;
            previewName.textContent = 'Imagen actual';
            previewDescription.textContent = 'Revisa la fotografía antes de guardar los cambios.';
            preview.classList.remove('hidden');
            preview.classList.add('flex');

            return;
        }

        input.value = '';
        originalFile = undefined;
        processedFile = undefined;
        selectionId += 1;
        preview.classList.remove('flex');
        preview.classList.add('hidden');
    });

    toggleVersionButton?.addEventListener('click', () => {
        if (! originalFile || ! processedFile) {
            return;
        }

        if (selectedVersion === 'processed') {
            if (! replaceSelectedFile(originalFile)) {
                showProcessingStatus('El navegador no permitió cambiar a la fotografía original. Se conservará la versión con fondo blanco.');

                return;
            }

            selectedVersion = 'original';
            showPreview(originalFile, originalFile.name, 'Se guardará la fotografía original.');
            toggleVersionButton.textContent = 'Usar versión con fondo blanco';

            return;
        }

        if (! replaceSelectedFile(processedFile)) {
            showProcessingStatus('El navegador no permitió cambiar a la versión con fondo blanco. Se conservará la fotografía original.');

            return;
        }

        selectedVersion = 'processed';
        showPreview(
            processedFile,
            processedFile.name,
            'Fondo eliminado en este dispositivo. Revisa el resultado antes de crear el producto.',
        );
        toggleVersionButton.textContent = 'Conservar fotografía original';
    });

    productForm?.addEventListener('submit', (event) => {
        if (processingPromise && ! waitingToSubmit) {
            event.preventDefault();
            waitingToSubmit = true;

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Terminando fotografía…';
            }

            processingPromise.finally(() => {
                waitingToSubmit = false;
                submitButton?.removeAttribute('disabled');
                productForm.requestSubmit(submitButton);
            });

            return;
        }

        if (waitingToSubmit) {
            event.preventDefault();

            return;
        }

        if (! submitButton) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = isCreate ? 'Creando producto…' : 'Guardando…';
    });

    function startBackgroundRemoval(file, currentSelectionId) {
        showProcessingStatus('Quitando el fondo en este dispositivo. La primera vez puede tardar un poco.');

        const currentPromise = import('./product-image-background.js')
            .then(({ removeProductImageBackground }) => removeProductImageBackground(file))
            .then((result) => {
                if (currentSelectionId !== selectionId) {
                    return;
                }

                processedFile = result;

                if (! replaceSelectedFile(result)) {
                    throw new Error('El navegador no permitió preparar el archivo procesado.');
                }

                selectedVersion = 'processed';
                showPreview(
                    result,
                    result.name,
                    'Fondo eliminado en este dispositivo. Revisa el resultado antes de crear el producto.',
                );
                showProcessingStatus('Fotografía lista. Puedes conservar el resultado o usar la original.');
                toggleVersionButton?.classList.remove('hidden');

                if (toggleVersionButton) {
                    toggleVersionButton.textContent = 'Conservar fotografía original';
                }
            })
            .catch((error) => {
                if (currentSelectionId !== selectionId) {
                    return;
                }

                processedFile = undefined;
                selectedVersion = 'original';
                replaceSelectedFile(originalFile);
                showPreview(
                    originalFile,
                    originalFile.name,
                    'No se pudo quitar el fondo; se conservará la fotografía original.',
                );
                showProcessingStatus(
                    error.message || 'No fue posible quitar el fondo. Se conservará la fotografía original.',
                );
                toggleVersionButton?.classList.add('hidden');
            });

        processingPromise = currentPromise;
        currentPromise.finally(() => {
            if (processingPromise === currentPromise) {
                processingPromise = undefined;
            }
        });
    }

    function replaceSelectedFile(file) {
        if (! file || typeof DataTransfer === 'undefined') {
            return false;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;

            return input.files.length === 1;
        } catch {
            return false;
        }
    }

    function showPreview(file, name, description) {
        if (! file) {
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        previewUrl = URL.createObjectURL(file);
        previewImage.src = previewUrl;
        previewName.textContent = name;

        if (previewDescription) {
            previewDescription.textContent = description;
        }

        preview.classList.remove('hidden');
        preview.classList.add('flex');
    }

    function showProcessingStatus(message) {
        if (! processingStatus) {
            return;
        }

        processingStatus.textContent = message;
        processingStatus.classList.remove('hidden');
    }
}

const conversionForm = document.querySelector('[data-conversion-form]');

conversionForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const output = document.querySelector('[data-conversion-output]');
    const button = conversionForm.querySelector('button[type="submit"]');
    const quantity = new FormData(conversionForm).get('quantity');
    const endpoint = new URL(conversionForm.action);
    endpoint.searchParams.set('quantity', quantity);

    button.disabled = true;
    output.innerHTML = '<p class="text-sm text-ink-600">Calculando sugerencia…</p>';

    try {
        const response = await fetch(endpoint, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        if (! response.ok) {
            const message = payload.errors?.quantity?.[0] ?? payload.message ?? 'No fue posible calcular la sugerencia.';
            throw new Error(message);
        }

        const suggestion = payload.data;
        const rows = suggestion.components.map((component) => `
            <li class="flex items-center justify-between gap-4 border-b border-stone-100 py-2 last:border-0">
                <span><strong>${escapeHtml(component.count)}</strong> × ${escapeHtml(component.name)}</span>
                <span class="text-right text-sm text-ink-600">${escapeHtml(component.base_quantity)} ${escapeHtml(suggestion.base_unit.symbol)} · ${escapeHtml(component.subtotal)}</span>
            </li>
        `).join('');

        output.innerHTML = `
            <div class="rounded-xl bg-mint-50 p-4">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <strong class="text-leaf-700">Desglose sugerido</strong>
                    <span class="text-sm font-semibold">Total estimado: ${escapeHtml(suggestion.estimated_total)}</span>
                </div>
                <ul>${rows || '<li class="py-2 text-sm">No hay presentaciones vendibles para cubrir la cantidad.</li>'}</ul>
                ${suggestion.is_exact ? '' : `<p class="mt-2 text-sm text-amber-700">Quedan ${escapeHtml(suggestion.remaining_base_quantity)} ${escapeHtml(suggestion.base_unit.symbol)} sin cubrir.</p>`}
                <p class="mt-3 text-xs text-ink-600">${escapeHtml(suggestion.notice)}</p>
            </div>
        `;
    } catch (error) {
        output.innerHTML = `<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700">${escapeHtml(error.message)}</p>`;
    } finally {
        button.disabled = false;
    }
});

const orderCreateForm = document.querySelector('[data-order-create-form]');

if (orderCreateForm) {
    const routeStopSelect = orderCreateForm.querySelector('[data-route-stop-select]');
    const salespersonSelect = orderCreateForm.querySelector('[data-order-salesperson-select]');

    orderCreateForm.addEventListener('remote-picker:selected', (event) => {
        if (event.target.dataset.pickerType !== 'customer') {
            return;
        }

        const emptyLabel = routeStopSelect.dataset.routeRequired === 'true'
            ? 'Selecciona una visita…'
            : 'Pedido fuera de ruta';
        routeStopSelect.replaceChildren(new Option(emptyLabel, ''));

        event.detail.route_stops.forEach((stop) => {
            const option = new Option(`${stop.visit_day_label} #${stop.visit_order} · ${stop.route_name}`, stop.id);
            option.dataset.salespersonId = stop.salesperson_id ?? '';
            routeStopSelect.add(option);
        });

        if (event.detail.route_stops.length === 1) {
            routeStopSelect.value = event.detail.route_stops[0].id;
            routeStopSelect.dispatchEvent(new Event('change'));
        }
    });

    orderCreateForm.addEventListener('remote-picker:cleared', (event) => {
        if (event.target.dataset.pickerType === 'customer') {
            routeStopSelect.replaceChildren(new Option(
                routeStopSelect.dataset.routeRequired === 'true' ? 'Primero busca un cliente…' : 'Pedido fuera de ruta',
                '',
            ));
        }
    });

    routeStopSelect?.addEventListener('change', () => {
        const salespersonId = routeStopSelect.selectedOptions[0]?.dataset.salespersonId;

        if (salespersonId && salespersonSelect) {
            salespersonSelect.value = salespersonId;
        }
    });
}

document.querySelectorAll('[data-order-item-form]').forEach((form) => {
    const button = form.querySelector('[data-order-quote-button]');
    const presentation = form.querySelector('[data-product-presentation-value]');
    const quantity = form.querySelector('[data-order-quantity-input]');
    const output = form.querySelector('[data-order-quote-output]');

    button?.addEventListener('click', async () => {
        if (! presentation.value || ! quantity.value) {
            showOrderQuoteError(output, 'Selecciona una presentación e indica la cantidad.');

            return;
        }

        const endpoint = new URL(form.dataset.quoteUrl, window.location.origin);
        endpoint.searchParams.set('product_presentation_id', presentation.value);
        endpoint.searchParams.set('quantity', quantity.value);
        button.disabled = true;
        output.classList.remove('hidden');
        output.innerHTML = '<p class="rounded-xl bg-stone-50 p-4 text-sm text-ink-600">Calculando precio y conversión…</p>';

        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            const payload = await response.json();

            if (! response.ok) {
                const message = Object.values(payload.errors ?? {}).flat()[0]
                    ?? payload.message
                    ?? 'No fue posible calcular el precio.';
                throw new Error(message);
            }

            const quote = payload.data;
            const selected = quote.selected;
            const suggestion = quote.conversion_suggestion;
            const components = suggestion.components.map((component) => `
                <li class="flex items-center justify-between gap-3 border-t border-emerald-100 py-2 first:border-0">
                    <span><strong>${escapeHtml(component.count)}</strong> × ${escapeHtml(component.name)}</span>
                    <span class="text-xs text-ink-600">${escapeHtml(component.base_quantity)} ${escapeHtml(suggestion.base_unit.symbol)}</span>
                </li>
            `).join('');

            output.innerHTML = `
                <div class="grid gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-emerald-800 uppercase">Precio calculado</p>
                        <p class="mt-1 text-xl font-black text-emerald-950">${escapeHtml(selected.line_total)}</p>
                        <p class="text-sm text-emerald-900">${escapeHtml(selected.quantity)} × ${escapeHtml(selected.standard_unit_price)} · ${selected.price_source === 'price_tier' ? 'precio por cantidad' : 'precio normal'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-emerald-800 uppercase">Presentaciones sugeridas</p>
                        <ul class="mt-1 text-sm text-emerald-950">${components || '<li class="py-2">Sin combinación disponible.</li>'}</ul>
                        ${suggestion.is_exact ? '' : `<p class="mt-1 text-xs text-amber-800">Quedan ${escapeHtml(suggestion.remaining_base_quantity)} ${escapeHtml(suggestion.base_unit.symbol)} sin cubrir.</p>`}
                    </div>
                    <p class="md:col-span-2 text-xs text-emerald-900">${escapeHtml(suggestion.notice)}</p>
                </div>
            `;
        } catch (error) {
            showOrderQuoteError(output, error.message);
        } finally {
            button.disabled = false;
        }
    });
});

document.querySelectorAll('[data-preparation-form]').forEach((form) => {
    const quantityInputs = form.querySelectorAll('[data-zero-safe-quantity]');

    quantityInputs.forEach((input) => {
        input.addEventListener('focus', () => input.select());
        input.addEventListener('mouseup', (event) => event.preventDefault());
    });

    form.querySelectorAll('[data-fill-requested]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('div')?.parentElement?.querySelector('[data-zero-safe-quantity]');

            if (input) {
                input.value = button.dataset.fillRequested;
                input.focus();
            }
        });
    });

    form.querySelector('[data-fill-all-requested]')?.addEventListener('click', () => {
        quantityInputs.forEach((input) => {
            input.value = input.dataset.requestedQuantity;
        });
        quantityInputs[0]?.focus();
    });
});

document.querySelectorAll('[data-payment-form]').forEach((form) => {
    const method = form.querySelector('[data-payment-method]');
    const reference = form.querySelector('[data-payment-reference]');
    const requiredLabel = form.querySelector('[data-reference-required-label]');

    const syncReference = () => {
        const isRequired = method.selectedOptions[0]?.dataset.requiresReference === 'true';
        reference.required = isRequired;
        requiredLabel.textContent = isRequired ? '*' : '(opcional)';
    };

    method.addEventListener('change', syncReference);
    syncReference();
});

function showOrderQuoteError(output, message) {
    output.classList.remove('hidden');
    output.innerHTML = `<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700">${escapeHtml(message)}</p>`;
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');

    return element.innerHTML;
}
