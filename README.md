# videosHAQ

Sistema interno para visualizacion de videos por areas con acceso visual por tarjetas.

## Modelo de acceso actual

- El portal principal muestra tarjetas por area: Admision, Calidad, DO, Enfermeria y Direccion Gral.
- El acceso a cada area de visualizacion es directo (sin codigo por area).
- La tarjeta de SISTEMAS es la unica que solicita contrasena para entrar al panel de administracion y subida de videos.

## Requisitos

- XAMPP con Apache
- PHP habilitado en Apache
- MySQL (MariaDB de XAMPP)

## Estructura

- `index.php`, `systems.php`, `stream.php`, `upload_chunk.php`: entrypoints en raiz
- `app/controllers/`: controladores principales
- `app/views/systems/`: componentes y modales de SISTEMAS
- `config/app.php`: configuracion de DB, seguridad y rutas
- `public/assets/css/main.css`: estilos del portal y panel
- `public/assets/js/player.js`: JS del reproductor
- `public/assets/js/portal.js`: JS de notificaciones del portal
- `public/assets/js/systems.js`: JS del panel SISTEMAS
- `public/assets/img/areas/`: logos SVG por tarjeta
- `database/schema.sql`: script SQL de tablas y datos base
- `D:/VIDEOSHAQ/`: almacenamiento privado por area (fuera del proyecto)

## Configurar MySQL

1. Importa `database/schema.sql` en phpMyAdmin.
2. Verifica credenciales en `config/app.php`:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

## Contrasena inicial de SISTEMAS

Definida en la tabla `area_codes` dentro de `database/schema.sql`:

- Sistemas: `SIS-HAQ-2026`

## Cargar videos

Puedes cargar videos desde `systems.php` una vez autenticado en SISTEMAS.

La subida se realiza por bloques (chunks) para soportar videos grandes.

Formatos permitidos:

- `.mp4`
- `.webm`
- `.ogg`

## Ejecucion

1. Inicia Apache en XAMPP.
2. Inicia MySQL en XAMPP.
3. Importa `database/schema.sql`.
4. Abre `http://localhost/videosHAQ/`.
5. Elige una tarjeta de area para visualizar videos o la tarjeta de SISTEMAS para administrar contenido.

## Ajustes recomendados para videos grandes

Revisa en `php.ini`:

- `upload_max_filesize = 64M`
- `post_max_size = 64M`
- `max_execution_time = 0`
- `max_input_time = 0`
- `memory_limit = 512M`

Despues reinicia Apache.

## Seguridad implementada

- Sesiones seguras con regeneracion periodica de ID.
- CSRF en formularios y subida por chunks.
- Acceso protegido para panel de SISTEMAS con contrasena.
- Videos fuera del webroot en `D:/VIDEOSHAQ/`.
- Reproduccion via `stream.php` con token de sesion temporal.

## Nota

No existe bloqueo 100% infalible contra captura de contenido en la web. Este sistema reduce acceso directo y descarga casual, pero no elimina todos los vectores de captura avanzada.
