# 📋 Registro de cambios

Todos los cambios importantes del plugin Host Seeystems. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [1.9.0] - 2026-11-28

### ✨ Añadido
- 💲 **Nuevo cálculo de precio** (Importación → «Precio y descuentos»): precio = el mayor entre **precio mayorista × 2** y el **PVP sugerido**. Si el precio con descuentos (descuento general, 27 % por defecto, + el de su categoría) queda en o por debajo de mayorista + **utilidad mínima** (20 % por defecto), el precio sube lo justo (redondeado hacia arriba a 100). El precio rebajado queda vacío.
- 🏷️ **Categorías de descuento por margen** (margen aprox. sobre PVP = 1 − mayorista ÷ PVP): 50–59 % → Ofertas 5 %, 60–69 % → Ofertas 10 %, 70 % o más → Ofertas 15 %, 49 % o menos → ninguna. Configurables (margen desde, categoría y %). El plugin solo asigna la categoría (y quita la de otros tramos); el descuento lo aplica otro plugin. Es independiente de las categorías de género / estilo.
- 📝 Metas del producto: precio mayorista, PVP sugerido, **margen aprox.** (`_hsy_margin`), **precio mayorista × 2** (`_hsy_wholesale_x2`) y el detalle del cálculo; en el editor se ven debajo del precio, con la categoría de descuento, el descuento total y el precio con descuentos frente al mínimo.
- 🔄 Botón **«Actualizar precios y categorías de descuento (N)»** en la pestaña Productos: recalcula solo precio, metas y categoría de descuento de los productos importados del filtro actual (con los precios más recientes del catálogo). No usa IA ni toca descripciones.
- 📋 Columna «Precio tienda» en la tabla.

### 🔄 Cambiado
- El precio de los productos ya no es el PVP sugerido sin más: se aplica la regla de arriba al importar (campo «Precio y categoría de descuento»).
- Las categorías de ofertas (y la categoría por defecto) no cuentan como «tiene categorías»: un producto con solo «Ofertas 10 %» sigue recibiendo sus categorías de género al completar lo faltante.

## [1.8.0] - 2026-11-27

### ✨ Añadido
- 🏷️ **Filtro por marca** en la tabla de productos (con la cantidad de cada una), combinable con el buscador y el filtro de estado.
- 🚀 Botón **«Publicar filtrados con contenido (N)»** junto a los filtros: publica todos los productos del filtro actual (todas las páginas) que están en borrador y tienen contenido completo; por ejemplo, marca «Guess» → publicar sus borradores listos. Con confirmación, por lotes, y cada producto se vuelve a comprobar antes de publicarse.
- 🔎 Filtros «Listos para publicar (borrador con contenido)» y «Publicados».
- La columna Estado muestra si el producto importado está en **Borrador** o **Publicado**.

## [1.7.1] - 2026-11-27

### ✨ Añadido
- 🎯 **Estilo por CSV**: el CSV exportado trae `estilo_opciones` (las subcategorías posibles del producto) y una columna `estilo` para rellenar («Análogo, Elegante»). Al importar se asignan esas subcategorías y se quita el badge «Pendiente de asignar por IA». «estilo» aparece en `falta` cuando está pendiente. Acepta mayúsculas, sin tildes y separadores `,` `;` `/` `|`; un estilo que no esté entre las opciones del producto da error con el motivo.

### 🐛 Corregido
- La columna Categorías de la tabla ocultaba la categoría por defecto de WooCommerce. Si esa categoría es una real (p. ej. «Relojes»), se veía solo «Mujer». Ahora se muestran todas; «Sin categoría» y el filtro «Con contenido faltante» siguen usando la misma regla (alguna categoría además de la de por defecto).

## [1.7.0] - 2026-11-27

### ✨ Añadido
- 📤 **Contenido por CSV** (pestaña Productos → «Contenido por CSV»), para redactar los textos fuera del sitio cuando la IA gratuita no alcanza:
  - **Exportar CSV**: una fila por producto importado (por defecto solo los que tienen contenido faltante) con sus datos (`contexto`, `tipo_producto`, `marca`, `genero`, `categorias`, `falta`…) y columnas vacías para rellenar: `intro`, `beneficios` (uno por línea o separados por `||`), `caracteristicas` y `descripcion_corta`. UTF-8 con BOM (Excel).
  - **Importar CSV**: vista previa sin escribir nada (cuántos se actualizan, se saltan o tienen error, con el motivo por línea) y «Aplicar» por lotes, con la tabla actualizándose en vivo. La descripción se arma con la misma estructura fija que la IA (beneficios, características, tabla de especificaciones y cierre). Modo «solo rellenar lo que falta» (por defecto) o «reemplazar». Acepta también una columna `descripcion_html` ya armada y separadores `,` `;` o tabulador.

