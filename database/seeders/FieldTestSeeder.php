<?php

namespace Database\Seeders;

use App\Actions\CreateCustomerAction;
use App\Actions\CreateInventoryDocumentAction;
use App\Actions\CreateProductAction;
use App\Actions\PostInventoryDocumentAction;
use App\Actions\RecordCustomerOpeningBalanceAction;
use App\Actions\SaveInventoryDocumentItemAction;
use App\Actions\UpdateCustomerCreditProfileAction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data for field tests of the mobile app: one route with 40 customers visited every
 * day and about 200 products. Run it by hand, never from DatabaseSeeder:
 *
 *   php artisan db:seed --class=FieldTestSeeder
 *
 * Everything it creates is tagged CAMPO (route, customer codes, SKUs) and it skips
 * what already exists, so running it again does not duplicate anything.
 */
class FieldTestSeeder extends Seeder
{
    public const ROUTE_CODE = 'RUTA CAMPO';

    public const CUSTOMERS = 40;

    /** @var array<string, array{string, list<array{string, string, string}>}> category => [brand, [name, size, price]] */
    private const CATALOG = [
        'Bebidas' => ['Refrescos del Valle', [
            ['Refresco cola', '355 ml', '15'], ['Refresco cola', '600 ml', '22'], ['Refresco cola', '1.5 L', '38'], ['Refresco cola', '3 L', '68'],
            ['Refresco naranja', '355 ml', '15'], ['Refresco naranja', '1.5 L', '36'], ['Refresco uva', '600 ml', '22'], ['Refresco limón', '600 ml', '22'],
            ['Agua purificada', '500 ml', '10'], ['Agua purificada', '1 L', '16'], ['Agua purificada', 'galón', '35'], ['Jugo de naranja', '250 ml', '14'],
            ['Jugo de manzana', '250 ml', '14'], ['Jugo de durazno', '1 L', '42'], ['Bebida hidratante', '600 ml', '28'], ['Té frío limón', '500 ml', '24'],
            ['Café en lata', '250 ml', '30'], ['Bebida energética', '473 ml', '45'], ['Horchata', '1 L', '40'], ['Agua mineral', '600 ml', '20'],
            ['Refresco piña', '355 ml', '15'], ['Refresco fresa', '355 ml', '15'], ['Jugo de mango', '1 L', '42'], ['Leche con chocolate', '250 ml', '18'],
            ['Agua de coco', '330 ml', '35'],
        ]],
        'Abarrotes' => ['La Hacienda', [
            ['Arroz blanco', '1 lb', '18'], ['Arroz blanco', '5 lb', '85'], ['Frijol rojo', '1 lb', '22'], ['Frijol negro', '1 lb', '24'],
            ['Azúcar blanca', '1 lb', '14'], ['Azúcar morena', '1 lb', '13'], ['Sal refinada', '1 lb', '8'], ['Harina de trigo', '1 lb', '15'],
            ['Harina de maíz', '2 lb', '32'], ['Aceite vegetal', '750 ml', '65'], ['Aceite vegetal', '1.5 L', '120'], ['Manteca vegetal', '1 lb', '38'],
            ['Pasta espagueti', '200 g', '14'], ['Pasta codito', '200 g', '14'], ['Sopa instantánea', '64 g', '12'], ['Salsa de tomate', '400 g', '30'],
            ['Mayonesa', '400 g', '48'], ['Café molido', '200 g', '55'], ['Café instantáneo', '100 g', '80'], ['Avena en hojuelas', '400 g', '35'],
            ['Sardina en lata', '155 g', '28'], ['Atún en lata', '140 g', '38'], ['Consomé de pollo', '12 cubos', '25'], ['Frijoles molidos', '400 g', '26'],
            ['Maíz dulce', '400 g', '30'],
        ]],
        'Lácteos' => ['Lácteos de Copán', [
            ['Leche entera', '1 L', '32'], ['Leche semidescremada', '1 L', '32'], ['Leche en polvo', '360 g', '120'], ['Leche en polvo', '800 g', '245'],
            ['Queso fresco', '1 lb', '75'], ['Queso seco', '1 lb', '90'], ['Mantequilla crema', '1 lb', '60'], ['Mantequilla barra', '250 g', '45'],
            ['Yogur fresa', '200 g', '18'], ['Yogur natural', '1 L', '60'], ['Crema ácida', '200 g', '25'], ['Quesillo', '1 lb', '85'],
            ['Leche condensada', '397 g', '42'], ['Leche evaporada', '354 ml', '30'], ['Bebida de soya', '1 L', '38'], ['Requesón', '1 lb', '40'],
            ['Yogur durazno', '200 g', '18'], ['Queso crema', '200 g', '42'], ['Leche deslactosada', '1 L', '36'], ['Queso rallado', '100 g', '35'],
            ['Helado vainilla', '1 L', '95'], ['Helado chocolate', '1 L', '95'], ['Paleta de leche', 'unidad', '12'], ['Flan', '100 g', '15'],
            ['Gelatina preparada', '100 g', '12'],
        ]],
        'Snacks' => ['Crujiditos', [
            ['Papas fritas', '45 g', '15'], ['Papas fritas', '150 g', '42'], ['Tortillitas de maíz', '50 g', '12'], ['Chicharrones', '40 g', '12'],
            ['Maní salado', '50 g', '10'], ['Maní con chile', '50 g', '10'], ['Galleta de soda', 'paquete', '8'], ['Galleta de chocolate', 'paquete', '10'],
            ['Galleta de vainilla', 'paquete', '10'], ['Galleta rellena', 'paquete', '12'], ['Barra de cereal', '25 g', '12'], ['Chocolate en barra', '40 g', '20'],
            ['Chicle', 'unidad', '2'], ['Caramelo duro', 'unidad', '1'], ['Paleta de dulce', 'unidad', '3'], ['Bombones', '100 g', '28'],
            ['Platanitos', '50 g', '12'], ['Palomitas', '80 g', '18'], ['Semillas de marañón', '50 g', '30'], ['Pan dulce', 'unidad', '8'],
            ['Pan de molde', 'bolsa', '55'], ['Rosquillas', 'bolsa', '35'], ['Quequitos', 'unidad', '10'], ['Churritos', '45 g', '12'],
            ['Tajaditas de plátano', '70 g', '15'],
        ]],
        'Limpieza' => ['Hogar Limpio', [
            ['Detergente en polvo', '500 g', '45'], ['Detergente en polvo', '1 kg', '85'], ['Jabón de lavar', 'barra', '18'], ['Cloro', '1 L', '25'],
            ['Cloro', 'galón', '75'], ['Desinfectante lavanda', '1 L', '40'], ['Lavaplatos en crema', '500 g', '35'], ['Lavaplatos líquido', '750 ml', '48'],
            ['Suavizante de ropa', '1 L', '55'], ['Esponja de cocina', 'unidad', '12'], ['Escoba', 'unidad', '85'], ['Trapeador', 'unidad', '95'],
            ['Bolsas de basura', '10 unidades', '35'], ['Limpiavidrios', '500 ml', '45'], ['Insecticida', '400 ml', '95'], ['Ambientador', '400 ml', '70'],
            ['Paste de aluminio', 'unidad', '8'], ['Guantes de hule', 'par', '40'], ['Fósforos', 'caja', '3'], ['Velas', 'unidad', '6'],
            ['Papel aluminio', 'rollo', '45'], ['Servilletas', 'paquete', '20'], ['Vasos desechables', '25 unidades', '30'], ['Platos desechables', '25 unidades', '35'],
            ['Pinzas de ropa', '12 unidades', '25'],
        ]],
        'Higiene' => ['Cuidado Diario', [
            ['Papel higiénico', '4 rollos', '40'], ['Papel higiénico', '12 rollos', '115'], ['Jabón de baño', 'barra', '20'], ['Champú', '400 ml', '75'],
            ['Acondicionador', '400 ml', '75'], ['Pasta dental', '100 ml', '38'], ['Cepillo dental', 'unidad', '25'], ['Desodorante', '50 ml', '65'],
            ['Toallas sanitarias', '10 unidades', '45'], ['Pañales etapa 3', '30 unidades', '280'], ['Pañales etapa 4', '30 unidades', '300'], ['Toallitas húmedas', '80 unidades', '65'],
            ['Crema corporal', '400 ml', '90'], ['Rasuradora', 'unidad', '20'], ['Algodón', '50 g', '18'], ['Alcohol', '250 ml', '30'],
            ['Gel antibacterial', '250 ml', '45'], ['Talco', '200 g', '50'], ['Enjuague bucal', '250 ml', '60'], ['Hilo dental', 'unidad', '35'],
            ['Jabón líquido', '250 ml', '50'], ['Gel para cabello', '250 g', '40'], ['Protector solar', '120 ml', '150'], ['Curitas', '20 unidades', '30'],
            ['Pañuelos desechables', 'caja', '28'],
        ]],
        'Granos y básicos' => ['Del Campo', [
            ['Huevos', 'cartón 30', '135'], ['Huevos', 'media docena', '30'], ['Tortillas de maíz', '10 unidades', '15'], ['Café en grano', '1 lb', '70'],
            ['Panela', 'unidad', '12'], ['Maicena', '400 g', '30'], ['Polvo de hornear', '100 g', '18'], ['Vinagre', '500 ml', '20'],
            ['Achiote', '100 g', '12'], ['Comino', '50 g', '10'], ['Pimienta', '50 g', '15'], ['Canela', '50 g', '15'],
            ['Ajo en polvo', '50 g', '15'], ['Salsa inglesa', '150 ml', '35'], ['Salsa picante', '150 ml', '25'], ['Mostaza', '200 g', '28'],
            ['Miel de abeja', '250 ml', '75'], ['Mermelada de fresa', '250 g', '45'], ['Crema de maní', '340 g', '70'], ['Cereal de maíz', '300 g', '65'],
            ['Pan tostado', 'paquete', '35'], ['Pasas', '100 g', '25'], ['Chile jalapeño', '200 g', '28'], ['Encurtido', '400 g', '35'],
            ['Pepinillos', '400 g', '45'],
        ]],
        'Mascotas y otros' => ['Varios', [
            ['Alimento para perro', '2 kg', '160'], ['Alimento para gato', '1 kg', '110'], ['Pilas AA', '2 unidades', '30'], ['Pilas AAA', '2 unidades', '30'],
            ['Foco LED', 'unidad', '45'], ['Encendedor', 'unidad', '10'], ['Carbón', 'bolsa', '40'], ['Cuaderno', 'unidad', '25'],
            ['Lapicero', 'unidad', '6'], ['Lápiz', 'unidad', '4'], ['Recarga telefónica', 'L 50', '50'], ['Cinta adhesiva', 'rollo', '15'],
            ['Pegamento', 'unidad', '12'], ['Hielo', 'bolsa', '20'], ['Bolsas plásticas', '100 unidades', '35'], ['Tijeras', 'unidad', '35'],
            ['Termo', 'unidad', '120'], ['Sombrilla', 'unidad', '150'], ['Gorra', 'unidad', '90'], ['Cargador de teléfono', 'unidad', '150'],
            ['Audífonos', 'unidad', '120'], ['Repelente', '120 ml', '65'], ['Linterna', 'unidad', '90'], ['Candado', 'unidad', '75'],
            ['Cuerda', '10 m', '40'],
        ]],
    ];

