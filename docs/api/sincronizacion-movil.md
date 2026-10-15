# Contrato de sincronización — app móvil Proximus

Contrato entre el servidor Proximus y la app Flutter. Cubre el inicio de sesión del teléfono, la descarga de datos para trabajar sin conexión, el envío de lo registrado sin conexión y la numeración de documentos impresos en el teléfono.

**Principios**

- **El servidor es la fuente de verdad.** El teléfono guarda una copia para trabajar sin señal y envía *hechos* (un pedido tomado, una entrega hecha, un cobro recibido). El servidor los aplica con las mismas reglas que la web.
- **Una sola app para todos los roles.** El servidor decide qué ve cada usuario. La app arma su menú con `resources` y con el rol que devuelve el manifiesto, nunca con listas propias.
- **Todo envío es idempotente.** Reenviar la misma operación nunca la duplica.

Todas las rutas van bajo `/api/v1`. Se envía `Accept: application/json` y, salvo en el login, `Authorization: Bearer <token>`. Fechas en ISO 8601. Los montos y cantidades viajan como **texto decimal** (`"125.5000"`): no los conviertas a `double` para guardarlos ni para sumar.

---

## 1. Inicio de sesión y registro del teléfono

`POST /api/v1/tokens`

```json
{
  "email": "repartidor@empresa.hn",
  "password": "••••••",
  "device_name": "Samsung A15",
  "installation_id": "5f0c2e9a-7b1d-4c11-9a51-2f7d3c8e6b10",
  "platform": "android",
  "app_version": "1.0.0"
}
```

`installation_id` es un UUID que la app genera **la primera vez que se instala** y guarda para siempre en almacenamiento seguro. Es lo que identifica al teléfono. Sin este campo, el login funciona pero el teléfono no queda registrado y no puede numerar documentos.

**201**

```json
{
  "token": "12|9xH…",
  "token_type": "Bearer",
  "abilities": ["orders:view", "orders:manage", "…"],
  "expires_at": null,
  "user": { "id": 7, "name": "…", "role": "repartidor", "…": "…" },
  "device": {
    "id": "01J…",
    "code": "M001",
    "series": {
      "invoice":          { "prefix": "FAC-M001-", "last_number": 0 },
      "receipt":          { "prefix": "REC-M001-", "last_number": 0 },
      "customer_payment": { "prefix": "ABO-M001-", "last_number": 0 }
    }
  }
}
```

- Un nuevo login desde la misma instalación **invalida el token anterior** y conserva el mismo `code`.
- Si otro usuario inicia sesión en el mismo teléfono, el teléfono sigue siendo el mismo (mismo código y mismas series).
- **422**: credenciales inválidas o usuario inactivo.
- **423** con `"code": "device_revoked"`: el administrador bloqueó este teléfono. Ver la sección 6.

`POST /api/v1/tokens/refresh` cambia el token actual por uno nuevo (misma respuesta que el login, con vencimiento desde cero y los permisos vigentes del usuario). La app lo llama con señal cuando el token tiene más de 7 días; así una sesión en uso no vence. Si el usuario fue desactivado responde 403.

`DELETE /api/v1/tokens/current` cierra la sesión. Antes de cerrarla, la app debe vaciar la cola de envío (sección 4). Si no puede, debe avisar al usuario de que perderá lo pendiente.

---

## 2. Manifiesto

`GET /api/v1/sync`. Se llama al abrir la app con señal y antes de cada sincronización.

