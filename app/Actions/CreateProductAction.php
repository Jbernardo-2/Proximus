<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateProductAction
{
    public function handle(array $data, ?UploadedFile $image = null): Product
    {
        $imagePath = $this->storeImage($image);

        try {
            return DB::transaction(function () use ($data, $imagePath): Product {
                $product = Product::query()->create([
                    ...Arr::only($data, [
                        'category_id',
                        'brand_id',
                        'base_unit_id',
                        'sku',
                        'name',
                        'slug',
                        'description',
                        'allows_decimal',
                        'is_active',
                    ]),
                    'image_path' => $imagePath,
                ]);

                $product->presentations()->create([
                    'name' => $data['base_presentation_name'],
                    'barcode' => $data['base_barcode'] ?? null,
                    'conversion_factor' => '1',
                    'sale_price' => $data['base_sale_price'],
                    'is_base' => true,
                    'is_sellable' => $data['base_is_sellable'],
                    'is_purchasable' => $data['base_is_purchasable'],
                    'is_active' => true,
                ]);

                return $product->load(['category', 'brand', 'baseUnit', 'basePresentation.priceTiers', 'presentations.priceTiers']);
            });
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = $image->store('products', 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => 'No fue posible guardar la imagen. Intenta nuevamente o crea el producto sin fotografía.',
            ]);
        }

        return $path;
    }
}
