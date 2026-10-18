import './customer-location.js';
import './dashboard.js';
import './location-capture.js';
import './notifications.js';
import './remote-picker.js';

const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    mobileMenu?.classList.toggle('hidden');
});

const appDialog = document.querySelector('[data-app-dialog]');
const appDialogTitle = appDialog?.querySelector('[data-app-dialog-title]');
const appDialogMessage = appDialog?.querySelector('[data-app-dialog-message]');
const appDialogIcon = appDialog?.querySelector('[data-app-dialog-icon]');
const appDialogConfirm = appDialog?.querySelector('[data-app-dialog-confirm]');
const appDialogCancel = appDialog?.querySelector('[data-app-dialog-cancel]');

appDialog?.querySelector('[data-app-dialog-close]')?.addEventListener('click', () => {
    appDialog.close('cancel');
});
appDialogConfirm?.addEventListener('click', () => {
    appDialog.close(appDialog.dataset.dialogMode === 'confirm' ? 'confirm' : 'accept');
});
appDialogCancel?.addEventListener('click', () => {
    appDialog.close('cancel');
});

const showAppDialog = ({ title, messages, type = 'info', confirmText = 'Entendido', cancelText = null }) => {
    if (! appDialog) {
        return Promise.resolve(false);
    }

    const isConfirmation = cancelText !== null;
    const iconStyles = {
        success: ['bg-mint-100', 'text-leaf-700', '✓'],
        error: ['bg-red-100', 'text-red-700', '!'],
        warning: ['bg-amber-100', 'text-amber-800', '!'],
        info: ['bg-mint-100', 'text-leaf-700', 'i'],
    };
    const [backgroundClass, colorClass, icon] = iconStyles[type] ?? iconStyles.info;

    appDialog.dataset.dialogMode = isConfirmation ? 'confirm' : 'alert';
    appDialogTitle.textContent = title;
    appDialogMessage.replaceChildren();
    messages.forEach((message) => {
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        appDialogMessage.append(paragraph);
    });
    appDialogIcon.classList.remove('bg-mint-100', 'text-leaf-700', 'bg-red-100', 'text-red-700', 'bg-amber-100', 'text-amber-800');
    appDialogIcon.classList.add(backgroundClass, colorClass);
    appDialogIcon.textContent = icon;
    appDialogConfirm.textContent = confirmText;
    appDialogCancel.textContent = cancelText ?? 'Cancelar';
    appDialogCancel.classList.toggle('hidden', ! isConfirmation);
    appDialog.returnValue = '';

    return new Promise((resolve) => {
        appDialog.addEventListener('close', () => {
            resolve(appDialog.returnValue === 'confirm');
        }, { once: true });
        appDialog.showModal();
    });
};

