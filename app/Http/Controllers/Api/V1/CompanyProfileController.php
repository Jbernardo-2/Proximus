<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordSecurityEventAction;
use App\Actions\UpdateCompanyProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\JsonResponse;

class CompanyProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->serialize(CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID))]);
    }

    /** Send as multipart/form-data when it includes a new logo. */
    public function update(
        UpdateCompanyProfileRequest $request,
        UpdateCompanyProfileAction $updateCompanyProfile,
        RecordSecurityEventAction $recordSecurityEvent,
    ): JsonResponse {
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $oldLogoPath = $companyProfile->logo_path;
        $updated = $updateCompanyProfile->handle(
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
            subject: $updated,
            metadata: ['channel' => 'api', 'logo_changed' => $oldLogoPath !== $updated->logo_path],
        );

        return response()->json(['data' => $this->serialize($updated)]);
    }

    /** @return array<string, mixed> */
    private function serialize(CompanyProfile $company): array
    {
        return [
            ...$company->only(['commercial_name', 'legal_name', 'rtn', 'phone', 'whatsapp', 'email', 'address', 'slogan']),
            'logo_url' => $company->logo_path ? asset('storage/'.$company->logo_path) : null,
        ];
    }
}
