const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    mobileMenu?.classList.toggle('hidden');
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

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');

    return element.innerHTML;
}
