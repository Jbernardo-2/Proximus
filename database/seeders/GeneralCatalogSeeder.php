<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Categories and brands common to any grocery distributor in Honduras, so a new client
 * does not start from an empty catalog. Safe to run again: whatever already exists, even
 * edited or deleted by the client, is left as it is.
 *
 *   php artisan db:seed --class=GeneralCatalogSeeder --force
 */
class GeneralCatalogSeeder extends Seeder
{
    public const CATEGORIES = [
        'Granos básicos' => 'Arroz, frijoles, maíz, lentejas, semillas',
        'Azúcar y endulzantes' => 'Azúcar, miel, panela, edulcorantes',
        'Harinas y repostería' => 'Harinas, levaduras, mezclas, gelatinas e ingredientes',
        'Pastas y alimentos preparados' => 'Pastas, fideos, purés, tamales y preparados',
        'Aceites y grasas' => 'Aceites, mantecas y margarinas',
        'Salsas y condimentos' => 'Salsas, vinagres, mayonesa, sal, especias y sazonadores',
        'Sopas y alimentos instantáneos' => 'Sopas, consomés, cremas, ramen y similares',
        'Enlatados y conservas' => 'Vegetales, carnes, pescados, frutas y conservas',
        'Desayunos y productos para untar' => 'Cereales, avenas, granolas, jaleas y cremas',
        'Café, té y bebidas en polvo' => 'Café, té, cocoa y refrescos en polvo',
        'Leche, lácteos y huevos' => 'Leche, queso, crema, yogur, mantequilla y huevos',
        'Panadería y tortillas' => 'Pan, tortillas y repostería empacada',
        'Galletas' => 'Dulces, saladas, integrales y crackers',
        'Snacks y boquitas' => 'Churros, papas, plátanos, palomitas, maní y semillas',
        'Dulces y golosinas' => 'Chocolates, caramelos, chicles y confites',
        'Bebidas gaseosas' => 'Gaseosas y refrescos carbonatados',
        'Jugos y otras bebidas' => 'Jugos, néctares, maltas, tés y bebidas preparadas',
        'Agua, energizantes e hidratantes' => 'Agua, bebidas energéticas y deportivas',
        'Carnes, embutidos y congelados' => 'Jamón, salchichas, pollo, carnes frías y congelados',
        'Limpieza del hogar' => 'Cloro, lavaplatos, desinfectantes, insecticidas, ambientadores y utensilios',
        'Cuidado de la ropa' => 'Detergentes, jabones, suavizantes y quitamanchas',
        'Papel, desechables y bolsas' => 'Papel higiénico, servilletas, vasos, platos y bolsas',
        'Higiene y cuidado personal' => 'Jabón, champú, pasta dental, desodorante, afeitado y cuidado femenino',
        'Cuidado del bebé' => 'Pañales, toallitas, alimentos y artículos infantiles',
        'Productos para mascotas' => 'Alimentos, higiene y accesorios básicos',
        'Artículos básicos para el hogar' => 'Fósforos, encendedores, velas, baterías, aluminio y plástico adherente',
    ];

