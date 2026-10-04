<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Warehouse;
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
                $warehouses = Warehouse::query()
                    ->active()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
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
                        'tracks_lots',
                        'tracks_expiration',
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

                foreach ($warehouses as $warehouse) {
                    $product->inventoryStocks()->create([
                        'warehouse_id' => $warehouse->id,
                        'quantity_on_hand' => '0.000000',
                        'quantity_reserved' => '0.000000',
                        'reorder_point' => '0.000000',
                    ]);
                }

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