## [1.6.2] - 2026-11-26

### 🔄 Cambiado
- 🛑 **Parada automática cuando no queda ninguna IA**: si en un producto ninguna conexión responde (todas en pausa por límite de uso, sin cuota o mal configuradas), la cola se detiene ahí mismo en vez de seguir fallando con el resto. Mensaje: «Detenido: ninguna IA disponible (…). Quedan N sin procesar». Al importar, la importación sigue y solo se detiene la cola de IA (lo que queda sigue «Pendiente de generar con IA»).
- 📊 Resultado en tres grupos: completos, **parciales** (se asignaron las categorías pero falló la IA) y con error. Antes los parciales contaban como error.

## [1.6.1] - 2026-11-26

### ✨ Añadido
- 🔎 Filtro **«Con contenido faltante»** en la tabla: productos importados a los que les falta la descripción, la descripción corta o las categorías.

### 🔄 Cambiado
- 🔢 «Generar descripciones y categorías faltantes» va **de menos a más faltantes**: primero los productos a los que les falta 1 cosa (p. ej. solo la descripción corta), después los de 2 y al final los vacíos (3); los que solo esperan el estilo van al principio. A igualdad, por ID.
- 📊 Los contadores «Con descripción / Sin descripción» pasan a **«Con contenido» / «Sin contenido»**. Contenido = descripción + descripción corta + categorías (el estilo no cuenta).
- 🚀 «Publicar productos con descripción» pasa a **«Publicar productos con contenido (N)»**: solo publica borradores importados con las tres cosas; un producto sin categorías ya no se publica.

## [1.6.0] - 2026-11-26

### ✨ Añadido
- ☑️ Casilla **«Generar contenido al importar»** en la pestaña Productos, activada por defecto. Desactivada, la importación solo importa (sin llamar a la IA) y los productos quedan con «Pendiente de generar con IA» para completarlos después con «Generar descripciones y categorías faltantes». La elección se recuerda en el navegador. Sin IA configurada, sale desactivada.

### 🗑️ Eliminado
- Ajuste «Después de importar» de la pestaña Importación (`content_after_import`): lo reemplaza la casilla de la pestaña Productos.

## [1.5.0] - 2026-11-26

### 🔄 Cambiado
- 🧩 «Generar descripciones pendientes» pasa a **«Generar descripciones y categorías faltantes (N)»**: completa solo lo que le falta a cada producto importado. Si no tiene categorías, se las asigna por género / joyas (sin IA) y después la IA elige el estilo; si falta la descripción, la corta o el estilo, genera solo eso. Lo que ya tiene no se toca.
- El contador (N) incluye también los productos importados sin categoría. Sin IA activa, el botón igual asigna las categorías.

## [1.4.6] - 2026-11-26

### 🔄 Cambiado
- ⚡ **Importación e IA en paralelo**: al importar, cada lote de 5 que termina pasa enseguida a la cola de la IA (de a 1), mientras se importan los 5 siguientes; lo que llega mientras la IA trabaja se suma a la cola. Antes las descripciones empezaban cuando terminaba toda la importación. Nunca hay más de 2 peticiones a la vez.
- 📊 Dos barras de progreso al importar («Importación N/M» e «IA N/M») y un mensaje con el avance y el resumen final de las dos colas.
- 🔄 **La tabla se actualiza en vivo**: cada producto importado muestra al momento sus badges («Pendiente de generar con IA», categorías) y, cada vez que la IA termina uno (al importar, «Generar pendientes» o «Regenerar»), su fila se repinta con la descripción y la corta, con un destello verde. Sin recargar la tabla: se mantienen la página y la selección.

### 🐛 Corregido
- Sugerencia de categorías por género: si la tienda no tiene «Relojes», ya no elige una «Mujer» / «Hombre» de otra rama (p. ej. la de «Joyas»); solo de primer nivel.

## [1.4.5] - 2026-11-26