```json
{
  "server_time": "2026-10-13T16:05:00-06:00",
  "timezone": "America/Tegucigalpa",
  "currency": "HNL",
  "user": { "id": 7, "name": "…", "role": "repartidor" },
  "device": { "id": "01J…", "code": "M001", "series": { "…": "igual que en el login" } },
  "company": {
    "commercial_name": "…", "legal_name": "…", "rtn": "…", "phone": "…",
    "whatsapp": "…", "email": "…", "address": "…", "slogan": "…",
    "logo_url": "https://…/storage/…png"
  },
  "resources": {
    "products":        { "mode": "delta", "available": true },
    "stocks":          { "mode": "delta", "available": true },
    "routes":          { "mode": "full",  "available": false },
    "customers":       { "mode": "full",  "available": false },
    "customer_credit": { "mode": "full",  "available": false },
    "receivables":     { "mode": "full",  "available": false },
    "route_activity":  { "mode": "full",  "available": false },
    "recent_orders":   { "mode": "full",  "available": false },
    "delivery_runs":   { "mode": "full",  "available": true }
  },
  "default_warehouse": { "id": "01J…", "code": "BOD-01", "name": "Bodega principal" },
  "operations": ["delivery.outcome", "delivery.payment"],
  "catalogs": {
    "payment_terms":            [{ "value": "cash", "label": "Contado" }],
    "payment_methods":          [{ "value": "bank_transfer", "label": "Transferencia", "requires_reference": false }],
    "settlement_methods":       [{ "value": "credit", "label": "Crédito", "requires_reference": false, "is_credit": true }],
    "delivery_outcome_reasons": [{ "value": "cannot_pay", "label": "…" }],
    "visit_reasons":            [{ "value": "business_closed", "label": "…" }]
  }
}
```

**Cómo usarlo**

- Descarga solo los recursos con `available: true`. Pedir uno no disponible devuelve 403.
- Usa `server_time` para detectar un reloj mal configurado. Si difiere en más de 5 minutos, avisa al usuario: la hora del teléfono queda registrada en cada visita.
- Usa los catálogos para llenar los selectores. No los escribas fijos en la app; las etiquetas pueden cambiar.
- Guarda `company` y descarga `logo_url` para imprimir facturas sin conexión.
- Actualiza las series locales con `device.series` (ver la sección 5).

### Qué ve cada rol (resumen)

| Recurso | Admin / Supervisor | Preventista | Repartidor | Bodeguero |
|---|---|---|---|---|
| products, stocks | ✓ | ✓ | ✓ | ✓ |
| routes, customers, customer_credit | ✓ (todos) | ✓ (solo sus rutas) | — | — |
| receivables | ✓ (todos) | ✓ (solo sus rutas) | — | — |
| route_activity | — | ✓ (sus paradas de hoy) | — | — |
| recent_orders | ✓ (todos) | ✓ (sus clientes) | — | — |
| delivery_runs | ✓ (todas las abiertas) | — | ✓ (solo las suyas) | según permisos |

---

## 3. Descarga de datos

`GET /api/v1/sync/pull/{recurso}?cursor=<cursor>&limit=<1-500>` (por defecto 200)

```json
{
  "resource": "products",
  "mode": "delta",
  "records": [ … ],
  "next_cursor": "MjAyNi0xMC0xMyAxNjowMDowMHwwMUo…",
  "has_more": true,
  "server_time": "…"
}
```

El cursor es opaco: guárdalo tal cual, no lo interpretes.

### Modo `delta` (products, stocks)

1. La primera vez, se pide sin cursor.
2. Se repite con `cursor = next_cursor` mientras `has_more` sea `true`.
3. **Al terminar, guarda el último `next_cursor`.** En la próxima sincronización se empieza desde ahí y solo llegan los cambios.
4. Cada registro **reemplaza completo** al local con el mismo `id`, incluidas las presentaciones y escalas de precio anidadas. Una presentación que ya no viene se borra localmente.
5. Un registro `{ "id": "…", "deleted": true }` significa que se elimine localmente.

Para forzar una descarga completa (reinstalación o datos dañados), borra el cursor guardado.

### Modo `full` (routes, customers, customer_credit, receivables, route_activity, recent_orders, delivery_runs)

1. Se pide sin cursor y se recorren todas las páginas igual que en `delta`.
2. Solo **al terminar todas las páginas**, se reemplaza la tabla local completa por lo recibido. Si la descarga se corta a la mitad, se conserva la tabla anterior.
3. No se guarda cursor entre sincronizaciones.

### Estructura de los registros

