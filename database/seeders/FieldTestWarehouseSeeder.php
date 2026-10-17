<?php

namespace Database\Seeders;

use App\Actions\ApplyInventoryDeltaAction;
use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\CreateCompleteOrderAction;
use App\Actions\CreateDeliveryRunAction;
use App\InventoryMovementType;
use App\Models\Customer;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\PaymentTerm;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Work for the warehouse phone, after FieldTestSeeder:
 *
 *   php artisan db:seed --class=FieldTestWarehouseSeeder
 *
 * - A draft run with 3 orders, ready to start preparing (by product or by order).
 * - 3 more confirmed orders without a run, to assemble a new one on the phone.
 * - One product all those orders take falls short in the warehouse (stock lost
 *   after the orders were confirmed), so the phone must warn while picking and
 *   share what there is by stop order.
 * - Products to count, with their barcode, printed at the end.
 *
 * Each run creates a new set; it never touches earlier ones.
 */
class FieldTestWarehouseSeeder extends Seeder
{
    public const RUN_PREFIX = 'CAMPO-BODEGA-';

    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un administrador activo.');
        $driver = User::query()->where('email', 'repartidor@gmail.com')->where('role', UserRole::Repartidor)->first()
            ?? User::query()->where('role', UserRole::Repartidor)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un repartidor activo.');
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->first()
            ?? throw new RuntimeException('Se necesita una bodega activa.');

        // Boxed products with room to sell: the scenario needs a box and its loose units.
        $products = Product::query()
            ->where('sku', 'like', 'CAMPO-%')
            ->where('is_active', true)
            ->whereHas('presentations', fn ($presentations) => $presentations->where('is_base', false)->where('is_active', true)->where('conversion_factor', '>', 1))
            ->whereHas('inventoryStocks', fn ($stocks) => $stocks->where('warehouse_id', $warehouse->id)->whereRaw('quantity_on_hand - quantity_reserved >= 120'))
            ->with(['presentations', 'baseUnit'])
            ->inRandomOrder()
            ->limit(6)
            ->get();
        $customers = Customer::query()->where('code', 'like', 'CAMPO-%')->where('is_active', true)->inRandomOrder()->limit(6)->get();

        if ($products->count() < 6 || $customers->count() < 6) {
            throw new RuntimeException('Primero ejecuta FieldTestSeeder: faltan productos o clientes CAMPO con existencia.');
        }

        $short = $products->first();
        $set = DeliveryRun::query()->where('client_reference', 'like', self::RUN_PREFIX.'%')->count() + 1;
        $vehicle = Vehicle::query()->firstOrCreate(
            ['code' => 'CAMPO-01'],
            ['license_plate' => 'PCA 0001', 'description' => 'Camión de prueba de campo', 'is_active' => true, 'created_by' => $admin->id],
        );

        // Every order takes the short product (2 boxes) plus two others, boxes and loose units.
        $orders = $customers->values()->map(fn (Customer $customer, int $index): Order => app(CreateCompleteOrderAction::class)->handle([
            'customer_id' => $customer->id,
            'route_stop_id' => RouteStop::query()->where('customer_id', $customer->id)->value('id'),
            'salesperson_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today()->toDateString(),
            'requested_delivery_date' => today()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
            'notes' => 'Pedido de prueba de bodega.',
            'confirm' => true,
            'credit_override_reason' => 'Prueba de bodega.',
            'items' => [
                ['product_presentation_id' => $this->box($short)->id, 'quantity' => '2'],
                ['product_presentation_id' => $this->box($products[1 + $index % 5])->id, 'quantity' => (string) ($index % 3 + 1)],
                ['product_presentation_id' => $this->unit($products[1 + ($index + 2) % 5])->id, 'quantity' => (string) ($index + 3)],
            ],
        ], $admin));

        $run = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'scheduled_date' => today()->toDateString(),
            'client_reference' => self::RUN_PREFIX.$set,
            'notes' => 'Jornada de prueba de bodega: el primer producto no alcanza.',
        ], $admin);

        foreach ($orders->take(3) as $index => $order) {
            app(AssignOrderToDeliveryRunAction::class)->handle($run, $order, $admin, $index + 1);
        }

        // Stock lost after confirming: the 3 orders of the run need 6 boxes; leave 4 and a few loose.
        $factor = (string) $this->box($short)->conversion_factor;
        $target = bcadd(bcmul($factor, '4', 6), '3', 6);
        $stock = InventoryStock::query()->where('warehouse_id', $warehouse->id)->where('product_id', $short->id)->firstOrFail();
        $delta = bcsub($target, (string) $stock->quantity_on_hand, 6);

        if (bccomp($delta, '0', 6) !== 0) {
            app(ApplyInventoryDeltaAction::class)->handle($warehouse, $short, bccomp($delta, '0', 6) > 0 ? InventoryMovementType::AdjustmentIn : InventoryMovementType::AdjustmentOut, $delta, '0', $admin, [
                'reason' => 'Prueba de bodega: mercancía dañada después de confirmar los pedidos.',
            ]);
        }

        $this->command?->info("Jornada {$run->run_number} en borrador con 3 pedidos para {$driver->name}.");
        $this->command?->info('3 pedidos confirmados sin jornada: '.$orders->slice(3)->pluck('order_number')->join(', ').'.');
        $this->command?->warn("No alcanza: {$short->name}. La jornada pide 6 cajas x{$this->plain($factor)}; en bodega quedan {$this->plain($target)} {$short->baseUnit?->symbol} (4 cajas y 3 sueltas).");
        $this->command?->info('Para contar (código de la unidad · existencia en sistema):');
        $this->countable($products, $warehouse)->each(fn (string $line) => $this->command?->line("  {$line}"));
    }

    private function box(Product $product)
    {
        return $product->presentations->where('is_base', false)->where('is_active', true)->sortByDesc('conversion_factor')->first();
    }

    private function unit(Product $product)
    {
        return $product->presentations->firstWhere('is_base', true) ?? $this->box($product);
    }

    /** @return Collection<int, string> */
    private function countable(Collection $products, Warehouse $warehouse): Collection
    {
        $stocks = InventoryStock::query()->where('warehouse_id', $warehouse->id)->whereIn('product_id', $products->pluck('id'))->pluck('quantity_on_hand', 'product_id');

        return $products->map(fn (Product $product): string => sprintf(
            '%s · %s · %s %s',
            $product->name,
            $this->unit($product)->barcode ?? 'sin código',
            $this->plain((string) ($stocks[$product->id] ?? '0')),
            $product->baseUnit?->symbol,
        ));
    }

    private function plain(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