### ✨ Añadido
- 📄 **Tabla paginada de 100 en 100**, con paginador arriba y abajo («Mostrando 101–200 de 914», primera / anterior / página N / siguiente / última). Buscar o filtrar vuelve a la página 1; después de importar o generar se queda en la misma página.
- ☑️ La selección se conserva al cambiar de página («Quitar selección» para vaciarla); «seleccionar todo» marca solo la página actual.

### 🔄 Cambiado
- «Importar todos los visibles» pasa a **«Importar esta página (N)»**: importa los productos de la página actual, para ir revisando de 100 en 100.

## [1.4.4] - 2026-11-26

### ✨ Añadido
- 🧱 **Estructura fija de la descripción** (todas iguales): la IA escribe solo los textos (JSON con `intro`, `beneficios` y `caracteristicas`) y el plugin arma párrafo inicial, «Beneficios principales», «Características del producto», «Especificaciones técnicas» y cierre.
- 📋 **Tabla de especificaciones** generada por el plugin con los datos del producto: una sola tabla, con estilos en línea (bordes, fondo en la columna de etiquetas; no depende del CSS del tema) y filas y emojis fijos. Relojes: Marca, Modelo, Referencia, Género, Correa, Caja, Tamaño, Resistencia al agua. Joyas: Marca, Tipo, Referencia, Género, Material, Color, Tamaño.
- ✍️ **Cierre de la descripción** configurable (por defecto «Compra con confianza en {tienda}: producto original, compra segura y envío a toda Colombia.»).
- 🔁 Botón **«Restablecer prompts por defecto»** (Importación → Descripciones): vuelve al momento a los prompts de descripción, descripción corta y estilo, y al cierre, que trae el plugin (se guardan vacíos, así siguen las mejoras de próximas versiones).

### 🔄 Cambiado
- ✍️ Prompt por defecto de la descripción: pide el JSON con los textos (sin emojis ni tablas). Un prompt propio que devuelva HTML se sigue usando tal cual.
- ✍️ Prompt por defecto de la descripción corta con una estructura fija para todos: «[Tipo] [Marca] [Nombre] para [género] en [material y color], [tamaño], [beneficio]. Ref. [referencia]» (100–200 caracteres, texto plano, sin datos que no correspondan al tipo de producto).

### 🐛 Corregido
- Descripciones distintas entre productos: emojis y filas variables, dos tablas con los mismos datos (Material, Color y Tamaño repetidos), nombres inventados («Diámetro», «Nombre») y cierre diferente en cada producto. La tabla tampoco se veía como tabla porque el tema no le da estilos.

## [1.4.3] - 2026-11-26

### ✨ Añadido
- 🔄 **Regenerar descripciones**: botón «Regenerar» en cada producto importado y «Regenerar descripciones (N)» para los seleccionados. Vuelve a generar con IA la descripción y la descripción corta aunque ya existan (también si se editaron a mano), con confirmación.
- 🧩 Variables `{tipo_producto}` («Reloj» o, en joyas, «Aretes (joya)»), `{material}` y `{color}`; el tipo de producto va también como primera línea de `{contexto}`.

### 🔄 Cambiado
- 💍 **Joyas**: el catálogo usa los campos de reloj (Correa / Caja) para el material y el color. En las joyas se guardan como **Material** y **Color** («Caja: Acero, Plateado» → Material «Acero», Color «Plateado») y se quitan Correa, Caja y Resistencia al agua: en las características, en la descripción corta provisional y en lo que recibe la IA (también en joyas importadas antes, sin reimportar).
- ✍️ Prompts por defecto: incluir solo características que correspondan al tipo de producto (en joyas, nada de correa, caja, esfera ni resistencia al agua aunque estén en los datos).

## [1.4.2] - 2026-11-25

### ✨ Añadido
- 🔗 Al actualizar el **nombre** de un producto ya importado, la **URL** (slug) se regenera con el nombre nuevo (solo si el nombre cambió) y queda en el log (`url_cambiada`).
- ↪️ **Redirección 301** de la URL vieja a la nueva: WordPress guarda el slug anterior (`_wp_old_slug`) en los productos publicados, y el plugin redirige esa URL vieja aunque la estructura de enlaces de producto no use base (p. ej. sin «/producto/»), donde la redirección propia de WordPress no actúa.

## [1.4.1] - 2026-11-25