**products**
```json
{
  "id": "01J…", "deleted": false, "sku": "BEB-001", "name": "Agua 600 ml",
  "category": "Bebidas", "brand": "…", "base_unit": "und", "allows_decimal": false,
  "is_active": true, "image_url": "https://…",
  "presentations": [{
    "id": "01J…", "name": "Fardo 24", "barcode": "…", "conversion_factor": "24.000000",
    "sale_price": "180.0000", "is_base": false, "is_sellable": true, "is_active": true,
    "price_tiers": [{ "id": "01J…", "min_quantity": "10.000000", "max_quantity": null,
                      "unit_price": "170.0000", "starts_at": null, "ends_at": null, "is_active": true }]
  }],
  "updated_at": "…"
}
```

Precio sugerido sin conexión (la misma regla que el servidor):

1. Toma las escalas activas en las que la cantidad esté entre `min_quantity` y `max_quantity` (sin tope si es `null`) y cuya vigencia incluya hoy.
2. De ellas, usa la de mayor `min_quantity`.
3. Si ninguna aplica, usa `sale_price`.

**stocks**: `id`, `warehouse_id`, `product_id`, `quantity_on_hand`, `quantity_reserved`, `quantity_available`, `updated_at`. Las existencias se expresan en la unidad base del producto y sirven **solo como referencia**: sin conexión se puede vender aunque falte (ver la sección 7).

**routes**: `id`, `code`, `name`, `salesperson_id`, `stops[]` con `id`, `customer_id`, `visit_day`, `visit_order`.

**customers**: `id`, `code`, `business_name`, `business_type`, `contact_name`, `phone`, `whatsapp`, `address`, `reference`, `latitude`, `longitude`.

**customer_credit**: `customer_id`, `status`, `allows_credit`, `credit_limit`, `term_days`, `grace_days`, `outstanding`, `overdue`, `committed`, `available_credit`. Sin conexión, la app resta de `available_credit` el crédito que ya otorgó localmente desde la última descarga.

**route_activity**: las paradas de hoy del preventista con lo que ya se hizo en cada una. Contiene `id` (= `route_stop_id`), `date` (día para el que se preparó), `visit_order`, `route`, `customer`, `status` (`pending`, `order_taken`, `no_sale` o `visited`), las marcas `has_order`, `has_no_sale` y `has_payment`, `orders_count`, `orders_total`, `payments_count`, `payments_total`, y el último `order` y la última `visit`. Pedido y abono son independientes: un cliente puede tener los dos el mismo día. Si `date` no es hoy, la app avisa que la ruta es de otro día.

**recent_orders**: pedidos de los últimos 30 días de los clientes visibles, sin borradores, para la ficha del cliente. Contiene `id`, `customer_id`, `route_stop_id`, `client_reference`, `order_number`, `order_date`, `requested_delivery_date`, `status`, `status_label`, `payment_term`, `total`, `items_count` y `salesperson_name`. La app los cruza con su cola por `client_reference` para no mostrar dos veces un pedido que hizo el teléfono.

`default_warehouse` del manifiesto es la bodega cuyas existencias se muestran al tomar pedidos. `operations` lista solo las operaciones que el usuario puede enviar.

**receivables**: deudas abiertas de los clientes visibles, para aplicar abonos sin conexión. Contiene `id`, `customer_id`, `source`, `document_number`, `order_id`, `original_amount`, `outstanding_amount`, `currency`, `status`, `status_label`, `issued_at`, `due_date`, `is_overdue` y `notes`. Solo está disponible para quien puede registrar abonos. Sin conexión, la app resta de `outstanding_amount` lo que ya abonó localmente.

**delivery_runs**: el mismo formato que `GET /api/v1/delivery-runs/{id}`. Incluye la jornada, el vehículo, las paradas (`run_orders`) en orden de visita, los productos cargados con su precio y los pagos registrados.

---

## 4. Envío de operaciones

### Cola local

Toda acción que cambia datos (pedido, visita, entrega, cobro, abono) se guarda **primero en una cola local**, con o sin señal. La cola se envía en orden: si hay señal, de inmediato; si no, en cuanto vuelva. Así hay un solo camino para todo y la app se comporta igual con o sin conexión.

Cada operación lleva:

- `client_reference`: identificador único generado en el teléfono. Usa `"<code del teléfono>:<uuid>"`. **Nunca cambia** entre reintentos.
- `type`: uno de `operations` del manifiesto.
- `payload`: los datos (ver abajo), con un `location` opcional.

