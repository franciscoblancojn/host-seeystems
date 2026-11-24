# 📋 Registro de cambios

Todos los cambios importantes del plugin Host Seeystems. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [Sin publicar]

### ✨ Añadido
- 🎉 Primera versión: conexión con un catálogo B2B externo mediante usuario y clave (login por formulario con `csrf_token` y sesión por cookie, con re-login automático).
- 📦 Pestaña **Productos**: carga del catálogo completo, buscador, filtro por estado e importación individual, de los seleccionados o de todos, por lotes y con progreso.
- 🧾 Lector del catálogo: datos JSON de cada tarjeta (`openProductModal`) y paginación «Siguiente». Referencia → SKU; marca → categoría (opcionalmente bajo una categoría padre) y taxonomía `product_brand`; género, tamaño, correa, caja y resistencia al agua → atributos visibles y descripción corta.
- 💲 Precio normal = PVP o precio del cliente × multiplicador, sin precio rebajado; el precio del cliente se guarda en `_hsy_cost`.
- 📦 Inventario = stock − reservado; la etiqueta «SIN STOCK» deja el producto agotado.
- 🔁 Reimportar actualiza el mismo producto (id de origen o SKU) según los campos elegidos; las imágenes ya descargadas se reutilizan.
- ⚙️ Pestañas **Conexión** (con «Probar conexión») e **Importación** (estado, imágenes, categorías, multiplicador de precio).