### ✨ Añadido
- ✏️ Variable `{tipo}` en la plantilla del nombre: «Reloj» en los relojes y vacío en las joyas (su nombre del catálogo ya es el tipo: «Aretes», «Brazalete»). Ambos se configuran en Importación.

### 🔄 Cambiado
- ✏️ **Nombre sin repetidos**: el texto fijo de la plantilla y el `{tipo}` se quitan si esa palabra ya está en los datos del producto (sin mayúsculas, acentos ni plural). En las joyas, un «Reloj» escrito como texto fijo se cambia por el tipo de joya. Con «Reloj {marca} {nombre} {genero} {ref}»: reloj → «Reloj Armani Exchange LEILA SET DAMA AX7186SET»; joya → «Michael Kors Joyas Aretes DAMA MKJ8655040».

## [1.4.0] - 2026-11-24

### ✨ Añadido
- 🗂️ Columna **Categorías** en la tabla de productos: las categorías del producto importado (recortadas, con tooltip), el badge «Pendiente de asignar por IA» mientras la IA no elige el estilo y «Sin categoría» si no tiene ninguna.

### 🔄 Cambiado
- 🔒 Las categorías se asignan **solo si el producto no tiene** (nuevo, o importado sin categoría / en «Sin categorizar»). Las que ya tiene, a mano o por el plugin, no se vuelven a tocar al reimportar. Se quita «Categorías» de los campos a actualizar.

## [1.3.2] - 2026-11-24

### ✨ Añadido
- 🗂️ **Categorías por género** (Importación → Categorías): para cada género del catálogo (DAMA, HOMBRE, UNISEX…) se marcan las categorías de la tienda. Sugerencia automática por nombre: DAMA → Relojes + Mujer, HOMBRE → Relojes + Hombre, UNISEX → Relojes + Hombre + Mujer. Las de ofertas no se muestran.
- 💍 **Joyas**: si la marca contiene una de las palabras configuradas (por defecto «joyas, joyería, jewelry»), el producto va a «Joyas» → Mujer / Hombre según el género; se crean al importar la primera joya si no existen.
- 🎯 **Estilo con IA**: la IA elige 1 o 2 subcategorías (Análogo, Digital, Deportivo, Casual, Elegante, Cronógrafo, Smartwatch…) entre las que existen bajo el género del producto, con su propio prompt editable (variable `{opciones}`). Queda pendiente (`_hsy_style`) hasta clasificarse; entra en «Generar descripciones pendientes» y en la vista previa.
- «Categorías» en lo que se actualiza de los productos ya importados (conserva los estilos ya puestos).

### 🔄 Cambiado
- «Categoría de los nuevos» pasa a ser «Sin regla»: solo para productos cuyo género no tiene categorías marcadas.

## [1.3.1] - 2026-11-24

### ✨ Añadido
- 🏷️ Tabla **«Marcas del catálogo»** en Importación: cada marca del catálogo, cuántos productos tiene y a qué marca de la tienda irá («Equivalente» si el nombre no es idéntico) o si se creará.

### 🔄 Cambiado
- 🏷️ Emparejado de marcas más robusto para no crear duplicados: compara sin mayúsculas, acentos, espacios ni signos y sin palabras de relleno («reloj», «relojes», «relog»…); otras palabras sí cuentan («Michael Kors Joyas» ≠ «Michael Kors»). Busca primero por nombre y después por slug (una marca renombrada conserva su slug) y, si encajan varias, usa la que tiene más productos. Antes buscaba primero por slug y podía elegir una marca vacía duplicada.

## [1.3.0] - 2026-11-24

### ✨ Añadido
- 📊 **Contadores** en la pestaña Productos: total del catálogo, importados, pendientes de importar, con descripción, sin descripción y en borrador. Se actualizan al importar, generar o publicar.
- 🚀 Botón **«Publicar productos con descripción (N)»**: publica, por lotes y con confirmación, los productos importados que están en borrador y ya tienen descripción y descripción corta (misma regla que el badge «Pendiente de generar con IA»). Cada producto se vuelve a comprobar antes de publicarlo y queda en el log.

## [1.2.1] - 2026-11-24

### ✨ Añadido
- 🧩 Variables nuevas para los prompts: `{contexto}` (todos los datos del producto que existan, uno por línea: referencia, nombre, marca, info de la marca, características e información de la web; los vacíos se omiten), `{info_marca}` (descripción de la marca) y `{tienda}` (nombre del sitio).

