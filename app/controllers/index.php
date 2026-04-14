<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();

$error = '';
$requestError = '';
$requestSuccess = '';
$openRequestModal = false;
$toastError = '';
$toastSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf()) {
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit;
        }

        $error = 'La sesion expiro. Recarga la pagina e intenta nuevamente.';
        $toastError = $error;
        goto render;
    }

    enforce_post_csrf();

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($action === 'login') {
        if (throttle_is_limited('area_login')) {
            $error = 'Demasiados intentos. Espera unos minutos e intenta de nuevo.';
            $toastError = $error;
            goto render;
        }

        $area = strtolower(trim((string) ($_POST['area'] ?? '')));
        $code = trim((string) ($_POST['code'] ?? ''));

        if (validate_area_access($area, $code)) {
            session_regenerate_id(true);
            $_SESSION['area'] = $area;
            throttle_reset('area_login');
            header('Location: index.php');
            exit;
        }

        throttle_hit('area_login');
        $error = 'Codigo o area incorrectos.';
        $toastError = $error;
    }

    if ($action === 'request_code') {
        if (throttle_is_limited('request_code')) {
            $requestError = 'Demasiadas solicitudes. Espera unos minutos e intenta de nuevo.';
            $openRequestModal = true;
            goto render;
        }

        $name = trim((string) ($_POST['request_name'] ?? ''));
        $areaKey = strtolower(trim((string) ($_POST['request_area'] ?? '')));
        $employeeNumber = trim((string) ($_POST['request_employee_number'] ?? ''));

        if ($name === '' || $areaKey === '' || $employeeNumber === '') {
            $requestError = 'Completa nombre, area y numero de empleado.';
            $toastError = $requestError;
            $openRequestModal = true;
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $requestError = 'El nombre debe tener entre 3 y 120 caracteres.';
            $toastError = $requestError;
            $openRequestModal = true;
        } elseif (!area_exists($areaKey)) {
            $requestError = 'El area seleccionada no es valida.';
            $toastError = $requestError;
            $openRequestModal = true;
        } elseif (!preg_match('/^[A-Za-z0-9-]{3,30}$/', $employeeNumber)) {
            $requestError = 'Numero de empleado invalido. Usa solo letras, numeros o guion.';
            $toastError = $requestError;
            $openRequestModal = true;
        } else {
            $queued = queue_access_code_request($name, get_area_label($areaKey), $employeeNumber);

            if ($queued) {
                trigger_mail_queue_worker_async();
                throttle_reset('request_code');
                $requestSuccess = 'Solicitud enviada correctamente a SISTEMAS.';
                $toastSuccess = $requestSuccess;
            } else {
                throttle_hit('request_code');
                $requestError = 'No se pudo enviar la solicitud por correo. Intenta nuevamente.';
                $toastError = $requestError;
                $openRequestModal = true;
            }
        }
    }
}

render:

$activeArea = (string) ($_SESSION['area'] ?? '');
$hasAccess = area_exists($activeArea);
$videosPerPage = 24;
$currentPage = 1;
$totalPages = 1;
$totalVideos = 0;
$videos = [];
$firstVideoMime = 'video/mp4';

if ($hasAccess) {
    $requestedPage = (int) ($_GET['p'] ?? 1);
    $currentPage = $requestedPage > 0 ? $requestedPage : 1;

    $totalVideos = count_videos_for_area($activeArea);
    $totalPages = max(1, (int) ceil($totalVideos / $videosPerPage));

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $videosPerPage;
    $videos = get_videos_for_area($activeArea, $videosPerPage, $offset);
}

$firstVideoId = isset($videos[0]['id']) ? (int) $videos[0]['id'] : 0;
$firstVideoMimeCandidate = isset($videos[0]['mime_type']) ? strtolower((string) $videos[0]['mime_type']) : '';
if (in_array($firstVideoMimeCandidate, ALLOWED_VIDEO_MIME_TYPES, true)) {
    $firstVideoMime = $firstVideoMimeCandidate;
}
$availableAreas = get_video_areas(true);

if ($hasAccess) {
    $expiresAt = (int) ($_SESSION['stream_token_expires'] ?? 0);
    if (!isset($_SESSION['stream_token']) || $expiresAt < time()) {
        $_SESSION['stream_token'] = bin2hex(random_bytes(24));
        $_SESSION['stream_token_expires'] = time() + 900;
    }
}

$streamToken = $hasAccess ? (string) ($_SESSION['stream_token'] ?? '') : '';

