# 📋 Registro de cambios

Todos los cambios importantes del plugin Host Seeystems. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [Sin publicar]

### ✨ Añadido
- ✏️ Variable `{genero}` en la plantilla del nombre.

### 🔄 Cambiado
- ✏️ Plantilla del nombre por defecto: `{nombre} {genero} {marca} {ref}` (p. ej. «LEILA SET DAMA Armani Exchange AX7186SET»). Si ya hay una plantilla guardada, se conserva.

## [1.0.1] - 2026-11-23

### ✨ Añadido
- 🔖 Meta `_hsy_imported = yes` en todos los productos importados.
- 📦 **Stock mínimo** en Importación (3 por defecto): al importar, con el mínimo o menos el producto queda con stock 0; después, si el stock de un producto importado baja hasta el mínimo o por debajo (venta, edición, API), se pone en 0 automáticamente y se anota en el log.
- 💰 **Precio mayorista** en la meta `_hsy_wholesale_price`: se actualiza en cada importación (aunque «actualizar existentes» esté apagado) y se muestra en el editor del producto junto a los precios, con el margen sobre el precio normal.
- 🔎 **Descripción desde Google** (vía Serper.dev) para productos sin descripción: solo acepta páginas o resultados con la referencia exacta y nunca reemplaza una descripción existente.
- 🏷️ Característica «Referencia» en los atributos.

### 🔄 Cambiado
- 🏷️ La marca va solo a la taxonomía de marcas (`product_brand`) y ya no se crea como categoría. Si no existe se crea en formato normal («ARMANI EXCHANGE» → «Armani Exchange»); si existe se reutiliza sin distinguir mayúsculas.
- 💲 El precio normal es siempre el **PVP sugerido**; se quitan las opciones «precio de venta» y «multiplicador». El precio rebajado no se toca.
- 🧾 Características con el mismo formato que la ficha del catálogo (Caja «Acero, Dorado») y género tal cual (DAMA); la etiqueta de disponibilidad (DISPONIBLE, EN TRÁNSITO, SIN STOCK) ya no se trata como descripción.
- ⚙️ Pestaña Importación reorganizada: Productos, Stock mínimo y Descripción desde Google, con la explicación de cada opción. «Categoría padre» pasa a ser una categoría opcional para los productos nuevos.

### 🗑️ Eliminado
- Meta `_hsy_cost` (reemplazada por `_hsy_wholesale_price`).

## [1.0.0] - 2026-11-23

### ✨ Añadido
- 🎉 Primera versión: conexión con un catálogo B2B externo mediante usuario y clave (login por formulario con `csrf_token` y sesión por cookie, con re-login automático).
- 📦 Pestaña **Productos**: carga del catálogo completo, buscador, filtro por estado e importación individual, de los seleccionados o de todos, por lotes y con progreso.
- 🧾 Lector del catálogo: datos JSON de cada tarjeta (`openProductModal`) y paginación «Siguiente». Referencia → SKU; marca → categoría (opcionalmente bajo una categoría padre) y taxonomía `product_brand`; género, tamaño, correa, caja y resistencia al agua → atributos visibles y descripción corta.
- 💲 Precio normal = PVP o precio del cliente × multiplicador, sin precio rebajado; el precio del cliente se guarda en `_hsy_cost`.
- 📦 Inventario = stock − reservado; la etiqueta «SIN STOCK» deja el producto agotado.
- 🔁 Reimportar actualiza el mismo producto (id de origen o SKU) según los campos elegidos; las imágenes ya descargadas se reutilizan.
- ⚙️ Pestañas **Conexión** (con «Probar conexión») e **Importación** (estado, imágenes, categorías, multiplicador de precio).