    private const BUSINESS_TYPES = ['Pulpería', 'Minisúper', 'Abarrotería', 'Bodega', 'Cafetería', 'Farmacia', 'Comedor', 'Tienda'];

    private const NAMES = [
        'La Bendición', 'El Buen Precio', 'Doña Marta', 'San José', 'La Esquina', 'El Progreso', 'Los Pinos', 'La Fe',
        'El Ahorro', 'Don Chepe', 'La Económica', 'El Milagro', 'Santa Lucía', 'La Cumbre', 'El Rincón', 'Las Flores',
        'Mi Tiendita', 'El Pueblo', 'La Montaña', 'El Carmen', 'Divino Niño', 'Los Ángeles', 'La Central', 'El Cafetal',
        'La Providencia', 'Don Toño', 'La Popular', 'El Mercadito', 'Los Hermanos', 'La Ceiba', 'El Paraíso', 'Santa Rosa',
        'La Granja', 'El Mirador', 'Doña Tere', 'La Colonia', 'El Sauce', 'Las Brisas', 'El Puente', 'La Placita',
    ];

    private const CONTACTS = [
        'María López', 'José Hernández', 'Ana Martínez', 'Carlos Pineda', 'Rosa Mejía', 'Luis Castro', 'Carmen Reyes', 'Jorge Flores',
        'Gloria Paz', 'Pedro Rivera', 'Sandra Cruz', 'Miguel Zelaya', 'Patricia Romero', 'Óscar Aguilar', 'Lidia Ortiz', 'Mario Sánchez',
        'Elena Díaz', 'Francisco Ramos', 'Teresa Guzmán', 'Raúl Pérez',
    ];

