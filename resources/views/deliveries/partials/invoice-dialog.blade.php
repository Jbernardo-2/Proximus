<dialog data-invoice-dialog aria-labelledby="invoice-dialog-title" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl border-0 bg-white p-0 text-ink-950 shadow-2xl backdrop:bg-ink-950/50 backdrop:backdrop-blur-sm">
    <div class="p-6 text-center sm:p-8">
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-mint-100 text-2xl font-black text-leaf-700" aria-hidden="true">✓</span>
        <h2 class="mt-4 text-xl font-black" id="invoice-dialog-title">Factura {{ $invoiceDialog['invoiceNumber'] }} generada</h2>
        <p class="mt-2 text-sm leading-6 text-ink-600">{{ $invoiceDialog['customerName'] }} · {{ $invoiceDialog['total'] }}</p>
        <p class="mt-1 text-sm font-semibold text-ink-800">¿Qué deseas hacer?</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a class="btn-primary" target="_blank" rel="noopener" href="{{ $invoiceDialog['printLetterUrl'] }}">Imprimir tamaño carta</a>
            <a class="btn-primary" target="_blank" rel="noopener" href="{{ $invoiceDialog['printTicketUrl'] }}">Imprimir ticket 80 mm</a>
            <a
                class="btn-secondary"
                target="_blank"
                rel="noopener"
                href="{{ $invoiceDialog['whatsappUrl'] }}"
                data-invoice-share
                data-download-url="{{ $invoiceDialog['downloadUrl'] }}"
                data-filename="{{ $invoiceDialog['filename'] }}"
                data-share-title="{{ $invoiceDialog['shareTitle'] }}"
                data-share-text="{{ $invoiceDialog['shareText'] }}"
            >Compartir</a>
            <button class="btn-secondary" type="button" data-invoice-dialog-finish>Terminar</button>
        </div>
    </div>
</dialog>
