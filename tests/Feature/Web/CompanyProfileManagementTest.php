<?php

namespace Tests\Feature\Web;

use App\Models\CompanyProfile;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_view_company_profile_and_navigation(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertSee('Datos de la empresa')
            ->assertSee('Nombre comercial')
            ->assertSee('Razón social')
            ->assertSee('RTN')
            ->assertSee('WhatsApp')
            ->assertSee(route('company.edit'), false);
    }

    public function test_non_administrator_cannot_manage_company_profile(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->get(route('company.edit'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->put(route('company.update'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_administrator_can_update_company_profile_and_logo(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->admin()->create();

        $response = $this->actingAs($administrator)->put(route('company.update'), [
            ...$this->validPayload(),
            'commercial_name' => '  Distribuidora La Central  ',
            'email' => '  VENTAS@LACENTRAL.HN  ',
            'logo' => UploadedFile::fake()->image('logo.png', 600, 600),
        ]);

        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $response
            ->assertRedirectToRoute('company.edit')
            ->assertSessionHas('success', 'Datos de la empresa actualizados correctamente.');
        $this->assertSame('Distribuidora La Central', $companyProfile->commercial_name);
        $this->assertSame('ventas@lacentral.hn', $companyProfile->email);
        $this->assertSame('La empresa que cumple', $companyProfile->slogan);
        Storage::disk('public')->assertExists($companyProfile->logo_path);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $administrator->id,
            'event' => SecurityEvent::CompanyProfileUpdated->value,
            'subject_type' => CompanyProfile::class,
            'subject_id' => (string) CompanyProfile::SINGLETON_ID,
        ]);
    }

    public function test_replacing_or_removing_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/old-logo.png', 'old-logo');
        $administrator = User::factory()->admin()->create();
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $companyProfile->update(['logo_path' => 'company/old-logo.png']);

        $this->actingAs($administrator)->put(route('company.update'), [
            ...$this->validPayload(),
            'logo' => UploadedFile::fake()->image('new-logo.webp', 400, 400),
        ])->assertRedirectToRoute('company.edit');

        Storage::disk('public')->assertMissing('company/old-logo.png');
        $newLogoPath = $companyProfile->refresh()->logo_path;
        Storage::disk('public')->assertExists($newLogoPath);

        $this->actingAs($administrator)->put(route('company.update'), [
            ...$this->validPayload(),
            'remove_logo' => '1',
        ])->assertRedirectToRoute('company.edit');

        Storage::disk('public')->assertMissing($newLogoPath);
        $this->assertNull($companyProfile->refresh()->logo_path);
    }

    public function test_non_image_logo_is_rejected_without_changing_profile(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)->put(route('company.update'), [
            ...$this->validPayload(),
            'commercial_name' => 'Nombre no permitido',
            'logo' => UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->assertDatabaseMissing('company_profiles', [
            'id' => CompanyProfile::SINGLETON_ID,
            'commercial_name' => 'Nombre no permitido',
        ]);
    }

    public function test_proximus_brand_stays_fixed_and_company_is_rendered_as_panel_context(): void
    {
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $companyProfile->update([
            'commercial_name' => 'Central <script>alert("x")</script>',
            'slogan' => 'Calidad <b>siempre</b>',
        ]);
        $administrator = User::factory()->admin()->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Proximus')
            ->assertSee('Distribución inteligente')
            ->assertSee('images/proximus-logo-horizontal.svg')
            ->assertSee('favicon.svg')
            ->assertDontSee($companyProfile->commercial_name)
            ->assertDontSee($companyProfile->slogan);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('images/proximus-logo-horizontal.svg')
            ->assertSee('Empresa usuaria')
            ->assertSee($companyProfile->commercial_name)
            ->assertSee($companyProfile->slogan)
            ->assertDontSee($companyProfile->commercial_name, false)
            ->assertDontSee($companyProfile->slogan, false);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'commercial_name' => 'Distribuidora La Central',
            'legal_name' => 'Inversiones La Central, S. de R.L.',
            'rtn' => '08011999123456',
            'phone' => '2234-5678',
            'whatsapp' => '9876-5432',
            'email' => 'ventas@lacentral.hn',
            'address' => 'Tegucigalpa, Francisco Morazán',
            'slogan' => 'La empresa que cumple',
            'remove_logo' => '0',
        ];
    }
}