    // Straight apostrophes, so "Lay's" typed on a phone keyboard finds the brand.
    public const BRANDS = [
        'Mr. Dieck', 'Tío Rico', 'Progreso', 'Suli', 'Sabemás', 'Selecto', 'Manhattan', 'Vigo',
        'Maseca', 'Doña Blanca', 'Gold Medal', 'Pillsbury', 'Betty Crocker', 'Mazola', 'Clover Brand',
        'Olmeca', 'Ideal', 'Capullo', 'Roma', 'Ina', 'Barilla', 'Prince', 'Maggi', 'Knorr', "Natura's",
        'Don Julio', 'McCormick', 'Badia', 'B&B', 'La Costeña', 'Del Monte', 'Goya', 'Herdez', 'Roland',
        'Heinz', "Hellmann's", 'Ducal', "Campbell's", 'Tabasco', 'Cholula', "Marie Sharp's",
        'Quaker', "Kellogg's", 'Nestlé', 'Jif', 'Skippy', 'Nutella', "Smucker's", 'Sula', 'Leyde',
        'Dos Pinos', 'Lactolac', 'Lala', 'La Lechera', 'Nido', 'Anchor', 'Kraft', 'Philadelphia',
        'Delicia', 'Norteño', 'Don Cristóbal', 'Europea', 'Toledo', 'Cinta Azul',
        'Café El Indio', 'Café Maya', 'Café Oro', 'Nescafé', 'Café Rey', 'Café Copán', 'Café Welchez',
        'Supremo', 'Lipton', 'Bimbo', 'Marinela', 'Pozuelo', 'Gamesa', 'Oreo', 'Nabisco', 'Ritz',
        'Del Carmen', 'Tosh', 'Gullón',
        'Yummies', 'Diana', 'Doritos', 'Cheetos', "Lay's", 'Ruffles', 'Barcel', 'Takis', 'Pringles',
        'Planters', "Hershey's", 'KitKat', 'Snickers', "M&M's", 'Ferrero Rocher', 'Kinder', 'Trident',
        'Halls', 'Chiclets', 'Colombina', 'Arcor', 'Mentos', 'Skittles',
        'Coca-Cola', 'Pepsi', 'Tropical', 'Link', '7UP', 'Sprite', 'Fanta', 'Mirinda', 'Fresca',
        'Canada Dry', 'Big Cola', 'Kerns', 'Del Valle', 'Tampico', 'Jumex', 'Fristy', 'Zuko', 'Tang',
        'Kool-Aid', 'Gatorade', 'Powerade', 'Electrolit', 'Red Bull', 'Monster', 'Aguazul', 'Dasani',
        'Aquafina',
        'Xedex', 'Ariel', 'Fab', 'Tide', 'Persil', 'Ultraklin', 'Swift', 'Más Color', 'Rendidor', 'Ace',
        'Rinso', 'Downy', 'Suavitel', 'Axion', 'Limpiox', 'Salvo', 'Dawn', 'Clorox', 'Magia Blanca',
        'Fabuloso', 'Lysol', 'Pine-Sol', 'Mistolin', 'Sapolio', 'Mr. Músculo', 'Glade', 'Air Wick', 'Raid',
        'Baygon', 'Scotch-Brite', 'Vileda',
        'Scott', 'Familia', 'Kleenex', 'Rosal', 'Nevax', 'Great Value', 'Essential Everyday', 'Ahorro Max',
        'Diamond', 'Reynolds', 'Ziploc',
        'Colgate', 'Oral-B', 'Listerine', 'Sensodyne', 'Crest', 'Palmolive', 'Protex', 'Dove', 'Lux',
        'Nivea', 'Rexona', 'Axe', 'Speed Stick', 'Gillette', 'Pantene', 'Head & Shoulders', 'Sedal',
        'Savilé', 'Herbal Essences', "Johnson's", 'Kotex', 'Always', 'Stayfree', 'Tena',
        'Huggies', 'Pampers', 'Babysec', 'Gerber', 'Enfamil', 'Similac',
        'Pedigree', 'Dog Chow', 'Cat Chow', 'Whiskas', 'Purina', 'Ganador', 'Ringo', 'Alpo', 'Friskies',
        'Duracell', 'Energizer', 'Eveready', 'Bic',
    ];

    public function run(): void
    {
        $categories = 0;
        foreach (self::CATEGORIES as $name => $description) {
            $categories += (int) Category::withTrashed()
                ->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => $description, 'is_active' => true])
                ->wasRecentlyCreated;
        }

        $brands = 0;
        foreach (self::BRANDS as $name) {
            $brands += (int) Brand::withTrashed()
                ->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true])
                ->wasRecentlyCreated;
        }

        $this->command?->info("Categorías nuevas: {$categories} de ".count(self::CATEGORIES).". Marcas nuevas: {$brands} de ".count(self::BRANDS).'.');
    }
}