### `POST /api/v1/sync/push`

```json
{
  "operations": [
    { "client_reference": "M001:7c1e…", "type": "delivery.outcome", "payload": { … } },
    { "client_reference": "M001:91aa…", "type": "delivery.payment", "payload": { … } }
  ]
}
```

- Máximo **100 operaciones por envío**, en el orden en que ocurrieron. Si hay más, se envían en varios lotes.
- Las operaciones se aplican una por una, en orden. Que una falle no detiene las demás.
- Este endpoint no tiene límite de frecuencia por operación.

**Respuesta 200**: un resultado por operación, en el mismo orden.

```json
{
  "server_time": "…",
  "results": [
    { "client_reference": "M001:7c1e…", "type": "delivery.outcome", "status": "accepted",
      "http_status": 200, "data": { … }, "message": null, "errors": null },
    { "client_reference": "M001:91aa…", "type": "delivery.payment", "status": "rejected",
      "http_status": 422, "data": null,
      "message": "Esta parada no está pendiente de cobro.",
      "errors": { "delivery_run_order": ["Esta parada no está pendiente de cobro."] } }
  ]
}
```

| `status` | Significado | Qué hace la app |
|---|---|---|
| `accepted` | Se aplicó | Sacar de la cola. Actualizar el registro local con `data` (por ejemplo, el número de pedido real) |
| `duplicate` | Ya se había aplicado antes | Igual que `accepted` |
| `rejected` | El servidor no la acepta (datos inválidos, sin permiso, estado que ya no lo permite) | Sacar de la cola y mostrarla en una bandeja de **"Rechazadas"** con `message`. Reintentarla igual no sirve. El supervisor también la ve en el reporte *Operaciones sincronizadas* |
| `failed` | Error del servidor | Dejar en la cola y reintentar más tarde con espera creciente (30 s, 2 min, 10 min…) |

**Errores del envío completo**

| Código | Qué hace la app |
|---|---|
| Sin red o tiempo agotado | Reintentar el mismo lote; es seguro gracias a `client_reference` |
| 401 | Pedir login de nuevo sin borrar la cola |
| 423 | Ver la sección 6 |
| 422 | El lote está mal formado: hay un error en la app |

**Dependencias.** Si una operación es rechazada, revisa las siguientes de la cola que dependan de ella (por ejemplo, el cobro de una entrega cuyo resultado fue rechazado). Enviarlas igual es seguro: el servidor también las rechazará con su motivo.

### Ubicación (`location`)

Opcional en todas las operaciones. Se captura automáticamente al guardar, sin pedir nada al usuario.

```json
"location": {
  "latitude": 14.0723, "longitude": -87.1921, "accuracy": 12.5,
  "captured_at": "2026-10-13T09:41:12-06:00",
  "status": "captured"
}
```

- `status`: `captured`, `denied` (el usuario negó el permiso) o `unavailable` (sin GPS o sin fijar posición). Con `denied` o `unavailable` se omiten las coordenadas.
- `captured_at` es la hora real del hecho en el teléfono. Se acepta hasta 30 días atrás y 10 minutos adelante.
- Usa la última posición conocida si tiene menos de 2 minutos. No bloquees el guardado esperando GPS.

### Operaciones

#### `customer.create`: cliente nuevo registrado en ruta

```json
{
  "id": "01JA…",
  "business_name": "Pulpería Nueva",
  "address": "Colonia Kennedy, bloque 3",
  "contact_name": null, "phone": null, "whatsapp": null, "reference": null,
  "latitude": "14.0723000", "longitude": "-87.1921000",
  "is_active": true,
  "route_stop": { "id": "01JA…", "sales_route_id": "01J…", "visit_day": 3 }
}
```

- El teléfono genera `id` y `route_stop.id` (ULID). Así, un `order.create` que va después en la cola puede usar ese `customer_id` y ese `route_stop_id` antes de sincronizar.
- `code` es opcional: el servidor asigna `CLI-n`.
- Al preventista se le agrega siempre a una de sus rutas: si tiene una sola, `sales_route_id` puede omitirse. `visit_day` va de 1 (lunes) a 7 (domingo); si falta, se usa el día de hoy.
- Reenviar con el mismo `id` no lo duplica: responde `accepted` con el cliente existente.

