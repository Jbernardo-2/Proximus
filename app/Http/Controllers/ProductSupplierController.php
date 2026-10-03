<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductSupplierRequest;
use App\Http\Requests\UpdateProductSupplierRequest;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductSupplierController extends Controller
{
    public function store(StoreProductSupplierRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $data = $request->validated();

            if ($data['is_preferred']) {
                $product->productSuppliers()->update(['is_preferred' => false]);
            }

            $source = ProductSupplier::withTrashed()
                ->where('supplier_id', $data['supplier_id'])
                ->where('product_presentation_id', $data['product_presentation_id'])
                ->first();

            if ($source === null) {
                $product->productSuppliers()->create($data);

                return;
            }

            $source->restore();
            $source->update([...$data, 'product_id' => $product->id]);
        });

        return back()->with('success', 'Proveedor vinculado correctamente.');
    }

    public function update(
        UpdateProductSupplierRequest $request,
        Product $product,
        ProductSupplier $productSupplier,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $product, $productSupplier): void {
            $data = $request->validated();

            if ($data['is_preferred']) {
                $product->productSuppliers()->whereKeyNot($productSupplier->id)->update(['is_preferred' => false]);
            }

            $productSupplier->update($data);
        });

        return back()->with('success', 'Datos del proveedor actualizados correctamente.');
    }

    public function destroy(Product $product, ProductSupplier $productSupplier): RedirectResponse
    {
        $productSupplier->delete();

        return back()->with('success', 'Vínculo con proveedor eliminado correctamente.');
    }
}
