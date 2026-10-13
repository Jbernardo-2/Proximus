@extends('layouts.app')

@section('title', 'Datos de la empresa')
@section('page-title', 'Datos de la empresa')
@section('page-subtitle', 'Configura la identidad y los datos de contacto de la empresa que utiliza Proximus.')

@section('content')
    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" class="max-w-5xl space-y-5">
        @csrf
        @method('PUT')

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Identidad de la empresa</h2>
                <p class="mt-1 text-sm text-ink-600">Estos datos identifican a la empresa usuaria y se utilizarán en el panel, reportes y documentos internos.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="commercial_name">Nombre comercial *</label>
                    <input class="form-input" id="commercial_name" name="commercial_name" required maxlength="180" autocomplete="organization" value="{{ old('commercial_name', $companyProfile->commercial_name) }}">
                </div>
                <div>
                    <label class="form-label" for="legal_name">Razón social</label>
                    <input class="form-input" id="legal_name" name="legal_name" maxlength="180" value="{{ old('legal_name', $companyProfile->legal_name) }}">
                </div>
                <div>
                    <label class="form-label" for="rtn">RTN</label>
                    <input class="form-input" id="rtn" name="rtn" maxlength="30" inputmode="numeric" value="{{ old('rtn', $companyProfile->rtn) }}" placeholder="08011999123456">
                </div>
                <div>
                    <label class="form-label" for="slogan">Slogan</label>
                    <input class="form-input" id="slogan" name="slogan" maxlength="255" value="{{ old('slogan', $companyProfile->slogan) }}" placeholder="Tu promesa de servicio">
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Contacto</h2>
                <p class="mt-1 text-sm text-ink-600">Información institucional para consultas, reportes y documentos internos.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="phone">Teléfono</label>
                    <input class="form-input" id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="{{ old('phone', $companyProfile->phone) }}" placeholder="2234-5678">
                </div>
                <div>
                    <label class="form-label" for="whatsapp">WhatsApp</label>
                    <input class="form-input" id="whatsapp" name="whatsapp" type="tel" maxlength="30" value="{{ old('whatsapp', $companyProfile->whatsapp) }}" placeholder="9876-5432">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input class="form-input" id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email', $companyProfile->email) }}" placeholder="contacto@empresa.com">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="address">Dirección</label>
                    <textarea class="form-input min-h-28" id="address" name="address" maxlength="1000" autocomplete="street-address" placeholder="Ciudad, colonia, calle y referencias">{{ old('address', $companyProfile->address) }}</textarea>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-7">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-ink-950">Logotipo</h2>
                <p class="mt-1 text-sm text-ink-600">Usa una imagen JPG, PNG o WebP de hasta 5 MB. Se recomienda fondo transparente o blanco.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-[12rem_1fr] md:items-start">
                <div class="grid aspect-square place-items-center overflow-hidden rounded-2xl border border-stone-200 bg-stone-50 p-4">
                    @if ($companyProfile->logo_path)
                        <img src="{{ $companyProfile->logoUrl() }}" alt="Logotipo actual de {{ $companyProfile->commercial_name }}" class="size-full object-contain">
                    @else
                        <span class="grid size-20 place-items-center rounded-2xl bg-leaf-700 text-3xl font-black text-white" aria-hidden="true">
                            {{ str($companyProfile->commercial_name)->substr(0, 1)->upper() }}
                        </span>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="form-label" for="logo">Seleccionar logotipo</label>
                        <input class="form-input file:mr-4 file:rounded-lg file:border-0 file:bg-mint-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-leaf-700" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                        <p class="form-help">Al guardar una nueva imagen se eliminará el logotipo anterior.</p>
                    </div>

                    @if ($companyProfile->logo_path)
                        <input type="hidden" name="remove_logo" value="0">
                        <label class="flex items-start gap-3 rounded-xl border border-stone-200 p-4">
                            <input class="mt-0.5 size-4 rounded border-stone-300 text-leaf-700" type="checkbox" name="remove_logo" value="1" @checked((bool) old('remove_logo'))>
                            <span>
                                <span class="block text-sm font-semibold text-ink-950">Eliminar el logotipo actual</span>
                                <span class="block text-xs text-ink-600">El sistema volverá a mostrar la inicial del nombre comercial.</span>
                            </span>
                        </label>
                    @else
                        <input type="hidden" name="remove_logo" value="0">
                    @endif
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="btn-primary">Guardar datos de la empresa</button>
        </div>
    </form>
@endsection