#### `order.create`: pedido del preventista

```json
{
  "customer_id": "01J…",
  "route_stop_id": "01J…",
  "order_date": "2026-10-13",
  "requested_delivery_date": "2026-10-14",
  "payment_term": "cash",
  "notes": null,
  "confirm": true,
  "items": [
    { "product_presentation_id": "01J…", "quantity": "12", "unit_price": "170.0000", "notes": null }
  ],
  "location": { … }
}
```

- `order_date` no puede ser futura. `items` admite entre 1 y 200 productos, sin repetir presentación.
- Envía siempre `unit_price` con el precio que vio el cliente. Si difiere del vigente al sincronizar, el servidor lo respeta y lo marca para el supervisor.
- Con `confirm: true` el pedido queda confirmado y listo para despacho; es lo normal en la app.
- `data` devuelve `id`, `order_number` y `status`. Guarda `order_number` para mostrarlo.
- Al aceptar el pedido, el servidor registra la visita "Pedido tomado" con la ubicación.

#### `visit.no_sale`: visita sin pedido

```json
{ "customer_id": "01J…", "reason": "business_closed", "notes": null, "location": { … } }
```

`reason` viene de `catalogs.visit_reasons`.

#### `delivery.outcome`: resultado de la entrega

```json
{
  "delivery_run_id": "01J…",
  "run_order_id": "01J…",
  "receiver_name": "Doña Marta",
  "outcome_reason": "cannot_pay",
  "outcome_notes": null,
  "items": [
    { "id": "<id del producto en la parada>", "delivered_quantity": "22",
      "returned_quantity": "1", "damaged_quantity": "1", "missing_quantity": "0" }
  ],
  "location": { … }
}
```

- Debe incluir todos los productos de la parada. Para cada uno: entregado + devuelto + dañado + faltante = cargado.
- `outcome_reason` solo es obligatorio si hay devolución; si solo hay dañado o faltante, el servidor lo asigna. Viene de `catalogs.delivery_outcome_reasons`.
- Deja la parada "pendiente de cobro" (si se entregó algo) o la completa (si no se entregó nada).

#### `delivery.payment`: cobro y factura de la entrega

```json
{
  "delivery_run_id": "01J…",
  "run_order_id": "01J…",
  "invoice_number": "FAC-M001-000045",
  "allocations": [
    { "method": "cash",          "amount": "300.00", "receipt_number": "REC-M001-000120" },
    { "method": "bank_transfer", "amount": "200.00", "reference": null, "receipt_number": "REC-M001-000121" },
    { "method": "credit",        "amount": "100.00" }
  ],
  "credit_reason": null,
  "location": { … }
}
```

- Las líneas deben sumar **exactamente** el total entregado de la parada. Se admite como máximo una línea de crédito.
- `reference` solo es obligatoria para tarjeta y cheque (`requires_reference` en el catálogo).
- La línea de crédito no lleva `receipt_number`.
- Requiere que el `delivery.outcome` de esa parada se haya aceptado antes; en la cola debe ir después.

#### `customer.payment`: abono a cuenta

```json
{
  "customer_id": "01J…",
  "receipt_number": "ABO-M001-000007",
  "amount": "500.00",
  "payment_lines": [
    { "method": "cash", "amount": "200.00" },
    { "method": "bank_transfer", "amount": "300.00", "reference": null }
  ],
  "allocations": [ { "receivable_id": "01J…", "amount": "500.00" } ],
  "notes": null,
  "location": { … }
}
```

Las líneas de pago y la aplicación a deudas deben sumar exactamente `amount`.

---

## 5. Numeración de documentos en el teléfono

El repartidor imprime la factura y los recibos **en el momento**, con o sin señal. Por eso cada teléfono numera sus propios documentos con una serie que le pertenece solo a él:

| Documento | Serie | Ejemplo |
|---|---|---|
| Factura de entrega | `FAC-<code>-` | `FAC-M001-000045` |
| Recibo de cobro de entrega | `REC-<code>-` | `REC-M001-000120` |
| Recibo de abono a cuenta | `ABO-<code>-` | `ABO-M001-000007` |