    private const PLACES = ['Barrio Mercedes', 'Barrio El Calvario', 'Colonia Osorio', 'Barrio Santa Teresa', 'Colonia Divino Paraíso', 'Barrio El Carmen', 'Colonia San Martín', 'Barrio Miraflores'];

    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $admin = User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un administrador activo para registrar los datos de prueba.');
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->first()
            ?? throw new RuntimeException('Se necesita una bodega activa para cargar las existencias.');

        $products = $this->products();
        $this->stock($products, $warehouse, $admin);
        $customers = $this->customers($this->route(), $admin);
        $this->credit($customers, $admin);

        $this->command?->info(sprintf('Ruta %s: %d clientes todos los días; %d productos CAMPO.', self::ROUTE_CODE, $customers->count(), $products->count()));
    }

    /** @return Collection<int, Product> */
    private function products()
    {
        $unit = MeasurementUnit::query()->where('symbol', 'u')->firstOrFail();
        $created = collect();
        $number = 0;

        foreach (self::CATALOG as $categoryName => [$brandName, $items]) {
            $category = Category::query()->firstOrCreate(['slug' => 'campo-'.Str::slug($categoryName)], ['name' => $categoryName.' (CAMPO)', 'is_active' => true]);
            $brand = Brand::query()->firstOrCreate(['slug' => 'campo-'.Str::slug($brandName)], ['name' => $brandName.' (CAMPO)', 'is_active' => true]);

            foreach ($items as [$name, $size, $price]) {
                $number++;
                $sku = sprintf('CAMPO-%03d', $number);
                $existing = Product::withTrashed()->where('sku', $sku)->first();

                if ($existing !== null) {
                    $created->push($existing);

                    continue;
                }

                $product = app(CreateProductAction::class)->handle([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'base_unit_id' => $unit->id,
                    'sku' => $sku,
                    'name' => "{$name} {$size}",
                    'slug' => Str::slug("{$name} {$size} {$sku}"),
                    'description' => 'Producto de prueba de campo.',
                    'allows_decimal' => false,
                    'tracks_lots' => false,
                    'tracks_expiration' => false,
                    'is_active' => true,
                    'base_presentation_name' => 'Unidad',
                    'base_barcode' => sprintf('7400000%06d', $number),
                    'base_sale_price' => $price,
                    'base_is_sellable' => true,
                    'base_is_purchasable' => true,
                ]);

                // Every third product also sells by the box, and every fifth has volume prices.
                if ($number % 3 === 0) {
                    $factor = $price >= 50 ? 6 : 24;
                    $product->presentations()->create([
                        'name' => "Caja x{$factor}",
                        'conversion_factor' => (string) $factor,
                        'sale_price' => bcmul(bcmul($price, (string) $factor, 4), '0.92', 2),
                        'is_base' => false,
                        'is_sellable' => true,
                        'is_purchasable' => true,
                        'is_active' => true,
                    ]);
                }

                if ($number % 5 === 0) {
                    $product->basePresentation->priceTiers()->createMany([
                        ['min_quantity' => '12', 'max_quantity' => '23', 'unit_price' => bcmul($price, '0.95', 2), 'starts_at' => today()->subDay(), 'is_active' => true],
                        ['min_quantity' => '24', 'max_quantity' => null, 'unit_price' => bcmul($price, '0.90', 2), 'starts_at' => today()->subDay(), 'is_active' => true],
                    ]);
                }

                $created->push($product);
            }
        }

        return $created;
    }

    /**
     * Initial inventory through a posted document, as in the web. Every tenth
     * product is left almost or fully out of stock to test shortages.
     *
     * @param  Collection<int, Product>  $products
     */
    private function stock($products, Warehouse $warehouse, User $admin): void
    {
        $pending = $products->filter(fn (Product $product): bool => ! $product->inventoryMovements()->exists());

        if ($pending->isEmpty()) {
            return;
        }

        $document = app(CreateInventoryDocumentAction::class)->handle([
            'type' => 'opening',
            'warehouse_id' => $warehouse->id,
            'occurred_on' => today()->toDateString(),
            'notes' => 'Inventario inicial de prueba de campo (CAMPO).',
        ], $admin);

        foreach ($pending as $product) {
            $number = (int) substr($product->sku, 6);
            $quantity = match (true) {
                $number % 20 === 0 => 0,
                $number % 10 === 0 => 3,
                default => 50 + ($number * 37) % 450,
            };

            if ($quantity === 0) {
                continue;
            }

            app(SaveInventoryDocumentItemAction::class)->handle($document, [
                'product_presentation_id' => $product->basePresentation->id,
                'quantity' => (string) $quantity,
                'unit_cost' => bcmul((string) $product->basePresentation->sale_price, '0.75', 2),
            ]);
        }

        if ($document->items()->exists()) {
            app(PostInventoryDocumentAction::class)->handle($document, $admin);
        }
    }

    private function route(): SalesRoute
    {
        $salesperson = User::query()->where('email', 'preventista@gmail.com')->first()
            ?? User::query()->where('role', UserRole::Preventista)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un preventista activo para la ruta de prueba.');
        $driver = User::query()->where('email', 'repartidor@gmail.com')->first()
            ?? User::query()->where('role', UserRole::Repartidor)->where('is_active', true)->orderBy('id')->first();

        return SalesRoute::query()->firstOrCreate(['code' => self::ROUTE_CODE], [
            'name' => 'Ruta de prueba de campo',
            'description' => '40 clientes de prueba que se visitan todos los días.',
            'salesperson_id' => $salesperson->id,
            'driver_id' => $driver?->id,
            'is_active' => true,
        ]);
    }

    /** @return Collection<int, Customer> */
    private function customers(SalesRoute $route, User $admin)
    {
        $customers = collect();

        for ($i = 1; $i <= self::CUSTOMERS; $i++) {
            $code = sprintf('CAMPO-%02d', $i);
            $phone = sprintf('9%07d', 4400000 + $i * 731);
            $customer = Customer::withTrashed()->where('code', $code)->first()
                ?? app(CreateCustomerAction::class)->handle([
                    'code' => $code,
                    'business_name' => self::BUSINESS_TYPES[$i % count(self::BUSINESS_TYPES)].' '.self::NAMES[$i - 1],
                    'business_type' => self::BUSINESS_TYPES[$i % count(self::BUSINESS_TYPES)],
                    'contact_name' => self::CONTACTS[$i % count(self::CONTACTS)],
                    'phone' => $phone,
                    'whatsapp' => $i % 4 === 0 ? null : $phone,
                    'address' => self::PLACES[$i % count(self::PLACES)].', '.($i * 2).' calle, Santa Rosa de Copán',
                    'reference' => $i % 3 === 0 ? 'Frente a la iglesia' : null,
                    'latitude' => number_format(14.7650 + ($i % 8) * 0.0021, 7, '.', ''),
                    'longitude' => number_format(-88.7790 - intdiv($i, 8) * 0.0024, 7, '.', ''),
                    'is_active' => true,
                ], $admin);

            // Monday to Saturday, so the route of any test day has all 40 stops.
            foreach (range(1, 6) as $day) {
                RouteStop::query()->firstOrCreate(
                    ['sales_route_id' => $route->id, 'customer_id' => $customer->id, 'visit_day' => $day],
                    ['visit_order' => $i, 'is_active' => true],
                );
            }

            $customers->push($customer);
        }

        return $customers;
    }

    /**
     * Customers 1-10 pay cash; 11-30 have credit (21-25 with a tiny limit to exceed
     * it); 31-35 are suspended or blocked; 36-40 have credit and overdue balances.
     * Several of them carry open balances to test combined payments.
     *
     * @param  Collection<int, Customer>  $customers
     */
    private function credit($customers, User $admin): void
    {
        foreach ($customers->values() as $index => $customer) {
            $i = $index + 1;

            if ($i <= 10 || $customer->creditProfile()->exists()) {
                continue;
            }

            app(UpdateCustomerCreditProfileAction::class)->handle($customer, [
                'status' => match (true) {
                    $i >= 31 && $i <= 33 => 'suspended',
                    $i >= 34 && $i <= 35 => 'blocked',
                    default => 'active',
                },
                'credit_limit' => $i >= 21 && $i <= 25 ? '500.00' : '15000.00',
                'term_days' => 15,
                'grace_days' => 3,
                'reason' => 'Datos de prueba de campo.',
            ], $admin);

            if ($i % 2 === 0 || $i >= 36) {
                $overdue = $i >= 31;
                $balances = $i >= 36 ? ['850.00', '1240.50', '310.00'] : [number_format(200 + $i * 23.5, 2, '.', '')];

                foreach ($balances as $n => $amount) {
                    app(RecordCustomerOpeningBalanceAction::class)->handle($customer, [
                        'amount' => $amount,
                        'issued_at' => today()->subDays($overdue ? 30 + $n * 7 : 3)->toDateString(),
                        'due_date' => today()->addDays($overdue ? -12 + $n : 12)->toDateString(),
                        'notes' => 'Saldo de prueba de campo.',
                    ], $admin);
                }
            }
        }
    }
}
