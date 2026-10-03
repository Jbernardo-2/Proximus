<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductPresentationRequest;
use App\Http\Requests\UpdateProductPresentationRequest;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Http\RedirectResponse;

class ProductPresentationController extends Controller
{
    public function store(StoreProductPresentationRequest $request, Product $product): RedirectResponse
    {
        $product->presentations()->create([
            ...$request->validated(),
            'is_base' => false,
        ]);

        return back()->with('success', 'Presentación agregada correctamente.');
    }

    public function update(
        UpdateProductPresentationRequest $request,
        Product $product,
        ProductPresentation $presentation,
    ): RedirectResponse {
        $presentation->update($request->validated());

        return back()->with('success', 'Presentación actualizada correctamente.');
    }

    public function destroy(Product $product, ProductPresentation $presentation): RedirectResponse
    {
        if ($presentation->is_base) {
            return back()->with('error', 'La presentación base no se puede eliminar.');
        }

        if ($presentation->productSuppliers()->exists()) {
            return back()->with('error', 'La presentación está vinculada a proveedores y no se puede eliminar.');
        }

        $presentation->delete();

        return back()->with('success', 'Presentación eliminada correctamente.');
    }
}
