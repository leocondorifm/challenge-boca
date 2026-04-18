# Product Comparison API

REST API en PHP puro (sin framework) para exponer catálogo, detalle y comparación de productos, pensada como challenge técnico con foco en capas, manejo de errores y calidad de código.

## Arquitectura (N-Tier)

Flujo: **Router → Controller → Service → Repository → Model**

- **Router**: resuelve método y path y delega en el controlador.
- **Controller**: adapta HTTP (query params, ids) sin lógica de negocio.
- **Service**: reglas de negocio, sparse fieldsets en comparación, armado de respuestas.
- **Repository**: único punto de acceso a datos JSON; valida JSON y registra fallos.
- **Model**: entidades pequeñas y tipadas mapeadas desde filas JSON.

### Decisiones (README)

1. **Patrón EAV** (`productos_detalle`): atributos heterogéneos por tipo de producto sin migraciones de esquema.
2. **JSON como persistencia**: mock liviano; reemplazar por base real implica cambiar solo repositorios (y eventualmente modelos/DTO).
3. **Filtrado de campos en Service**: el repositorio devuelve el dataset completo; el servicio aplica el fieldset opcional de comparación.
4. **Multimoneda vía listas de precios**: `precios` referencia `listas_de_precios`, que a su vez referencia `monedas`; permite varias listas vigentes.
5. **PHP puro**: demuestra fundamentos sin magia de framework.
6. **Logger dedicado** (`src/Logger/Logger.php`): observabilidad en runtime sin dependencias externas; escribe en `logs/error.log`.
7. **Rate limiting**: deliberadamente fuera de la app; en producción va en API Gateway / reverse proxy (Nginx).

## Logger

- Archivo: `logs/error.log` (el directorio `logs/` se crea en runtime si no existe).
- Formato de línea:

  `[YYYY-mm-dd HH:ii:ss] [LEVEL] <REQUEST_URI> — <mensaje> [<JSON context opcional>]`

- Ejemplo:

  `[2026-04-18 10:23:45] [ERROR] /api/products/99 — Product not found {"id":99}`

- API estática: `Logger::error()`, `Logger::warning()`, `Logger::info()`.

Uso en el proyecto:

- **ApiException** en `index.php`: siempre se registra en nivel ERROR antes de responder JSON.
- **Repository**: ERROR ante archivo faltante o JSON corrupto/ inválido.
- **Service**: WARNING si la comparación termina sin campos (caso borde).

`logs/error.log` está en `.gitignore`.

## Requisitos

- PHP 8.1+
- Composer (solo para autoload y PHPUnit)

## Instalación

```bash
cd /ruta/al/proyecto
composer install
```

## Ejecutar en local

Servidor embebido de PHP (en la raíz del proyecto):

```bash
php -S localhost:8000 index.php
```

Con Apache/XAMPP, el `.htaccess` reenvía todo a `index.php`.

## Tests

```bash
./vendor/bin/phpunit
```

## OpenAPI (Swagger)

- Especificación: `docs/openapi.yaml` (OpenAPI 3.0.3), servido de forma estática (p. ej. `http://localhost/meli/docs/openapi.yaml`).
- **Swagger UI** (`docs/index.html`). Abrí la carpeta **con barra final o sin ella** según el VirtualHost, p. ej. `http://localhost/meli/docs/`. Bajo XAMPP/Apache, la regla de rewrite a `index.php` **no** aplica a la carpeta física `docs/`, que incluye el índice. El listado de archivos (Index of) quedó desactivado con `docs/.htaccess` y `Options -Indexes`.
- **php -S**: si el estático `docs/*` no se encuentra, el embebido puede tratar de pasar al router; mientras existan `docs/index.html` y `docs/openapi.yaml` en el disco, suelen resolverse como archivos fijos.
- **Importar a Postman**: *Import* → *file* o URL al YAML.

## Endpoints

Todas las respuestas son JSON. Éxito: `{ "success": true, "data": ... }`. Error: `{ "success": false, "error": { "code", "message" } }`.

### GET `/api/products`

Lista productos activos con categoría y precio. Por defecto usa la **primera lista activa en ARS**. Cada `precio` incluye `lista_de_precios_id`, `valor`, `moneda` y `simbolo`.

Query opcionales:

- `categoria_id`: filtra por categoría.
- `lista_de_precios_id`: fuerza una lista concreta (debe estar activa); la **moneda se infiere** de esa lista (no hace falta otro parámetro).

```bash
curl -s "http://localhost:8000/api/products?categoria_id=1"
curl -s "http://localhost:8000/api/products?lista_de_precios_id=2"
```

### GET `/api/products/{id}`

Detalle con especificaciones EAV y precio.

- Sin `lista_de_precios_id`: `moneda` opcional (`ARS` si no se envía); se usa la primera lista activa de esa moneda.
- Con `lista_de_precios_id`: el precio sale de esa lista; la moneda del payload coincide con la de la lista. Si además enviás `moneda`, **debe coincidir** con la de la lista o la API responde `400`.

```bash
curl -s "http://localhost:8000/api/products/1?moneda=USD"
curl -s "http://localhost:8000/api/products/1?lista_de_precios_id=2"
curl -s "http://localhost:8000/api/products/1?lista_de_precios_id=2&moneda=USD"
```

### GET `/api/products/compare`

Comparación entre 2 y 4 productos. `campos` opcional (sparse fieldset); si se omite, se devuelve la unión de claves + `precio`. El precio usa por defecto la lista ARS estándar, salvo que envíes `lista_de_precios_id` (mismas reglas que el listado: lista activa, moneda inferida).

```bash
curl -s "http://localhost:8000/api/products/compare?ids=1,2&campos=marca,camara,precio,bateria"
curl -s "http://localhost:8000/api/products/compare?ids=1,2&campos=precio&lista_de_precios_id=2"
```

### GET `/api/categories`

Categorías activas.

```bash
curl -s "http://localhost:8000/api/categories"
```

## EAV (resumen)

Cada fila en `productos_detalle` es un par `(fk_producto, clave, valor)`. Ventaja: nuevos atributos sin alterar columnas del producto; desventaja: consultas más verbosas y sin tipado fuerte en BD (mitigado en capa de aplicación).

## Multimoneda (resumen)

Los importes viven en `precios` ligados a una `lista_de_precios`, que define la moneda. Sin override, el servicio elige la **primera lista activa** para `ARS` o `USD`. Con `?lista_de_precios_id=` elegís la lista explícita (útil si hay varias listas por moneda o para alinear cliente y backend).
