# videosHAQ

Sistema para visualizacion de videos por areas con acceso por codigo.

## Areas activas

- Admision
- Calidad
- Enfermeria

Tambien incluye acceso especial:

- SISTEMAS (subida de videos por area)

## Requisitos

- XAMPP con Apache
- PHP habilitado en Apache
- MySQL (MariaDB de XAMPP)

## Estructura

- `index.php`, `systems.php`, `stream.php`, `upload_chunk.php`: entrypoints en raiz
- `app/controllers/`: controladores principales
- `app/views/systems/`: componentes y modales de SISTEMAS
- `config/app.php`: configuracion de DB, areas, codigos y rutas
- `public/assets/css/main.css`: estilos
- `public/assets/js/player.js`: JS del reproductor
- `public/assets/js/systems.js`: JS del panel SISTEMAS
- `database/schema.sql`: script SQL de tablas y codigos iniciales
- `storage/uploads/private_videos/`: almacenamiento privado por area

## Configurar MySQL

1. Crea/importa base de datos ejecutando `database/schema.sql` en phpMyAdmin.
2. Verifica credenciales en `config/app.php`:
	- `DB_HOST`
	- `DB_PORT`
	- `DB_NAME`
	- `DB_USER`
	- `DB_PASS`

## Codigos iniciales

Definidos en la tabla `area_codes` (script `database/schema.sql`):

- Admision: `ADM-HAQ-2026`
- Calidad: `CAL-HAQ-2026`
- Enfermeria: `ENF-HAQ-2026`
- Sistemas: `SIS-HAQ-2026`

## Cargar videos

Coloca los videos en estas carpetas:

- `storage/uploads/private_videos/admision/`
- `storage/uploads/private_videos/calidad/`
- `storage/uploads/private_videos/enfermeria/`

Tambien puedes subirlos desde `systems.php` con el codigo de SISTEMAS.

La subida en SISTEMAS se hace por bloques (chunks) para soportar videos muy pesados (por ejemplo 2GB+).

Formatos permitidos:

- `.mp4`
- `.webm`
- `.ogg`

## Ejecucion

1. Inicia Apache en XAMPP.
2. Inicia MySQL en XAMPP.
3. Importa `database/schema.sql` (si aun no lo hiciste).
4. Abre en navegador: `http://localhost/videosHAQ/`.
5. Selecciona area e ingresa el codigo.

Para administracion:

- Abre `http://localhost/videosHAQ/systems.php`
- Ingresa codigo de SISTEMAS
- Sube videos por area

## Ajustes para videos de 2GB o mas

Aunque la subida es por bloques, revisa en `php.ini` para evitar cortes por limite de peticion/tiempo:

- `upload_max_filesize = 64M`
- `post_max_size = 64M`
- `max_execution_time = 0`
- `max_input_time = 0`
- `memory_limit = 512M`

Despues reinicia Apache.

## Seguridad implementada

- Acceso por area + codigo.
- Acceso separado para SISTEMAS.
- Codigos y enlaces de videos almacenados en MySQL.
- Los videos no se exponen como archivos publicos directos.
- Carpeta `storage/uploads/private_videos/` bloqueada por `.htaccess`.
- Reproduccion via `stream.php` con sesion activa.
- Se oculto boton de descarga en el reproductor (`nodownload`).

## Nota importante

En web no existe bloqueo 100% infalible contra descarga/captura de contenido.
Este sistema reduce fuertemente el acceso directo y la descarga casual, pero un usuario avanzado aun podria capturar el video por otros medios.
