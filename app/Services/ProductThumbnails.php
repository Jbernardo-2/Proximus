<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Thumbnails of product photos, made with GD. Each one is named after the photo it
 * comes from, so replacing the photo gives a new thumbnail and a new URL.
 */
class ProductThumbnails
{
    public const SIZE = 256;

    private const QUALITY = 75;

    /** Version of the photo, for the phone to know when to download it again. */
    public function version(Product $product): ?string
    {
        return $product->image_path === null ? null : substr(sha1($product->image_path), 0, 12);
    }

    /** Absolute path of the thumbnail, made now if missing; null without a usable photo. */
    public function pathFor(Product $product): ?string
    {
        $version = $this->version($product);
        $public = Storage::disk('public');

        if ($version === null || ! $public->exists($product->image_path)) {
            return null;
        }

        $local = Storage::disk('local');
        $thumbnail = "product-thumbnails/{$product->id}-{$version}.jpg";

        if (! $local->exists($thumbnail)) {
            $image = @imagecreatefromstring((string) $public->get($product->image_path));

            if ($image === false) {
                return null;
            }

            $local->put($thumbnail, $this->shrink($image));
        }

        return $local->path($thumbnail);
    }

    private function shrink(\GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::SIZE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        // White behind transparent PNGs, as JPEG has no transparency.
        imagefill($thumbnail, 0, 0, (int) imagecolorallocate($thumbnail, 255, 255, 255));
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($thumbnail, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