document.querySelectorAll('[data-toast]').forEach((toast) => {
    let removalTimer;
    const duration = Number.parseInt(toast.dataset.toastDuration ?? '2800', 10);

    const dismissToast = () => {
        if (toast.dataset.toastDismissing === 'true') {
            return;
        }

        toast.dataset.toastDismissing = 'true';
        toast.classList.add('-translate-y-2', 'pointer-events-none', 'opacity-0');
        removalTimer = window.setTimeout(() => toast.remove(), 200);
    };

    const dismissTimer = window.setTimeout(dismissToast, Number.isNaN(duration) ? 2800 : duration);

    toast.querySelector('[data-toast-close]')?.addEventListener('click', () => {
        window.clearTimeout(dismissTimer);
        window.clearTimeout(removalTimer);
        dismissToast();
    });
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

// Editing a user: the password fields stay closed until someone means to change it.
document.querySelectorAll('[data-password-open]').forEach((button) => {
    const fields = document.getElementById(button.dataset.passwordOpen);

    button.addEventListener('click', () => {
        fields.classList.remove('hidden');
        fields.querySelectorAll('input').forEach((input) => { input.disabled = false; });
        button.classList.add('hidden');
        fields.querySelector('input')?.focus();
    });
});

// A password easy to say out loud: a word, a dash and four digits (letters and numbers, 8+).
const passwordWords = ['Mango', 'Lluvia', 'Ceiba', 'Tortuga', 'Volcan', 'Laguna', 'Colibri', 'Pino', 'Arena', 'Coral', 'Nube', 'Jaguar', 'Cacao', 'Sendero', 'Brisa', 'Puma'];

document.querySelectorAll('[data-password-generate]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordGenerate);

    button.addEventListener('click', () => {
        const random = new Uint32Array(2);
        crypto.getRandomValues(random);
        input.value = `${passwordWords[random[0] % passwordWords.length]}-${String(random[1] % 10000).padStart(4, '0')}`;

        // Shown, so it can be copied and handed to the user.
        if (input.type === 'password') {
            document.querySelector(`[data-password-toggle="${input.id}"]`)?.click();
        }

        input.dispatchEvent(new Event('input'));
    });
});

document.querySelectorAll('[data-password-checklist]').forEach((list) => {
    const input = document.getElementById(list.dataset.passwordChecklist);
    const rules = {
        length: (value) => value.length >= 8,
        letter: (value) => /\p{L}/u.test(value),
        number: (value) => /\d/.test(value),
    };
    const update = () => {
        list.querySelectorAll('[data-rule]').forEach((item) => {
            const met = rules[item.dataset.rule](input.value);
            item.classList.toggle('text-leaf-700', met);
            item.classList.toggle('font-semibold', met);
            item.textContent = `${met ? '✓ ' : ''}${item.textContent.replace(/^✓ /, '')}`;
        });
    };

    input.addEventListener('input', update);
    update();
});

// Who already has this address, asked as soon as the field is left.
document.querySelectorAll('[data-email-check]').forEach((input) => {
    const notice = document.querySelector('[data-email-taken]');

    input.addEventListener('change', async () => {
        const email = input.value.trim();

        if (email === '') {
            notice.classList.add('hidden');

            return;
        }

        const query = new URLSearchParams({ email, ...(input.dataset.emailIgnore ? { ignore: input.dataset.emailIgnore } : {}) });

        try {
            const response = await fetch(`${input.dataset.emailCheck}?${query}`, { headers: { Accept: 'application/json' } });
            const result = await response.json();

            notice.replaceChildren();

            if (! result.taken) {
                notice.classList.add('hidden');

                return;
            }

            const link = document.createElement('a');
            link.href = result.url;
            link.className = 'underline';
            link.textContent = 'Ver usuario';
            notice.append(`${result.message} `, link);
            notice.classList.remove('hidden');
        } catch {
            // The server checks it again on save; a failed lookup only skips the early warning.
        }
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    let confirmationPending = false;
    let confirmationPassed = false;

    form.addEventListener('submit', async (event) => {
        if (confirmationPassed) {
            confirmationPassed = false;

            return;
        }

        event.preventDefault();

        if (confirmationPending) {
            return;
        }

        confirmationPending = true;
        const submitter = event.submitter;
        const confirmed = await showAppDialog({
            title: '¿Confirmas esta acción?',
            messages: [form.dataset.confirm],
            type: 'warning',
            confirmText: 'Continuar',
            cancelText: 'Cancelar',
        });
        confirmationPending = false;

        if (! confirmed) {
            return;
        }

        confirmationPassed = true;

        if (submitter) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
        confirmationPassed = false;
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
    const warningDialog = form.querySelector('[data-stock-warning-dialog]');
    const warningProduct = form.querySelector('[data-stock-warning-product]');
    const warningAvailable = form.querySelector('[data-stock-warning-available]');
    const warningShortage = form.querySelector('[data-stock-warning-shortage]');
    let stockCheckPassed = false;
    let pendingSubmitter = null;

    const fetchQuote = async () => {
        if (! presentation.value || ! quantity.value) {
            throw new Error('Selecciona una presentación e indica la cantidad.');
        }

        const endpoint = new URL(form.dataset.quoteUrl, window.location.origin);
        endpoint.searchParams.set('product_presentation_id', presentation.value);
        endpoint.searchParams.set('quantity', quantity.value);
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        const payload = await response.json();

        if (! response.ok) {
            const message = Object.values(payload.errors ?? {}).flat()[0]
                ?? payload.message
                ?? 'No fue posible verificar la disponibilidad.';
            throw new Error(message);
        }

        return payload.data;
    };

    const submitAfterStockCheck = () => {
        stockCheckPassed = true;

        if (pendingSubmitter) {
            form.requestSubmit(pendingSubmitter);
        } else {
            form.requestSubmit();
        }
    };

    presentation.addEventListener('change', () => {
        stockCheckPassed = false;
    });
    quantity.addEventListener('input', () => {
        stockCheckPassed = false;
    });

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

    form.addEventListener('submit', async (event) => {
        if (stockCheckPassed) {
            return;
        }

        event.preventDefault();
        output.classList.remove('hidden');
        output.innerHTML = '<p class="rounded-xl bg-stone-50 p-4 text-sm text-ink-600">Verificando disponibilidad…</p>';

        try {
            const quote = await fetchQuote();
            const stock = quote.stock;

            if (stock.has_shortage) {
                output.innerHTML = `<p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">Este producto dejaría un faltante de ${escapeHtml(formatOrderQuantity(stock.shortage_quantity))} ${escapeHtml(stock.base_unit_symbol)}.</p>`;
                pendingSubmitter = event.submitter;
                warningProduct.textContent = `${quote.selected.product_name} · ${quote.selected.presentation_name}`;
                warningAvailable.textContent = `${formatOrderQuantity(stock.available_quantity)} ${stock.base_unit_symbol}`;
                warningShortage.textContent = `${formatOrderQuantity(stock.shortage_quantity)} ${stock.base_unit_symbol}`;
                warningDialog.showModal();

                return;
            }

            pendingSubmitter = event.submitter;
            submitAfterStockCheck();
        } catch (error) {
            showOrderQuoteError(output, error.message);
        }
    });

    form.querySelector('[data-stock-warning-cancel]')?.addEventListener('click', () => {
        pendingSubmitter = null;
        warningDialog.close();
    });

    form.querySelector('[data-stock-warning-accept]')?.addEventListener('click', () => {
        warningDialog.close();
        submitAfterStockCheck();
    });

    warningDialog?.addEventListener('cancel', () => {
        pendingSubmitter = null;
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

document.querySelectorAll('[data-cash-count]').forEach((form) => {
    const input = form.querySelector('[data-cash-declared]');
    const output = form.querySelector('[data-cash-difference]');
    // Cents avoid float drift when comparing what was counted with what was expected.
    const expected = Math.round(Number(form.dataset.cashExpected || 0) * 100);
    const money = (cents) => `L ${(cents / 100).toLocaleString('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const update = () => {
        if (input.value === '') {
            output.textContent = '';

            return;
        }

        const difference = Math.round(Number(input.value) * 100) - expected;
        output.textContent = difference === 0 ? 'Cuadra ✓' : `${difference < 0 ? 'Faltan' : 'Sobran'} ${money(Math.abs(difference))}`;
        output.classList.toggle('text-emerald-700', difference === 0);
        output.classList.toggle('text-red-700', difference !== 0);
    };

    input.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-delivery-quantity]').forEach((input) => {
    input.addEventListener('focus', () => input.select());
    input.addEventListener('mouseup', (event) => event.preventDefault());

    if (input.name.endsWith('[delivered_quantity]')) {
        return;
    }

    input.addEventListener('input', () => {
        const rowInputs = [...input.closest('tr').querySelectorAll('[data-delivery-quantity]')];
        const delivered = rowInputs.find((candidate) => candidate.name.endsWith('[delivered_quantity]'));
        const notDelivered = rowInputs
            .filter((candidate) => candidate !== delivered)
            .reduce((total, candidate) => total + Number(candidate.value || 0), 0);

        delivered.value = String(Math.max(Number((Number(delivered.max) - notDelivered).toFixed(6)), 0));
    });
});

document.querySelectorAll('[data-delivery-outcome-form]').forEach((form) => {
    const reasonWrap = form.querySelector('[data-return-reason-wrap]');
    const reason = form.querySelector('[data-return-reason]');
    const returnedInputs = [...form.querySelectorAll('[data-delivery-returned]')];
    const syncReturnReason = () => {
        const hasReturn = returnedInputs.some((input) => Number(input.value || 0) > 0);

        reasonWrap.classList.toggle('hidden', ! hasReturn);
        reason.required = hasReturn;

        if (! hasReturn) {
            reason.value = '';
        }
    };

    returnedInputs.forEach((input) => input.addEventListener('input', syncReturnReason));
    syncReturnReason();
});

document.querySelectorAll('[data-combined-payment-form]').forEach((form) => {
    const rows = form.querySelector('[data-payment-rows]');
    const template = form.querySelector('[data-payment-row-template]');
    const addButton = form.querySelector('[data-add-payment]');
    const submitButton = form.querySelector('[data-payment-submit]');
    const paymentTotal = form.querySelector('[data-payment-total]');
    const creditTotal = form.querySelector('[data-credit-total]');
    const error = form.querySelector('[data-payment-error]');
    const creditReasonWrap = form.querySelector('[data-credit-reason-wrap]');
    const balance = Number(form.dataset.balance ?? 0);
    const currency = form.dataset.currency ?? 'HNL';
    let nextIndex = 1;

    const formatMoney = (value) => `${currency} ${new Intl.NumberFormat('es-HN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value)}`;

    const syncReference = (row) => {
        const method = row.querySelector('[data-payment-method]');
        const reference = row.querySelector('[data-payment-reference]');
        const requiredLabel = row.querySelector('[data-reference-required-label]');
        const isRequired = method.selectedOptions[0]?.dataset.requiresReference === 'true';
        const isCredit = method.selectedOptions[0]?.dataset.isCredit === 'true';

        reference.required = isRequired && ! isCredit;
        reference.disabled = isCredit;
        reference.value = isCredit ? '' : reference.value;
        requiredLabel.textContent = isRequired && ! isCredit ? '*' : '(opcional)';
    };

    const syncTotals = () => {
        const total = [...rows.querySelectorAll('[data-payment-amount]')]
            .reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        const remaining = balance - total;
        const isOverBalance = total > balance + 0.00001;
        const isExact = Math.abs(remaining) <= 0.00001;
        const hasCredit = [...rows.querySelectorAll('[data-payment-method]')]
            .some((select) => select.selectedOptions[0]?.dataset.isCredit === 'true');

        paymentTotal.textContent = formatMoney(total);
        creditTotal.textContent = formatMoney(Math.abs(remaining));
        creditTotal.closest('div').querySelector('p').textContent = isOverBalance ? 'Monto excedente' : 'Falta distribuir';
        error.textContent = isOverBalance
            ? 'La distribución supera el total entregado.'
            : 'La distribución debe completar exactamente el total entregado.';
        error.classList.toggle('hidden', isExact);
        submitButton.disabled = total <= 0 || ! isExact;
        creditReasonWrap.classList.toggle('hidden', ! hasCredit);
    };

    const bindRow = (row) => {
        const method = row.querySelector('[data-payment-method]');

        method.addEventListener('change', () => {
            syncReference(row);
            syncTotals();
        });
        row.querySelector('[data-payment-amount]').addEventListener('input', syncTotals);
        row.querySelector('[data-remove-payment]')?.addEventListener('click', () => {
            row.remove();
            addButton.disabled = false;
            syncTotals();
        });
        syncReference(row);
    };

    rows.querySelectorAll('[data-payment-row]').forEach(bindRow);
    addButton.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-payment-row]').length >= 10) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const row = wrapper.firstElementChild;
        nextIndex += 1;
        rows.append(row);
        bindRow(row);
        syncTotals();
        row.querySelector('[data-payment-method]').focus();
        addButton.disabled = rows.querySelectorAll('[data-payment-row]').length >= 10;
    });
    syncTotals();
});

const invoiceDialog = document.querySelector('[data-invoice-dialog]');

if (invoiceDialog) {
    const shareButton = invoiceDialog.querySelector('[data-invoice-share]');

    invoiceDialog.querySelector('[data-invoice-dialog-finish]').addEventListener('click', () => {
        invoiceDialog.close();
    });

    shareButton.addEventListener('click', async (event) => {
        event.preventDefault();
        const originalText = shareButton.textContent;
        const downloadAndOpenWhatsapp = () => {
            const link = document.createElement('a');

            link.href = shareButton.dataset.downloadUrl;
            link.download = shareButton.dataset.filename;
            document.body.append(link);
            link.click();
            link.remove();
            window.open(shareButton.href, '_blank', 'noopener');
        };

        if (! navigator.share || ! navigator.canShare) {
            downloadAndOpenWhatsapp();

            return;
        }

        shareButton.textContent = 'Preparando factura…';

        try {
            const response = await fetch(shareButton.dataset.downloadUrl, { credentials: 'same-origin' });

            if (! response.ok) {
                throw new Error('No se pudo generar la factura.');
            }

            const file = new File([await response.blob()], shareButton.dataset.filename, { type: 'application/pdf' });

            if (navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    title: shareButton.dataset.shareTitle,
                    text: shareButton.dataset.shareText,
                });
            } else {
                downloadAndOpenWhatsapp();
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                downloadAndOpenWhatsapp();
            }
        } finally {
            shareButton.textContent = originalText;
        }
    });

    invoiceDialog.showModal();
}

document.querySelectorAll('[data-customer-payment-form]').forEach((form) => {
    const rows = form.querySelector('[data-customer-payment-rows]');
    const template = form.querySelector('[data-customer-payment-row-template]');
    const addButton = form.querySelector('[data-add-customer-payment]');
    const totalInput = form.querySelector('[data-customer-payment-total-input]');
    const allocationInputs = [...form.querySelectorAll('[data-receivable-allocation]')];
    const receivedTotal = form.querySelector('[data-customer-received-total]');
    const appliedTotal = form.querySelector('[data-customer-applied-total]');
    const unallocatedTotal = form.querySelector('[data-customer-unallocated-total]');
    const error = form.querySelector('[data-customer-payment-error]');
    const submit = form.querySelector('[data-customer-payment-submit]');
    const currency = form.dataset.currency ?? 'HNL';
    let nextIndex = 1;

    const formatMoney = (value) => `${currency} ${new Intl.NumberFormat('es-HN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value)}`;

    const syncReference = (row) => {
        const method = row.querySelector('[data-customer-payment-method]');
        const reference = row.querySelector('[data-customer-payment-reference]');
        const referenceLabel = row.querySelector('[data-customer-reference-label]');
        const isRequired = method.selectedOptions[0]?.dataset.requiresReference === 'true';

        reference.required = isRequired;
        referenceLabel.textContent = isRequired ? '*' : '(opcional)';
    };

    const syncTotals = () => {
        const received = [...rows.querySelectorAll('[data-customer-payment-line-amount]')]
            .reduce((total, input) => total + (Number(input.value) || 0), 0);
        const applied = allocationInputs.reduce((total, input) => total + (Number(input.value) || 0), 0);
        const difference = received - applied;
        const isExact = received > 0 && Math.abs(difference) <= 0.00001;

        totalInput.value = received.toFixed(2);
        receivedTotal.textContent = formatMoney(received);
        appliedTotal.textContent = formatMoney(applied);
        unallocatedTotal.textContent = formatMoney(Math.abs(difference));
        unallocatedTotal.closest('div').querySelector('p').textContent = difference < 0 ? 'Monto excedente' : 'Falta aplicar';
        error.classList.toggle('hidden', received <= 0 || isExact);
        submit.disabled = ! isExact;
    };

    const autoAllocate = () => {
        let remaining = [...rows.querySelectorAll('[data-customer-payment-line-amount]')]
            .reduce((total, input) => total + (Number(input.value) || 0), 0);

        allocationInputs.forEach((input) => {
            const allocation = Math.min(remaining, Number(input.dataset.outstanding) || 0);
            input.value = allocation > 0 ? allocation.toFixed(2) : '0';
            remaining = Math.max(remaining - allocation, 0);
        });
        syncTotals();
    };

    const bindRow = (row) => {
        row.querySelector('[data-customer-payment-method]').addEventListener('change', () => syncReference(row));
        row.querySelector('[data-customer-payment-line-amount]').addEventListener('input', syncTotals);
        row.querySelector('[data-remove-customer-payment]')?.addEventListener('click', () => {
            row.remove();
            addButton.disabled = false;
            syncTotals();
        });
        syncReference(row);
    };

    rows.querySelectorAll('[data-customer-payment-row]').forEach(bindRow);
    addButton.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-customer-payment-row]').length >= 10) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const row = wrapper.firstElementChild;
        nextIndex += 1;
        rows.append(row);
        bindRow(row);
        syncTotals();
        row.querySelector('[data-customer-payment-method]').focus();
        addButton.disabled = rows.querySelectorAll('[data-customer-payment-row]').length >= 10;
    });
    allocationInputs.forEach((input) => input.addEventListener('input', syncTotals));
    form.querySelector('[data-auto-allocate]').addEventListener('click', autoAllocate);
    syncTotals();
});

document.querySelectorAll('[data-driver-dashboard]').forEach((dashboard) => {
    const buttons = dashboard.querySelectorAll('[data-run-filter]');
    const runs = dashboard.querySelectorAll('[data-driver-run]');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.runFilter;

            buttons.forEach((candidate) => {
                const selected = candidate === button;
                candidate.setAttribute('aria-pressed', String(selected));
                candidate.classList.toggle('bg-ink-950', selected);
                candidate.classList.toggle('text-white', selected);
            });
            runs.forEach((run) => {
                run.classList.toggle('hidden', filter !== 'all' && run.dataset.driverRun !== filter);
            });
        });
    });
});

function showOrderQuoteError(output, message) {
    output.classList.remove('hidden');
    output.innerHTML = `<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700">${escapeHtml(message)}</p>`;
}

function formatOrderQuantity(value) {
    return new Intl.NumberFormat('es-HN', { maximumFractionDigits: 6 }).format(Number(value ?? 0));
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');

    return element.innerHTML;
}