Siempre con 6 dígitos y relleno de ceros. Las facturas hechas desde la web usan la serie central (`FAC-2026-000123`), así que nunca chocan.

**Reglas para la app**

1. Guarda localmente el último número usado de cada serie.
2. Al generar un documento: `siguiente = max(último local, last_number del servidor) + 1`. Guarda el nuevo número **en la misma transacción local** que crea la operación de la cola, antes de imprimir. Así, aunque la app se cierre, el número no se repite.
3. En cada manifiesto y en cada login, sube el contador local si `device.series.*.last_number` es mayor. Esto cubre reinstalaciones y restauraciones.
4. Un número usado no se reutiliza, aunque el cobro se cancele antes de enviarse. Un hueco en la serie es aceptable; un número repetido no.

**Qué hace el servidor**

- Respeta el número si llega por `/sync/push` desde el teléfono dueño de esa serie y aún no existe. El número aceptado vuelve en `data`.
- Si el número no es de la serie del teléfono, ya existe, o llega por la API normal (no por `push`), el servidor asigna uno de la serie central. La app debe comparar el número de `data` con el impreso; si difieren, debe guardar el del servidor y mostrar ambos en el historial.

### Factura impresa sin conexión

La app genera la factura localmente con los datos que ya tiene: `company` del manifiesto (y su logo descargado), el cliente, los productos entregados (`delivered_quantity > 0`) con su precio, las líneas de cobro con su método, referencia y número de recibo, y el número de factura. Debe ofrecer los mismos dos formatos que la web, carta y ticket de 80 mm, y las mismas acciones: imprimir carta, imprimir ticket, compartir y terminar.

---

## 6. Teléfono bloqueado

El administrador puede bloquear un teléfono perdido desde **Administración → Teléfonos**. Al bloquearlo:

- Su token se invalida de inmediato: cualquier llamada devuelve **401**.
- El login desde esa instalación devuelve **423** con `"code": "device_revoked"`.

Cuando la app reciba 423, debe **borrar todos los datos locales**: clientes, créditos, cola pendiente y cursores. Después debe mostrar el mensaje del servidor. Lo que hubiera en la cola se pierde; el administrador lo sabe al bloquear.

El administrador puede reactivar el teléfono. Al reactivarlo, conserva su código y sus series.

---

## 7. Reglas cuando la operación llegó sin conexión

Lo que llega por `/sync/push` ya ocurrió frente al cliente. Por eso el servidor lo registra y deja la excepción a la vista del supervisor, en lugar de rechazarlo:

| Situación | Qué pasa |
|---|---|
| El precio cambió entre la toma y la sincronización | Se respeta el precio del teléfono y se marca "Precio con el que se tomó el pedido sin conexión" |
| El crédito excede el disponible, o el cliente ya no tiene crédito activo | Se acepta y queda como autorización excepcional para el supervisor |
| No hay existencias suficientes | Se acepta el pedido y la reserva queda en faltante |

Estas excepciones **solo** aplican a `/sync/push`. Las llamadas directas a la API siguen las reglas estrictas de la web. La app debe advertir al usuario sin conexión (por ejemplo, "excede el crédito disponible"), pero puede dejarle continuar.

Lo que **sí** se rechaza aunque venga sin conexión: datos inválidos, falta de permiso, una parada que ya no está pendiente (por ejemplo, porque el supervisor la reprogramó) y montos que no cuadran.

---

## 8. Ciclo recomendado

**Al abrir la app o recuperar señal**

1. `GET /sync`: rol, recursos disponibles, catálogos y series.
2. Vaciar la cola con `POST /sync/push`. Va **antes** de descargar, para que lo descargado ya incluya lo propio.
3. Descargar `delta`: products y stocks.
4. Descargar `full` según el rol: customers, customer_credit, routes o delivery_runs.

**Frecuencia**

- Cada vez que se agrega algo a la cola y hay señal: solo el paso 2.
- Cada 15 minutos con la app abierta y señal: pasos 1 a 4.
- Repartidor: descargar `delivery_runs` justo antes de salir a ruta. Sin esa descarga no tiene paradas para trabajar.

