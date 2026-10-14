<?php

namespace App\Http\Controllers\Api\V1;

use App\CustomerVisitReason;
use App\DeliveryOutcomeReason;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Device;
use App\Models\User;
use App\Models\Warehouse;
use App\PaymentMethod;
use App\PaymentTerm;
use App\Services\SyncPullService;
use App\Services\SyncPushService;
use App\SettlementMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    public function manifest(Request $request, SyncPullService $pull): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $company = CompanyProfile::query()->find(CompanyProfile::SINGLETON_ID);
        $device = Device::current($user);
        $warehouse = Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->first();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'currency' => mb_strtoupper((string) config('proximus.currency', 'HNL')),
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value],
            'device' => $device === null ? null : ['id' => $device->id, 'code' => $device->code, 'series' => $device->series()],
            'company' => $company === null ? null : [
                ...$company->only(['commercial_name', 'legal_name', 'rtn', 'phone', 'whatsapp', 'email', 'address', 'slogan']),
                'logo_url' => $company->logo_path ? asset('storage/'.$company->logo_path) : null,
            ],
            'default_warehouse' => $warehouse?->only(['id', 'code', 'name']),
            'resources' => $pull->manifest($user),
            'operations' => SyncPushService::availableOperations($user),
            'catalogs' => [
                'payment_terms' => $this->labels(PaymentTerm::cases()),
                'payment_methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'requires_reference' => $method->requiresReference(),
                ])->all(),
                'settlement_methods' => collect(SettlementMethod::cases())->map(fn (SettlementMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'requires_reference' => $method->requiresReference(),
                    'is_credit' => $method->isCredit(),
                ])->all(),
                'delivery_outcome_reasons' => $this->labels(DeliveryOutcomeReason::cases()),
                'visit_reasons' => $this->labels(CustomerVisitReason::cases()),
            ],
        ]);
    }

    public function pull(Request $request, string $resource, SyncPullService $pull): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($pull->exists($resource), 404);
        abort_unless($pull->allows($user, $resource), 403);
        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.SyncPullService::MAXIMUM_LIMIT],
        ]);

        return response()->json($pull->pull(
            $user,
            $resource,
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? SyncPullService::DEFAULT_LIMIT),
        ));
    }

    public function push(Request $request, SyncPushService $push): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.client_reference' => ['required', 'string', 'max:100', 'distinct'],
            'operations.*.type' => ['required', 'string', Rule::in(SyncPushService::availableOperations($user))],
            'operations.*.payload' => ['required', 'array'],
        ]);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'results' => $push->push($request, $validated['operations']),
        ]);
    }

    /**
     * @param  list<\UnitEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function labels(array $cases): array
    {
        return array_map(fn ($case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
