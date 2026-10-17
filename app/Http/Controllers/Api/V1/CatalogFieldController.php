<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Services\ProductThumbnails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What the warehouse phone changes on a product while walking the shelves: its
 * photo and the barcode of one presentation, without sending the whole product.
 */
class CatalogFieldController extends Controller
{
    public function photo(Request $request, Product $product, ProductThumbnails $thumbnails): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']], [], ['image' => 'foto']);
        $oldImage = $product->image_path;
        $newImage = $request->file('image')->store('products', 'public');

        try {
            $product->update(['image_path' => $newImage]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newImage);

            throw $exception;
        }

        if ($oldImage !== null && $oldImage !== $newImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return response()->json(['data' => [
            'id' => $product->id,
            'image_url' => asset('storage/'.$newImage),
            'thumbnail_url' => route('api.v1.sync.product-images.show', $product),
            'image_version' => $thumbnails->version($product->refresh()),
        ]]);
    }

    public function barcode(Request $request, Product $product, ProductPresentation $presentation): JsonResponse
    {
        $data = $request->validate(['barcode' => ['nullable', 'string', 'max:80']], [], ['barcode' => 'código de barras']);
        $barcode = trim((string) ($data['barcode'] ?? ''));
        // Say whose code it is, so the keeper knows which label is wrong.
        $owner = $barcode === '' ? null : ProductPresentation::query()->with('product')->where('barcode', $barcode)->whereKeyNot($presentation->id)->first();

        if ($owner !== null) {
            throw ValidationException::withMessages(['barcode' => ["Ese código ya es de {$owner->product?->name} · {$owner->name}."]]);
        }

        $presentation->update(['barcode' => $barcode === '' ? null : $barcode]);
        // The phones download the catalog by delta: the product must look changed.
        $product->touch();

        return response()->json(['data' => [
            'id' => $presentation->id,
            'product_id' => $product->id,
            'barcode' => $presentation->barcode,
        ]]);
    }
}
