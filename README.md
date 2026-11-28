# 📦 Host Seeystems

> Conecta con un catálogo B2B externo mediante usuario y clave y permite importar sus productos a WooCommerce, de forma individual o masiva.

**Versión:** 1.9.2 · **WordPress:** 5.8+ · **PHP:** 7.4+ · **WooCommerce:** requerido · **Licencia:** GPLv2+

📋 [Registro de cambios](CHANGELOG.md) · 🤖 [Reglas para IAs](AGENTS.md) · 📦 [Releases](https://github.com/franciscoblancojn/host-seeystems/releases)

---

## 💡 Cómo funciona

1. En **Conexión** se guardan la URL del catálogo, el correo y la contraseña, y se prueba el acceso.
2. En **Productos**, «Cargar productos del catálogo» inicia sesión, recorre todas las páginas del catálogo y muestra la tabla (con buscador y filtro *sin importar / ya importados*).
3. Se importa uno a uno (botón de la fila), los seleccionados o todos los visibles. La importación va por lotes con barra de progreso.
4. Cada producto creado guarda su id de origen: volver a importarlo **actualiza** el mismo producto (según los campos elegidos en **Importación**) en lugar de duplicarlo.

Ejemplo (ficha del catálogo → producto de WooCommerce):

| Catálogo | WooCommerce |
|---|---|
| ARMANI EXCHANGE | Marca «Armani Exchange» (`product_brand`, se crea si no existe) |
| LEILA SET | Nombre |
| AX7186SET | SKU y característica «Referencia» |
| Genero, Correa, Caja, Tamano | Atributos visibles (y descripción corta provisional) |
| PVP sugerido $1.019.900 | Precio normal |
| Precio mayorista $611.940 | Meta `_hsy_wholesale_price`, visible en el editor junto a los precios |
| Stock 3 (mínimo 3) | Stock 0 |
| Sin descripción | Descripción y descripción corta generadas con IA (IA Conector), opcionalmente con lo encontrado en Google como contexto |

Todos los productos importados llevan la meta `_hsy_imported = yes`. Si su stock baja hasta el mínimo o por debajo, se pone en 0 automáticamente.

## ⚙️ Configuración

En el menú **Importar productos** del admin:

| Pestaña | Ajustes |
|---|---|
| Productos | Cargar el catálogo, buscar, importar individual / seleccionados / todos. |
| Conexión | URL del catálogo, correo, contraseña (no se muestra; vacío = conservar), rutas de login y catálogo, «Probar conexión». |
| Importación | Estado de los nuevos (borrador por defecto), plantilla del nombre, categoría opcional, qué se actualiza en los existentes, imágenes, **stock mínimo** (3 por defecto) y **descripciones** con IA (plugin IA Conector) y/o Google (API key de Serper.dev). |

## 📋 Requisitos

- Opcional: plugin **IA Conector** activo con una conexión (Gemini, Groq…) para generar descripciones.

- WordPress 5.8+
- PHP 7.4+
- WooCommerce: requerido
- Permiso `manage_woocommerce`

## ⚙️ Instalación

1. Descarga el zip de la rama `master`: https://github.com/franciscoblancojn/host-seeystems/archive/refs/heads/master.zip
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y actívalo.
3. Ve a **Importar productos**, revisa los ajustes y guarda.

## 🔄 Actualizaciones

- Usa `FWUUpdate` (librería `wordpress_utils`), configurado en `index.php` (repositorio, rama `master` y token) con el formato común.
- En **Plugins** aparece «Actualizar» cuando hay una release más nueva en GitHub. Para eso, cada versión necesita su release: la crea el workflow al subir el tag.
- `FWUUpdate` instala el zip de la rama `master`, que se descomprime en la carpeta `host-seeystems-master`. Conviene hacer la **primera instalación también desde ese zip**, para que la carpeta no cambie con la primera actualización: si cambia, WordPress desactiva el plugin.

## 🧑‍💻 Desarrollo

```bash
npm run install          # composer install → libs/ (franciscoblancojn/wordpress_utils)
npm run check            # validaciones: php -l, JS ES5, require_once, secretos y dependencias
npm run push-v -- patch  # o minor | major → publica una versión
```

`npm run push-v`:

1. exige una sección `## [Sin publicar]` en [`CHANGELOG.md`](CHANGELOG.md) y que `npm run check` pase;
2. sube la versión en `index.php`, `package.json` y este README;
3. cambia `## [Sin publicar]` por `## [X.Y.Z] - fecha`;
4. hace commit «Release X.Y.Z», crea el tag `X.Y.Z` y hace push.

Después, GitHub Actions crea la release con `host-seeystems.zip`.

## 🗂️ Estructura

```
host-seeystems/
├── index.php                 # Cabecera del plugin, constantes HSY_*, FWUUpdate, carga
├── composer.json             # wordpress_utils, autoloader-suffix HSY
├── libs/                     # Composer → libs (se versiona; no editar)
├── bin/harness.sh            # Validaciones (npm run check)
├── scripts/bump-version.sh   # npm run push-v
├── .github/workflows/        # Release con el zip al subir un tag
└── src/
    ├── _.php                 # Cargador maestro
    ├── data/                 # HSY_USE_DATA_CONFIG (ajustes) y HSY_USE_DATA_CATALOG (último catálogo leído)
    ├── services/             # HSY_CLIENT (sesión), HSY_PARSER (HTML), HSY_CATALOG_SERVICE, HSY_IMPORTER, log
    ├── hooks/                # Integración con WordPress / WooCommerce
    ├── api/                  # Guardado de ajustes (admin-post) y AJAX
    ├── page/                 # Menú «Importar productos» y secciones (FWUPage)
    ├── js/admin.js           # ES5 + jQuery
    └── css/admin.css
```
