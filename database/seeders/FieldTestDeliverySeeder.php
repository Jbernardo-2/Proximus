<?php

namespace Database\Seeders;

use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\ConfirmDeliveryLoadAction;
use App\Actions\CreateCompleteOrderAction;
use App\Actions\CreateDeliveryRunAction;
use App\Actions\SaveDeliveryPreparationAction;
use App\Actions\StartDeliveryPreparationAction;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\DeliveryRun;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\PaymentTerm;
use App\UserRole;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A run already prepared and loaded by the warehouse, for field tests of the
 * driver's app. Run it by hand each time a new run is needed (a run is used up
 * once delivered), after FieldTestSeeder:
 *
 *   php artisan db:seed --class=FieldTestDeliverySeeder
 *
 * It goes through the same steps as the web: confirmed orders of CAMPO customers,
 * a run for the driver, preparation (one line short, as happens in the warehouse)
 * and load. While a loaded CAMPO run of the driver has not left, it creates no other.
 */
class FieldTestDeliverySeeder extends Seeder
{
    public const STOPS = 10;

    public const RUN_PREFIX = 'CAMPO-JORNADA-';

    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un administrador activo.');
        $driver = User::query()->where('email', 'repartidor@gmail.com')->where('role', UserRole::Repartidor)->first()
            ?? User::query()->where('role', UserRole::Repartidor)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un repartidor activo.');
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->first()
            ?? throw new RuntimeException('Se necesita una bodega activa.');

        $waiting = DeliveryRun::query()
            ->where('driver_id', $driver->id)
            ->where('client_reference', 'like', self::RUN_PREFIX.'%')
            ->where('status', DeliveryRunStatus::Loaded)
            ->first();

        if ($waiting !== null) {
            $this->command?->info("La jornada {$waiting->run_number} de {$driver->name} sigue cargada sin salir; no se creó otra.");

            return;
        }

        $customers = Customer::query()->where('code', 'like', 'CAMPO-%')->orderBy('code')->get();

        if ($customers->count() < self::STOPS) {
            throw new RuntimeException('Primero ejecuta FieldTestSeeder para crear los clientes y productos CAMPO.');
        }

        $products = Product::query()
            ->where('sku', 'like', 'CAMPO-%')
            ->whereHas('inventoryStocks', fn ($stocks) => $stocks->where('warehouse_id', $warehouse->id)->whereRaw('quantity_on_hand - quantity_reserved >= 30'))
            ->with('presentations')
            ->orderBy('sku')
            ->get();

        $vehicle = Vehicle::query()->firstOrCreate(
            ['code' => 'CAMPO-01'],
            ['license_plate' => 'PCA 0001', 'description' => 'Camión de prueba de campo', 'is_active' => true, 'created_by' => $admin->id],
        );

        $count = DeliveryRun::query()->where('client_reference', 'like', self::RUN_PREFIX.'%')->count() + 1;
        $run = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'scheduled_date' => today()->toDateString(),
            'client_reference' => self::RUN_PREFIX.$count,
            'notes' => 'Jornada de prueba de campo.',
        ], $admin);

        // The route has no stops on Sunday, so the salesperson is given rather than taken from today's stop.
        $salesperson = SalesRoute::query()->where('code', FieldTestSeeder::ROUTE_CODE)->value('salesperson_id');

        // Stops 1-10: customers CAMPO-01..05 pay cash, 11..15 buy on credit (21 has a small limit).
        $picked = $customers->whereIn('code', ['CAMPO-01', 'CAMPO-02', 'CAMPO-03', 'CAMPO-04', 'CAMPO-05', 'CAMPO-11', 'CAMPO-12', 'CAMPO-13', 'CAMPO-14', 'CAMPO-21'])->values();

        foreach ($picked as $index => $customer) {
            $credit = (int) substr($customer->code, 6) > 10;
            $items = $products->slice(($index * 3 + $count * 7) % max(1, $products->count() - 3), 3)
                ->map(fn (Product $product, int $position): array => [
                    'product_presentation_id' => ($product->presentations->firstWhere('is_base', false) ?? $product->presentations->firstWhere('is_base', true))->id,
                    'quantity' => (string) (($index + $position) % 4 + 1),
                ])
                ->values()
                ->all();

            $order = app(CreateCompleteOrderAction::class)->handle([
                'customer_id' => $customer->id,
                'route_stop_id' => RouteStop::query()->where('customer_id', $customer->id)->where('visit_day', today()->dayOfWeekIso)->value('id'),
                'salesperson_id' => $salesperson,
                'warehouse_id' => $warehouse->id,
                'order_date' => today()->toDateString(),
                'requested_delivery_date' => today()->toDateString(),
                'payment_term' => $credit ? PaymentTerm::Credit->value : PaymentTerm::Cash->value,
                'notes' => 'Pedido de prueba de campo.',
                'confirm' => true,
                'credit_override_reason' => 'Prueba de campo.',
                'items' => $items,
            ], $admin);

            app(AssignOrderToDeliveryRunAction::class)->handle($run, $order, $admin, $index + 1);
        }

        $run = app(StartDeliveryPreparationAction::class)->handle($run, $admin);
        $lines = $run->runOrders()->with('items')->orderBy('visit_order')->get()->flatMap->items->values();
        app(SaveDeliveryPreparationAction::class)->handle($run, $lines->map(fn ($item, int $position): array => [
            'id' => $item->id,
            // The warehouse came up one unit short on the first line of the last stop.
            'prepared_quantity' => $position === $lines->count() - 3 && (float) $item->requested_quantity > 1
                ? (string) ((float) $item->requested_quantity - 1)
                : (string) $item->requested_quantity,
        ])->all(), $admin);
        $run = app(ConfirmDeliveryLoadAction::class)->handle($run, $admin);

        $this->command?->info("Jornada {$run->run_number} cargada para {$driver->name}: ".self::STOPS.' paradas.');
    }
}
