<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\SyncOperation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a queue of operations recorded on a device, in order, through the same
 * API endpoints used online, so validation, permissions and business rules match.
 */
class SyncPushService
{
    /** Operation type => [HTTP method, path template]; {placeholders} are read from the payload. */
    public const array OPERATIONS = [
        'customer.create' => ['POST', '/api/v1/customers'],
        'order.create' => ['POST', '/api/v1/orders'],
        'visit.no_sale' => ['POST', '/api/v1/customers/{customer_id}/visits'],
        'delivery.depart' => ['POST', '/api/v1/delivery-runs/{delivery_run_id}/depart'],
        'delivery.outcome' => ['PUT', '/api/v1/delivery-runs/{delivery_run_id}/orders/{run_order_id}/outcome'],
        'delivery.payment' => ['POST', '/api/v1/delivery-runs/{delivery_run_id}/orders/{run_order_id}/payments'],
        'customer.payment' => ['POST', '/api/v1/customers/{customer_id}/payments'],
    ];

    public function __construct(private OfflineSync $offline) {}

    /** @return list<string> */
    public static function availableOperations(User $user): array
    {
        return collect(self::OPERATIONS)
            ->keys()
            ->filter(fn (string $operation): bool => match ($operation) {
                'customer.create' => Gate::forUser($user)->allows('create', Customer::class),
                'order.create', 'visit.no_sale' => Gate::forUser($user)->allows('manage-orders'),
                'delivery.depart', 'delivery.outcome', 'delivery.payment' => Gate::forUser($user)->allows('execute-deliveries'),
                'customer.payment' => Gate::forUser($user)->allows('record-customer-payments'),
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{client_reference: string, type: string, payload: array<string, mixed>}>  $operations
     * @return list<array<string, mixed>>
     */
    public function push(Request $request, array $operations): array
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();
        $device = Device::current($user);
        $deviceName = $device !== null ? "{$device->code} · {$device->name}" : ($token instanceof PersonalAccessToken ? $token->name : null);

        return array_map(fn (array $operation): array => $this->apply($request, $user, $device, $deviceName, $operation), $operations);
    }

    /**
     * @param  array{client_reference: string, type: string, payload: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    private function apply(Request $request, User $user, ?Device $device, ?string $deviceName, array $operation): array
    {
        $previous = SyncOperation::query()
            ->where('user_id', $user->id)
            ->where('client_reference', $operation['client_reference'])
            ->first();

        if ($previous?->status === SyncOperation::ACCEPTED) {
            return $this->result($previous, duplicate: true);
        }

        $payload = $operation['payload'];

        if (in_array($operation['type'], ['order.create', 'visit.no_sale'], true)) {
            $payload['client_reference'] ??= $operation['client_reference'];
        }

        $path = $this->path($operation['type'], $payload);

        if ($path === null) {
            [$status, $body] = [Response::HTTP_UNPROCESSABLE_ENTITY, ['message' => 'Faltan los identificadores que requiere la operación.']];
        } elseif ($operation['type'] === 'customer.create' && is_string($payload['id'] ?? null) && ($customer = Customer::withTrashed()->find($payload['id'])) !== null) {
            [$status, $body] = [Response::HTTP_OK, ['data' => ['id' => $customer->id, 'code' => $customer->code, 'business_name' => $customer->business_name]]];
        } elseif ($operation['type'] === 'order.create' && ($existing = $this->existingOrder($user, $payload)) !== null) {
            [$status, $body] = [Response::HTTP_OK, ['data' => ['id' => $existing->id, 'order_number' => $existing->order_number, 'status' => $existing->status->value, 'client_reference' => $existing->client_reference]]];
        } else {
            [$status, $body] = $this->offline->during(
                fn (): array => $this->dispatch($request, self::OPERATIONS[$operation['type']][0], $path, $payload),
                $device,
            );
        }

        $record = SyncOperation::query()->updateOrCreate(
            ['user_id' => $user->id, 'client_reference' => $operation['client_reference']],
            [
                'device_id' => $device?->id,
                'device_name' => $deviceName,
                'type' => $operation['type'],
                'status' => match (true) {
                    $status < 300 => SyncOperation::ACCEPTED,
                    $status < 500 => SyncOperation::REJECTED,
                    default => SyncOperation::FAILED,
                },
                'http_status' => $status,
                'payload' => $payload,
                'response' => $body,
                'occurred_at' => $this->occurredAt($payload),
                'attempts' => ($previous?->attempts ?? 0) + 1,
                'processed_at' => now(),
            ],
        );

        return $this->result($record, duplicate: false);
    }

    /** @param array<string, mixed> $payload */
    private function path(string $type, array $payload): ?string
    {
        $missing = false;
        $path = preg_replace_callback('/\{(\w+)\}/', function (array $match) use ($payload, &$missing): string {
            $value = $payload[$match[1]] ?? null;

            if (! is_string($value) || preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $value) !== 1) {
                $missing = true;

                return '';
            }

            return $value;
        }, self::OPERATIONS[$type][1]);

        return $missing ? null : $path;
    }

    /**
     * Runs the operation through the regular API route as the same user and token.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: array<string, mixed>|null}
     */
    private function dispatch(Request $request, string $method, string $path, array $payload): array
    {
        $operationRequest = Request::create($path, $method, server: [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => (string) $request->header('Authorization'),
            'REMOTE_ADDR' => (string) $request->ip(),
        ], content: (string) json_encode($payload));
        $operationRequest->attributes->set('sync_operation', true);
        $operationRequest->setUserResolver($request->getUserResolver());
        app()->instance('request', $operationRequest);

        try {
            $response = Route::dispatch($operationRequest);
        } finally {
            app()->instance('request', $request);
        }

        $body = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), is_array($body) ? $body : null];
    }

    /** @param array<string, mixed> $payload */
    private function existingOrder(User $user, array $payload): ?Order
    {
        $reference = $payload['client_reference'] ?? null;

        return is_string($reference) && $reference !== ''
            ? Order::query()->where('client_reference', $reference)->where('created_by', $user->id)->first()
            : null;
    }

    /** @param array<string, mixed> $payload */
    private function occurredAt(array $payload): ?CarbonImmutable
    {
        $value = $payload['location']['captured_at'] ?? null;

        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function result(SyncOperation $operation, bool $duplicate): array
    {
        $body = $operation->response ?? [];

        return [
            'client_reference' => $operation->client_reference,
            'type' => $operation->type,
            'status' => $duplicate ? 'duplicate' : $operation->status,
            'http_status' => $operation->http_status,
            'data' => $body['data'] ?? null,
            'message' => $operation->status === SyncOperation::ACCEPTED ? null : ($body['message'] ?? 'No se pudo aplicar la operación.'),
            'errors' => $body['errors'] ?? null,
        ];
    }
}