send_security_headers();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Work+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/assets/css/main.css?v=20260330-3">
</head>
<body class="<?php echo $openRequestModal ? 'index-page modal-open' : 'index-page'; ?>">
<div class="bg-layer"></div>
<main class="layout">
    <header class="hero">
        <p class="kicker">Plataforma Interna</p>
        <h1>Hospital Angeles Queretaro</h1>
        <p class="subtitle">Visualizacion de Videos por Area</p>
    </header>

    <?php if (!$hasAccess): ?>
        <section class="card login-card portal-login-card">
            <div class="login-top-actions">
                <a class="systems-link top-right" href="systems.php">SISTEMAS</a>
            </div>
            <?php if ($toastError !== ''): ?>
                <div id="portalToastError" class="hidden" data-toast-kind="error" data-toast-message="<?php echo htmlspecialchars($toastError, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endif; ?>
            <?php if ($toastSuccess !== ''): ?>
                <div id="portalToastSuccess" class="hidden" data-toast-kind="success" data-toast-message="<?php echo htmlspecialchars($toastSuccess, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endif; ?>
            <h2>Ingresar</h2>
            <p>Selecciona el area e ingresa el codigo de acceso.</p>
            <form method="post" class="portal-login-form" autocomplete="off">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="login">
                <label for="area">Area</label>
                <select id="area" name="area" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($availableAreas as $areaRow): ?>
                        <option value="<?php echo htmlspecialchars($areaRow['area_key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($areaRow['area_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="code">Codigo de acceso</label>
                <input id="code" name="code" type="password" placeholder="Ingresa tu codigo" required>

                <button type="submit">Entrar</button>
            </form>
            <button type="button" id="openRequestCodeModal" class="request-code-btn">SOLICITAR CODIGO A SISTEMAS</button>
            <p class="small-note">Si no cuentas con codigo, solicita acceso con tus datos de colaborador.</p>
        </section>

        <div id="requestCodeModal" class="modal request-code-modal <?php echo $openRequestModal ? '' : 'hidden'; ?>" role="dialog" aria-modal="true" aria-labelledby="requestCodeTitle">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 id="requestCodeTitle">Solicitar codigo a SISTEMAS</h3>
                    <button type="button" class="modal-close" data-close-request-modal>&times;</button>
                </div>

                <p class="small-note">Completa tus datos para enviar la solicitud directamente al area de SISTEMAS.</p>

                <form method="post" class="request-code-form" autocomplete="off">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="request_code">

                    <label for="request_name">Nombre completo</label>
                    <input id="request_name" name="request_name" type="text" maxlength="120" required>

                    <label for="request_area">Area</label>
                    <select id="request_area" name="request_area" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($availableAreas as $areaRow): ?>
                            <option value="<?php echo htmlspecialchars($areaRow['area_key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($areaRow['area_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="request_employee_number">Numero de empleado</label>
                    <input id="request_employee_number" name="request_employee_number" type="text" maxlength="30" required>

                    <button type="submit">Solicitar</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <section class="card dashboard-card">
            <div class="dashboard-header">
                <div>
                    <p class="chip">Area activa</p>
                    <h2><?php echo htmlspecialchars(get_area_label($activeArea), ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="ghost logout-btn">Cerrar sesion</button>
                </form>
            </div>

            <?php if (count($videos) === 0): ?>
                <p class="empty">No hay videos cargados en esta area.</p>
            <?php else: ?>
                <div class="player-wrap" id="playerWrap">
                    <video id="player" data-stream-token="<?php echo htmlspecialchars($streamToken, ENT_QUOTES, 'UTF-8'); ?>" disablePictureInPicture disableRemotePlayback controlsList="nodownload noplaybackrate noremoteplayback" playsinline preload="metadata" draggable="false">
                        <source id="playerSource" src="stream.php?id=<?php echo $firstVideoId; ?>&token=<?php echo rawurlencode($streamToken); ?>" type="<?php echo htmlspecialchars($firstVideoMime, ENT_QUOTES, 'UTF-8'); ?>">
                        Tu navegador no soporta video HTML5.
                    </video>
                </div>
                <div class="player-controls" aria-label="Controles del reproductor">
                    <button id="playToggle" type="button" class="player-btn">Reproducir</button>
                    <div class="volume-wrap">
                        <label for="volumeControl">Volumen</label>
                        <input id="volumeControl" type="range" min="0" max="1" step="0.05" value="1">
                    </div>
                    <button id="fullscreenToggle" type="button" class="player-btn">Pantalla completa</button>
                </div>

                <div class="list-header">Videos del area</div>
                <?php if ($totalVideos > 0): ?>
                    <p class="small-note">Mostrando <?php echo count($videos); ?> de <?php echo $totalVideos; ?> videos (pagina <?php echo $currentPage; ?> de <?php echo $totalPages; ?>).</p>
                <?php endif; ?>
                <div class="video-list">
                    <?php foreach ($videos as $index => $video): ?>
                        <button
                            class="video-item <?php echo $index === 0 ? 'active' : ''; ?>"
                            type="button"
                            data-id="<?php echo (int) $video['id']; ?>"
                            data-mime="<?php echo htmlspecialchars((string) ($video['mime_type'] ?? 'video/mp4'), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span class="dot"></span>
                            <span><?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Paginacion de videos">
                        <?php if ($currentPage > 1): ?>
                            <a class="pagination-link" href="index.php?p=<?php echo $currentPage - 1; ?>">Anterior</a>
                        <?php else: ?>
                            <span class="pagination-link is-disabled">Anterior</span>
                        <?php endif; ?>

                        <span class="pagination-current">Pagina <?php echo $currentPage; ?> de <?php echo $totalPages; ?></span>

                        <?php if ($currentPage < $totalPages): ?>
                            <a class="pagination-link" href="index.php?p=<?php echo $currentPage + 1; ?>">Siguiente</a>
                        <?php else: ?>
                            <span class="pagination-link is-disabled">Siguiente</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
<script src="public/assets/js/portal.js?v=20260330-1"></script>
<script src="public/assets/js/player.js"></script>
</body>
</html>