### 🔄 Cambiado
- ✍️ Prompts por defecto adaptados de los del plugin de importación desde Drive/Excel: descripción larga en HTML con párrafo inicial, «Beneficios principales», «Características del producto» y «Especificaciones técnicas» en tablas y cierre comercial con `{tienda}`; descripción corta en texto plano, una frase de máximo 200 caracteres que termina con «Ref. {referencia}». Los prompts personalizados guardados no cambian.
- 🏷️ HTML permitido en lo que devuelve la IA: se añaden `<h2>`, `<i>`, `<small>` y tablas (`<table>`, `<tr>`, `<th>`, `<td>`, con estilos en línea).

## [1.2.0] - 2026-11-24

### ✨ Añadido
- 🔁 **Varias conexiones de IA con respaldo**: en Importación → Descripciones se marcan las conexiones de IA Conector que se pueden usar y se ordenan con ↑ ↓. Se usa la primera; si falla (tokens o límite de uso agotados, modelo inexistente, sin conexión…) se prueba la siguiente hasta que una responda.
- ⏸️ Una conexión que falla por **cuota o límite de uso** queda en pausa 15 minutos (no se gasta una llamada fallida en cada producto) y se vuelve a probar sola; se ve «En pausa» en la lista, con botón «Reanudar».
- 📝 El producto guarda qué conexión respondió (`_hsy_content_ai`) y el log, las conexiones saltadas; la vista previa también lo muestra.

### 🔄 Cambiado
- ⚙️ El ajuste `ai_connection` (una conexión) pasa a `ai_connections` (lista en orden de prioridad). La conexión elegida hasta ahora queda como la primera, sin hacer nada.

## [1.1.0] - 2026-11-24

### ✨ Añadido
- 🤖 **Descripciones con IA** usando el plugin IA Conector (Gemini, Groq…): genera la descripción y la descripción corta de los productos importados que las tienen pendientes. Se elige la conexión de IA.
- ⏱️ Se generan justo después de importar (opcional) o con el botón «Generar descripciones pendientes» de la pestaña Productos, de a un producto y con progreso.
- ✍️ **Prompts personalizables** (uno para la descripción y otro para la corta) con variables: `{nombre}`, `{nombre_tienda}`, `{marca}`, `{referencia}`, `{genero}`, `{correa}`, `{caja}`, `{tamano}`, `{resistencia_agua}`, `{caracteristicas}`, `{info_web}`, `{fuente_web}` y `{descripcion}`. Lista de variables con clic para insertar y botón para restaurar el prompt por defecto (un prompt vacío o igual al de por defecto sigue al de por defecto).
- 🧪 **Probar los prompts** con un producto importado: genera con los prompts del formulario sin guardarlos y muestra el resultado y los prompts enviados, sin cambiar el producto.
- 🔎 Si la búsqueda en Google está activa, lo encontrado llega a la IA en `{info_web}`.
- 📋 Columnas **Descripción** y **Descripción corta** en la tabla de productos: los primeros 20 caracteres y el texto completo en un tooltip (hover o foco). Si falta, o la corta es todavía la lista automática, muestra el badge «Pendiente de generar con IA». La tabla se refresca al terminar de importar o de generar.

### 🔄 Cambiado
- 📋 Tabla de productos con ancho automático: referencia, precios y estado ya no se cortan en varias líneas.
- 📝 «Pendiente» = producto importado sin descripción, o con la descripción corta automática (lista de características, meta `_hsy_short_auto`). Lo escrito a mano o generado antes nunca se reemplaza; los productos importados antes también se reconocen.
- 🧾 La lista de características pasa a ser la descripción corta **provisional**: al reimportar solo se reescribe si sigue siendo la automática.
- 🔎 La búsqueda en Google ya no corre al importar, sino en el paso de descripciones (sin IA, el texto encontrado sigue siendo la descripción).
- ⚙️ La sección «Descripción desde Google» de Importación pasa a llamarse **Descripciones** (IA, después de importar, Google y API key).

### 🐛 Corregido
- Las barras ocultas (acciones de la tabla, descripciones pendientes) se mostraban igual porque su `display: flex` anulaba el atributo `hidden`.

## [1.0.2] - 2026-11-23

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
