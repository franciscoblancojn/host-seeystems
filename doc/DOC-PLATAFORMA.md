# DOC-PLATAFORMA.md

> Cómo funciona la plataforma Swiss Sport B2B (`https://host.seeystems.com/~swissstg`) y cómo la lee el plugin.

---

## Regla: solo lectura

El plugin **nunca** crea ni modifica datos en la plataforma.

| Permitido | Prohibido |
|---|---|
| GET `/auth/login` | POST/GET `cart`, `cart/add`, `cart/ids` |
| POST `/auth/login` (único POST) | `orders` |
| GET `/brands` | `auth/logout` (invalida la sesión) |
| GET `/catalog`, `/catalog?page=N`, `/catalog?marca_id=N` | `auth/cambiarPassword`, `auth/recover` |
| GET `/dashboard` (lectura) | Cualquier otro POST / PUT / DELETE |

`bin/harness.sh` valida que en `src/` solo exista un POST (el login) y que no aparezcan las rutas prohibidas.

---

## Login

1. `GET /auth/login` → HTML con `<input type="hidden" name="csrf_token" value="...">` y cookie `ssb2b` (HttpOnly, secure).
2. `POST /auth/login` con `csrf_token`, `email`, `password` (form-urlencoded).
3. Éxito: `302` → `/brands`. Fallo: vuelve a `/auth/login`.
4. Rutas protegidas sin sesión responden `302` → `/auth/login`.

`HSEY_CLIENT` detecta "estoy en el login" por redirect a `/auth/login` o por un body con `csrf_token` + `password`.

---

## Catálogo

- `GET /catalog` → página 1 (unas 50 tarjetas). Hoy: 23 páginas, ~1.100 productos.
- Paginación: `<nav class="pagination">` con `<a class="page-link" href=".../catalog?page=2#catalogTop">Siguiente &raquo;</a>`.
- Filtro por marca: `/catalog?marca_id=N` (links en `/brands`).
- Cada producto es un `<div class="product-card" data-pid="{id}" onclick="openProductModal({JSON})">`. El JSON (HTML-escaped en el atributo) es la fuente de verdad; el resto del HTML de la tarjeta es presentación.

### Ejemplo de producto

```json
{
  "id": 1546,
  "ref": "SR6386",
  "nombre": "BAYLEY",
  "marca": "SKECHERS",
  "precio": 122970,
  "pvp": "204950.00",
  "precio_mayorista": "0.00",
  "precio_especial": "0.00",
  "tipo_precio": "Negociado",
  "genero": "DAMA",
  "tamano": "34 mm",
  "descripcion": "DISPONIBLE",
  "correa_material": "Silicona",
  "correa_color": "Blanco",
  "caja_material": "Plástico",
  "caja_color": "Blanco",
  "resistencia_agua": "",
  "stock": 24,
  "stock_reservado": 0,
  "transito": 0,
  "imgs": [
    "https://host.seeystems.com/~swissstg/img/productos/SR6386/SR6386_1.jpg"
  ]
}
```

| Campo | Tipo | Nota |
|---|---|---|
| `id` | int | ID interno (`data-pid`) |
| `ref` | string | Referencia / SKU |
| `precio` | int | Precio para el cliente logueado |
| `pvp`, `precio_mayorista`, `precio_especial` | string decimal | `"0.00"` = no aplica |
| `stock`, `stock_reservado`, `transito` | int | Unidades |
| `imgs` | string[] | URLs absolutas; pueden no existir (la web usa `img/no-image.svg`) |

---

## Explorar la plataforma manualmente

- Desde WordPress: **Host Seeystems → Productos → HTML** (GET con la sesión del plugin).
- Desde terminal: credenciales en `.env` (`SITE`, `USER`, `PASSWORD`; nunca commitear). Solo GET después del login.
