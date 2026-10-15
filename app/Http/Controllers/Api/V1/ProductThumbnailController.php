<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductThumbnails;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Small JPEG of a product photo for the phone: about 15 KB instead of the original,
 * which can weigh megabytes. Made on the first request and kept for the next ones.
 */
class ProductThumbnailController extends Controller
{
    public function __invoke(Product $product, ProductThumbnails $thumbnails): BinaryFileResponse
    {
        $path = $thumbnails->pathFor($product);
        abort_if($path === null, 404, 'El producto no tiene fotografía.');

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
