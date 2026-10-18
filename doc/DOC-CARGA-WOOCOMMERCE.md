# DOC-CARGA-WOOCOMMERCE.md

> Carga de los productos sincronizados de la plataforma en WooCommerce (`HSEY_API_IMPORT`).

---

## Reglas

| Caso | Qué hace |
|---|---|
| El producto **no existe** | Lo crea (`WC_Product_Simple`) con **todos** los datos, en el estado configurado |
| El producto **existe** | Actualiza **solo** los campos activos en **Configuracion → Carga WooCommerce** |

Búsqueda del producto existente:
1. Por SKU = `ref` (`wc_get_product_id_by_sku`).
2. Si no, por la meta `_hsey_id` = `id` de la plataforma.

La carga lee `HSEY_PRODUCTS`: primero hay que **Traer productos**.

---

## Configuración (`HSEY_CONFIG['import']`)

| Opción | Valores | Default |
|---|---|---|
| `update_fields.stock` | Cantidad (stock) | **activo** |
| `update_fields.price` | Precio | inactivo |
| `update_fields.name` | Nombre | inactivo |
| `update_fields.description` | Descripción | inactivo |
| `update_fields.attributes` | Atributos | inactivo |
| `update_fields.brand` | Marca | inactivo |
| `update_fields.images` | Imágenes | inactivo |
| `price_source` | `pvp` (si es 0 usa `precio`) / `precio` | `pvp` |
| `status` (solo al crear) | `draft` / `pending` / `publish` | `draft` |

Se lee siempre con `HSEY_USE_DATA_CONFIG::getImport()`, que completa los defaults. Para agregar un campo: sumarlo a `HSEY_API_IMPORT::FIELDS` (`key => [label, default]`) y aplicarlo en `apply()` (o después de `save()` si necesita el ID).

---

## Mapeo plataforma → WooCommerce

| Campo WooCommerce | Origen |
|---|---|
| Nombre | `nombre` |
| SKU (solo al crear) | `ref` |
| Precio regular | `pvp` o `precio` según `price_source` |
| Stock | `stock` (gestión de inventario activada) |
| Descripción | Lista de specs (género, tamaño, correa, caja, resistencia) + `descripcion` |
| Atributos (visibles, no variación) | Género, Tamaño, Correa (`correa_material` + `correa_color`), Caja (`caja_material` + `caja_color`), Resistencia al agua. Vacíos se omiten |
| Marca | `marca` → taxonomía `product_brand` (se crea el término si no existe) |
| Imagen destacada / galería | `imgs[0]` / `imgs[1..]` |
| Meta `_hsey_id` | `id` |

Imágenes:
- Se descargan con `download_url()` (GET) y `media_handle_sideload()`.
- Cada adjunto guarda la meta `_hsey_img_src` con la URL de origen y se reutiliza si ya existe: no se duplican.
- Las URLs que no existen en la plataforma se omiten sin error.

---

## Ejecución por lotes

1. Productos → **Cargar N productos** → confirmación.
2. `src/js/hsey-import.js` llama `wp_ajax_hsey_import_batch` con `offset` y `limit` (5; máx. `BATCH_MAX` = 20).
3. Respuesta: `{ total, next, created, updated, errors[] }`. El JS sigue con `next` hasta `total`, muestra progreso y errores, y se puede **Detener**.
4. Errores por lote → log `import_errors`; al terminar → `import_done`.

Rendimiento medido: crear un producto con 3 imágenes tarda ~7 s (descarga + miniaturas de WordPress); actualizar solo el stock tarda < 0,3 s. Una primera carga completa (~1.100 productos) toma del orden de 2 horas; las siguientes, pocos minutos.

Si la carga se corta, se puede volver a lanzar: los productos ya creados se encuentran por SKU y solo se actualizan.

---

## Solo lectura

La carga no envía nada a la plataforma: solo lee `HSEY_PRODUCTS` y descarga imágenes por GET. Los POST son al `admin-ajax.php` del propio WordPress.
