<?php

namespace App\Actions;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateCompanyProfileAction
{
    public function handle(
        CompanyProfile $companyProfile,
        array $data,
        ?UploadedFile $logo = null,
        bool $removeLogo = false,
    ): CompanyProfile {
        $oldLogoPath = $companyProfile->logo_path;
        $newLogoPath = $this->storeLogo($logo);
        $attributes = Arr::only($data, [
            'commercial_name',
            'legal_name',
            'rtn',
            'phone',
            'whatsapp',
            'email',
            'address',
            'slogan',
        ]);

        if ($newLogoPath !== null) {
            $attributes['logo_path'] = $newLogoPath;
        } elseif ($removeLogo) {
            $attributes['logo_path'] = null;
        }

        try {
            $companyProfile->update($attributes);
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($oldLogoPath !== null && $oldLogoPath !== $companyProfile->logo_path) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return $companyProfile->refresh();
    }

    private function storeLogo(?UploadedFile $logo): ?string
    {
        if ($logo === null) {
            return null;
        }

        $path = $logo->store('company', 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'logo' => 'No fue posible guardar el logotipo. Intenta nuevamente.',
            ]);
        }

        return $path;
    }
}
