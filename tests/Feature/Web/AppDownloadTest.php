<?php

namespace Tests\Feature\Web;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    public function test_anyone_can_download_the_uploaded_app_with_its_version(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('releases/proximus.apk', 'apk');
        Storage::disk('local')->put('releases/version.txt', "0.1.1\n");

        $this->get('/app')
            ->assertOk()
            ->assertSee('Versión 0.1.1')
            ->assertSee(route('app-download.file'), false);

        $this->get('/app/descargar')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertDownload('proximus-0.1.1.apk');
    }

    public function test_without_an_uploaded_app_the_page_says_so_and_the_file_is_not_found(): void
    {
        Storage::fake('local');

        $this->get('/app')->assertOk()->assertSee('todavía no está disponible')->assertDontSee(route('app-download.file'), false);
        $this->get('/app/descargar')->assertNotFound();
    }
}
