# Proximus

API y panel web para administrar una operación de preventa, bodega y reparto. Actualmente cubre usuarios y roles, catálogo, clientes, rutas, pedidos, bodegas, inventario, preparación, entrega, cobros y liquidación.

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

## Inventario

- Cada existencia pertenece a una bodega y a un producto, expresada en la unidad base.
- La existencia física y la cantidad reservada por pedidos se mantienen separadas. El disponible es `físico - reservado`.
- Confirmar un pedido reserva toda la demanda. Si no alcanza la existencia, el faltante queda visible sin alterar el pedido; cancelar o reabrir libera la reserva.
- Las entradas, inventario inicial, devoluciones y salidas se registran como documentos. Al aplicarlos quedan inmutables y generan movimientos auditables.
- El inventario inicial y los ajustes manuales requieren administrador o supervisor. Un bodeguero puede recibir mercancía, registrar devoluciones y realizar conteos físicos.
- Los conteos son ciegos mientras están en proceso y se rechazan si la existencia cambió después de iniciar el conteo.
- Lotes y fechas de vencimiento son opcionales por producto. Si se activan, pasan a ser obligatorios en cada movimiento de ese producto.
- El sistema inicia con `BOD-001`, pero el modelo, el panel y la API soportan varias bodegas.

## Operación y reparto

- Los clientes pueden ubicarse con el GPS del dispositivo, tocando un mapa de OpenStreetMap o escribiendo coordenadas manualmente.
- Clientes, productos y presentaciones se buscan de forma paginada; el HTML no precarga catálogos completos. Los productos admiten búsqueda por nombre, SKU, código de barras, marca o categoría.
- Los campos de producto aceptan lectores USB/Bluetooth como teclado y, en navegadores compatibles, lectura con cámara.
- El preventista autenticado queda asignado por el servidor; no se confía en un identificador enviado por el navegador.
- Bodega prepara y guarda cada pedido por separado. Solo cuando todos están listos se confirma la carga completa en una transacción atómica.
- El tablero de despacho muestra pedidos confirmados pendientes, alertas operativas y un consolidado por producto y presentación.
- Los cobros admiten efectivo, transferencia bancaria, tarjeta, cheque y otros; los métodos electrónicos exigen referencia.

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

Envía el token como `Authorization: Bearer <token>`. Además del rol, cada endpoint exige una capacidad de token específica, por ejemplo `inventory:view`, `inventory:operate`, `inventory:adjust` o `inventory:configure`.

## Verificación

```bash
php artisan test
vendor/bin/pint --format agent
npm run build
composer audit
npm audit --audit-level=high
```

Las entidades del catálogo utilizan ULID para dejar preparada la futura sincronización con aplicaciones móviles offline.
