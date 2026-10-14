<?php

namespace App\Http\Controllers;

use App\Actions\RecordSecurityEventAction;
use App\Models\Device;
use App\Models\SyncOperation;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        return view('devices.index', [
            'devices' => Device::query()
                ->with(['user', 'revokedBy'])
                ->withMax('syncOperations as last_sync_at', 'processed_at')
                ->withCount(['syncOperations as rejected_operations_count' => fn ($query) => $query->where('status', SyncOperation::REJECTED)])
                ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                }))
                ->orderByRaw('revoked_at is not null')
                ->orderByDesc('last_seen_at')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function revoke(Request $request, Device $device, RecordSecurityEventAction $recordSecurityEvent): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        DB::transaction(function () use ($device, $actor): void {
            if ($device->personal_access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($device->personal_access_token_id)->delete();
            }

            $device->forceFill([
                'personal_access_token_id' => null,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
            ])->save();
        });
        $recordSecurityEvent->handle(SecurityEvent::DeviceRevoked, $request, actor: $actor, subject: $device, metadata: ['device_code' => $device->code]);

        return back()->with('success', "Teléfono {$device->code} bloqueado. Su sesión se cerró.");
    }

    public function restore(Request $request, Device $device, RecordSecurityEventAction $recordSecurityEvent): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $device->forceFill(['revoked_at' => null, 'revoked_by' => null])->save();
        $recordSecurityEvent->handle(SecurityEvent::DeviceRestored, $request, actor: $actor, subject: $device, metadata: ['device_code' => $device->code]);

        return back()->with('success', "Teléfono {$device->code} reactivado. Ya puede iniciar sesión.");
    }
}