**Reglas locales**

- Una parada con resultado o cobro en cola no se sobrescribe con lo descargado hasta que su operación se acepte.
- Un pedido creado sin conexión se muestra con su `client_reference` hasta tener `order_number`.

---

## 9. Funciones con conexión

Todo lo que hace la web está disponible en la API. Estas funciones **requieren señal** y no pasan por la cola. Cada endpoint exige el permiso indicado. Si el token no lo tiene, devuelve 403; la app debe ocultar esa sección según `abilities` del login.

Los tokens emitidos antes de esta versión no incluyen `reports:view`, `route-reports:view` ni `company:manage`; basta con volver a iniciar sesión.

| Función | Endpoint | Permiso |
|---|---|---|
| Panel según el rol | `GET /dashboard` | cualquier usuario del panel |
| Notificaciones | `GET /notifications?unread=1&page=`, `POST /notifications/{id}/read`, `POST /notifications/read-all` | cualquier usuario del panel |
| Catálogo de reportes | `GET /reports` | `reports:view` |
| Reporte como datos | `GET /reports/{key}?period=&from=&to=&basis=&search=&sort=&direction=&per_page=&<filtro>=` | `reports:view` |
| Reporte en PDF / CSV | `GET /reports/{key}/download`, `GET /reports/{key}/export` (mismos filtros) | `reports:view` |
| Reportes de ruta | `GET /route-reports?date_from=&date_to=&route_id=&…`, `GET /route-reports/{jornada}/{ruta}/download` | `route-reports:view` |
| Cartera de crédito | `GET /credits?search=&status=&overdue=1&page=` | `credits:view` |
| Crédito del cliente | `GET /customers/{id}/credit`: perfil, resumen, deudas abiertas y últimos 30 movimientos | `credits:view` |
| Comprobante de abono | `GET /customers/{id}/payments/{abono}/receipt`, `…/receipt/download` | `credits:view` |
| Política de crédito | `PUT /customers/{id}/credit-profile` (`status`, `credit_limit`, `term_days`, `grace_days`, `reason`) | `credits:configure` |
| Saldo inicial | `POST /customers/{id}/opening-balances` (`amount`, `issued_at`, `due_date`, `notes`) | `credits:configure` |
| Revertir abono | `POST /customers/{id}/payments/{abono}/reversal` (`reason`) | `payments:reverse` |
| Factura de entrega | `GET /delivery-runs/{j}/orders/{p}/invoice`: número, líneas, enlaces de descarga carta y ticket, enlace público de 7 días y enlace de WhatsApp | `deliveries:view` |
| PDF de la factura | `GET /delivery-runs/{j}/orders/{p}/invoice/download?format=letter\|ticket` | `deliveries:view` |
| Entrada rápida de inventario | `POST /inventory/stocks/{id}/replenishments` (`quantity`) | `inventory:operate` |
| Datos de la empresa | `GET /company`, `POST /company` (multipart si incluye `logo`; `remove_logo` obligatorio) | `company:manage` |
| Teléfonos | `GET /devices`, `POST /devices/{id}/revoke`, `POST /devices/{id}/restore` | `users:manage` |

El resto (catálogo, clientes, rutas, pedidos, jornadas y su preparación, carga, salida y liquidación, inventario, conteos, vehículos y usuarios) ya existía en la API con las mismas rutas que la web, bajo `/api/v1`.

**Formato de un reporte como datos**

- `columns`: lista de columnas con `key`, `label`, `format` (`money`, `quantity`, `integer`, `percent`, `date`, `datetime` o `text`), `sortable` y `total`.
- `rows`: objetos indexados por `key` de columna. `meta` indica la paginación.
- `totals`: totales de las columnas que los tienen.
- `summary`: indicadores con `label`, `value`, `format` y, opcionalmente, `previous` (periodo anterior) y `hint`.
- `charts`: `title`, `type`, `labels` y `series[]` con `name`, `color` y `values`.
- `filters.options`: los valores posibles de cada filtro del reporte, para llenar los selectores.
