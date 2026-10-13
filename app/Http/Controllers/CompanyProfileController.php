<?php

namespace App\Http\Controllers;

use App\Actions\RecordSecurityEventAction;
use App\Actions\UpdateCompanyProfileAction;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(): View
    {
        return view('company.edit', [
            'companyProfile' => CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID),
        ]);
    }

    public function update(
        UpdateCompanyProfileRequest $request,
        UpdateCompanyProfileAction $updateCompanyProfile,
        RecordSecurityEventAction $recordSecurityEvent,
    ): RedirectResponse {
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $oldLogoPath = $companyProfile->logo_path;
        $updatedCompanyProfile = $updateCompanyProfile->handle(
            $companyProfile,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        /** @var User $actor */
        $actor = $request->user();
        $recordSecurityEvent->handle(
            SecurityEvent::CompanyProfileUpdated,
            $request,
            actor: $actor,
            subject: $updatedCompanyProfile,
            metadata: [
                'channel' => 'web',
                'logo_changed' => $oldLogoPath !== $updatedCompanyProfile->logo_path,
            ],
        );

        return redirect()->route('company.edit')->with('success', 'Datos de la empresa actualizados correctamente.');
    }
}
