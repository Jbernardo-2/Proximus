<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Android app, uploaded by hand to storage/app/private/releases next to a version.txt.
 */
class AppDownloadController extends Controller
{
    private const APK = 'releases/proximus.apk';

    private const VERSION = 'releases/version.txt';

    public function show(): View
    {
        $disk = Storage::disk('local');
        $available = $disk->exists(self::APK);

        return view('app-download', [
            'available' => $available,
            'version' => $available ? trim((string) $disk->get(self::VERSION)) : null,
            'megabytes' => $available ? round($disk->size(self::APK) / 1048576, 1) : null,
            'updatedAt' => $available ? now()->setTimestamp($disk->lastModified(self::APK)) : null,
        ]);
    }

    public function download(): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists(self::APK), 404);
        $version = trim((string) $disk->get(self::VERSION)) ?: 'app';

        return $disk->download(self::APK, "proximus-{$version}.apk", [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
