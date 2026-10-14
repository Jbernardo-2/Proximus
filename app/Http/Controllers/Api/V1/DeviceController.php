<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordSecurityEventAction;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncOperation;
use App\Models\User;
use App\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = Device::query()
            ->with('user')
            ->withMax('syncOperations as last_sync_at', 'processed_at')
            ->withCount(['syncOperations as rejected_operations_count' => fn ($query) => $query->where('status', SyncOperation::REJECTED)])
            ->orderByRaw('revoked_at is not null')
            ->orderByDesc('last_seen_at')
            ->paginate(50);

        return response()->json([
            'data' => collect($devices->items())->map(fn (Device $device): array => $this->serialize($device))->all(),
            'meta' => ['current_page' => $devices->currentPage(), 'last_page' => $devices->lastPage(), 'total' => $devices->total()],
        ]);
    }

    public function revoke(Request $request, Device $device, RecordSecurityEventAction $recordSecurityEvent): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $device->revoke($actor);
        $recordSecurityEvent->handle(SecurityEvent::DeviceRevoked, $request, actor: $actor, subject: $device, metadata: ['device_code' => $device->code]);

        return response()->json(['data' => $this->serialize($device->load('user'))]);
    }

    public function restore(Request $request, Device $device, RecordSecurityEventAction $recordSecurityEvent): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $device->restore();
        $recordSecurityEvent->handle(SecurityEvent::DeviceRestored, $request, actor: $actor, subject: $device, metadata: ['device_code' => $device->code]);

        return response()->json(['data' => $this->serialize($device->load('user'))]);
    }

    /** @return array<string, mixed> */
    private function serialize(Device $device): array
    {
        return [
            'id' => $device->id,
            'code' => $device->code,
            'name' => $device->name,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'user' => $device->user === null ? null : ['id' => $device->user->id, 'name' => $device->user->name],
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at === null ? null : Carbon::parse($device->last_sync_at)->toIso8601String(),
            'rejected_operations_count' => (int) ($device->rejected_operations_count ?? 0),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
        ];
    }
}
