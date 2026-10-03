# Proximus — fase 1

API y panel web para administrar el catálogo de una operación de preventa, bodega y reparto. Esta primera fase cubre productos, categorías, marcas, unidades de medida, presentaciones, precios por cantidad y costos por proveedor.

No es multiempresa y no emite facturas fiscales.

## Requisitos

- PHP 8.3 o superior con extensiones PDO MySQL, BCMath, Mbstring, Intl y GD
- Composer 2
- MySQL 8 o MariaDB 10.6 o superior
- Node.js 20 o superior y npm

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configura en `.env` la conexión MySQL o MariaDB. Luego ejecuta:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan app:create-admin
```

El último comando solicita de forma segura el nombre, correo y contraseña del primer administrador. No existen credenciales predeterminadas.

Al crear un producto desde el panel web, Proximus intenta quitar el fondo de la fotografía directamente en el navegador. No requiere una API externa ni software adicional en el servidor. Antes de guardar se muestra el resultado y el usuario puede conservar la fotografía original. Las imágenes reemplazadas durante una edición no se procesan.

El procesamiento utiliza ONNX Runtime Web (MIT) y el modelo U²-NetP (Apache 2.0). El modelo incluido proviene de la publicación oficial utilizada por `rembg`: `https://github.com/danielgatis/rembg/releases/download/v0.0.0/u2netp.onnx`.

Para desarrollo:

```bash
composer run dev
```

## Modelo de precios

- Cada producto tiene una presentación base con factor de conversión `1`.
- Caja, paquete, fardo u otra presentación guarda cuántas unidades base contiene.
- Cada presentación tiene su propio precio normal y código de barras opcional.
- Los rangos de precio permiten cambiar el precio según cantidad y vigencia.
- Cada proveedor puede tener un costo y una referencia distintos por presentación.
- La conversión de unidades a cajas es una sugerencia visible; nunca modifica la operación automáticamente.

## API

La API versionada está bajo `/api/v1`. Un usuario activo obtiene un token con:

```http
POST /api/v1/tokens
Accept: application/json
Content-Type: application/json

{
  "email": "usuario@empresa.com",
  "password": "su-contraseña",
  "device_name": "tablet bodega"
}
```

Envía el token como `Authorization: Bearer <token>`. Los recursos del catálogo requieren rol administrador, supervisor o bodeguero.

## Verificación

```bash
php artisan test
vendor/bin/pint --format agent
npm run build
composer audit
npm audit --audit-level=high
```

Las entidades del catálogo utilizan ULID para dejar preparada la futura sincronización con aplicaciones móviles offline.
