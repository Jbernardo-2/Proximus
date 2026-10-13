@if (session('success'))
    <div class="fixed inset-x-4 top-4 z-[100] flex items-start gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-900 shadow-lg transition duration-200 sm:left-auto sm:right-5 sm:w-full sm:max-w-sm" role="status" aria-live="polite" aria-atomic="true" data-toast data-toast-duration="2800">
        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-100 font-black text-emerald-700" aria-hidden="true">✓</span>
        <p class="min-w-0 flex-1 pt-0.5 font-semibold">{{ session('success') }}</p>
        <button class="-mr-1 grid size-7 shrink-0 place-items-center rounded-full text-lg leading-none text-emerald-700 transition hover:bg-emerald-50" type="button" aria-label="Cerrar mensaje" data-toast-close>×</button>
    </div>
@endif

@if (session('error'))
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <span aria-hidden="true">!</span>
        <p>{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <ul class="list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<dialog data-app-dialog aria-labelledby="app-dialog-title" aria-describedby="app-dialog-message" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl border-0 bg-white p-0 text-ink-950 shadow-2xl backdrop:bg-ink-950/50 backdrop:backdrop-blur-sm">
    <div class="relative p-6 text-center sm:p-8">
        <button class="absolute right-4 top-4 grid size-9 place-items-center rounded-full text-xl text-ink-600 transition hover:bg-stone-100" type="button" data-app-dialog-close aria-label="Cerrar">×</button>
        <span class="mx-auto grid size-14 place-items-center rounded-full bg-mint-100 text-2xl font-black text-leaf-700" data-app-dialog-icon aria-hidden="true">i</span>
        <h2 class="mt-4 text-xl font-black" id="app-dialog-title" data-app-dialog-title></h2>
        <div class="mt-3 space-y-2 text-sm leading-6 text-ink-600" id="app-dialog-message" data-app-dialog-message></div>
        <div class="mt-7 flex flex-col-reverse justify-center gap-3 sm:flex-row">
            <button class="btn-secondary hidden" type="button" data-app-dialog-cancel>Cancelar</button>
            <button class="btn-primary min-w-32" type="button" data-app-dialog-confirm>Entendido</button>
        </div>
    </div>
</dialog>
