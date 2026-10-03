<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceTierRequest;
use App\Http\Requests\UpdatePriceTierRequest;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Http\RedirectResponse;

class PriceTierController extends Controller
{
    public function store(
        StorePriceTierRequest $request,
        Product $product,
        ProductPresentation $presentation,
    ): RedirectResponse {
        $presentation->priceTiers()->create($request->validated());

        return back()->with('success', 'Precio por cantidad agregado correctamente.');
    }

    public function update(
        UpdatePriceTierRequest $request,
        Product $product,
        ProductPresentation $presentation,
        PriceTier $priceTier,
    ): RedirectResponse {
        $priceTier->update($request->validated());

        return back()->with('success', 'Precio por cantidad actualizado correctamente.');
    }

    public function destroy(
        Product $product,
        ProductPresentation $presentation,
        PriceTier $priceTier,
    ): RedirectResponse {
        $priceTier->delete();

        return back()->with('success', 'Precio por cantidad eliminado correctamente.');
    }
}
