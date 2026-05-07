<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();
ensure_portal_default_areas();

$error = '';
$toastError = '';
$systemsModalError = '';
$openSystemsModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf()) {
        if ($action === 'logout' || $action === 'back_to_menu') {
            unset($_SESSION['area'], $_SESSION['stream_token'], $_SESSION['stream_token_expires']);
            header('Location: index.php');
            exit;
        }

        $error = 'La sesion expiro. Recarga la pagina e intenta nuevamente.';
        $toastError = $error;

        if ($action === 'systems_login_modal') {
            $systemsModalError = $error;
            $openSystemsModal = true;
        }

        goto render;
    }

    enforce_post_csrf();

    if ($action === 'logout' || $action === 'back_to_menu') {
        unset($_SESSION['area'], $_SESSION['stream_token'], $_SESSION['stream_token_expires']);
        header('Location: index.php');
        exit;
    }

    if ($action === 'systems_login_modal') {
        if (throttle_is_limited('systems_login')) {
            $systemsModalError = 'Demasiados intentos. Espera unos minutos e intenta de nuevo.';
            $toastError = $systemsModalError;
            $openSystemsModal = true;
            goto render;
        }

        $systemsCode = trim((string) ($_POST['systems_code'] ?? ''));

        if (validate_systems_access($systemsCode)) {
            session_regenerate_id(true);
            $_SESSION['systems_access'] = true;
            throttle_reset('systems_login');
            header('Location: systems.php');
            exit;
        }

        throttle_hit('systems_login');
        $systemsModalError = 'Contrasena de SISTEMAS incorrecta.';
        $toastError = $systemsModalError;
        $openSystemsModal = true;
    }

    if ($action === 'select_area') {
        $area = strtolower(trim((string) ($_POST['area'] ?? '')));

        if (area_exists($area)) {
            session_regenerate_id(true);
            $_SESSION['area'] = $area;
            header('Location: index.php');
            exit;
        }

        $error = 'El area seleccionada no esta disponible en este momento.';
        $toastError = $error;
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
$availableAreasByKey = [];
foreach ($availableAreas as $areaRow) {
    $availableAreasByKey[(string) $areaRow['area_key']] = $areaRow;
}

$portalCards = [
    [
        'area_key' => 'admision',
        'area_label' => 'Admision',
        'description' => 'Ingreso y orientacion al paciente.',
        'logo' => 'public/assets/img/portal-icons/admision.svg',
    ],
    [
        'area_key' => 'calidad',
        'area_label' => 'Calidad',
        'description' => 'Estandares y mejora continua hospitalaria.',
        'logo' => 'public/assets/img/portal-icons/calidad.svg',
    ],
    [
        'area_key' => 'do',
        'area_label' => 'DO',
        'description' => 'Desarrollo organizacional y cultura.',
        'logo' => 'public/assets/img/portal-icons/do.svg',
    ],
    [
        'area_key' => 'enfermeria',
        'area_label' => 'Enfermeria',
        'description' => 'Protocolos de atencion y cuidados clinicos.',
        'logo' => 'public/assets/img/portal-icons/enfermeria.svg',
    ],
    [
        'area_key' => 'direccion-gral',
        'area_label' => 'Direccion Gral',
        'description' => 'Comunicados y lineamientos institucionales.',
        'logo' => 'public/assets/img/portal-icons/direccion-gral.svg',
    ],
    [
        'area_key' => SYSTEM_AREA_KEY,
        'area_label' => 'Sistemas',
        'description' => 'Panel protegido para subir y gestionar videos.',
        'logo' => 'public/assets/img/portal-icons/sistemas.svg',
    ],
];

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
    <link rel="icon" type="image/png" href="logohaq1.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Work+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/assets/css/main.css?v=20260507-9">
</head>
<body class="<?php echo $openSystemsModal ? 'index-page modal-open' : 'index-page'; ?>">
<div class="bg-layer"></div>
<main class="layout">
    <header class="hero">
        <p class="kicker">Plataforma Interna</p>
        <h1>Hospital Angeles Queretaro</h1>
        <p class="subtitle">Visualizacion de Videos por Area</p>
    </header>

    <?php if (!$hasAccess): ?>
        <section class="portal-cards-card">
            <?php if ($toastError !== ''): ?>
                <div id="portalToastError" class="hidden" data-toast-kind="error" data-toast-message="<?php echo htmlspecialchars($toastError, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endif; ?>
            <h2>Selecciona un area</h2>
            <p>Acceso directo por tarjeta para visualizacion. Solo la tarjeta de Sistemas solicita contrasena para entrar al panel de carga.</p>

            <div class="portal-areas-grid" role="list" aria-label="Accesos por area">
                <?php foreach ($portalCards as $card): ?>
                    <?php $areaKey = (string) $card['area_key']; ?>
                    <?php $isSystemsCard = $areaKey === SYSTEM_AREA_KEY; ?>
                    <?php $isAreaAvailable = $isSystemsCard || isset($availableAreasByKey[$areaKey]); ?>
                    <article class="portal-area-card <?php echo $isSystemsCard ? 'is-systems' : ''; ?> <?php echo $isAreaAvailable ? '' : 'is-disabled'; ?>" role="listitem">
                        <div class="portal-area-logo-wrap">
                            <img class="portal-area-logo" src="<?php echo htmlspecialchars((string) $card['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo <?php echo htmlspecialchars((string) $card['area_label'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="portal-area-body">
                            <h3><?php echo htmlspecialchars((string) $card['area_label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars((string) $card['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <?php if ($isSystemsCard): ?>
                            <button type="button" class="portal-area-action" data-open-systems-modal="systemsAccessModal">Entrar a Sistemas</button>
                        <?php elseif ($isAreaAvailable): ?>
                            <form method="post" class="portal-area-form" autocomplete="off">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="select_area">
                                <input type="hidden" name="area" value="<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="portal-area-action">Entrar al area</button>
                            </form>
                        <?php else: ?>
                            <p class="portal-area-unavailable">No disponible temporalmente</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div id="systemsAccessModal" class="modal systems-access-modal <?php echo $openSystemsModal ? '' : 'hidden'; ?>" role="dialog" aria-modal="true" aria-labelledby="systemsAccessTitle">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 id="systemsAccessTitle">Acceso a Sistemas</h3>
                    <button type="button" class="modal-close" data-close-systems-modal>&times;</button>
                </div>

                <p class="small-note">Ingresa la contrasena para acceder al panel de carga de videos.</p>

                <?php if ($systemsModalError !== ''): ?>
                    <div class="alert"><?php echo htmlspecialchars($systemsModalError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" class="systems-modal-form" autocomplete="off">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="systems_login_modal">

                    <label for="systems_code_modal">Contrasena SISTEMAS</label>
                    <input id="systems_code_modal" name="systems_code" type="password" required>

                    <button type="submit">Entrar a Sistemas</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <section class="card dashboard-card area-dashboard-card">
            <div class="dashboard-header area-dashboard-header">
                <div class="area-heading-block">
                    <p class="chip area-chip">Area activa</p>
                    <h2 class="area-active-title"><?php echo htmlspecialchars(get_area_label($activeArea), ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <form method="post" class="inline-exit-form area-menu-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="back_to_menu">
                    <button type="submit" class="ghost back-menu-btn area-menu-btn">Regresar al menu</button>
                </form>
            </div>

            <?php if (count($videos) === 0): ?>
                <p class="empty">No hay videos cargados en esta area.</p>
            <?php else: ?>
                <div class="list-header area-list-header">Selecciona un video</div>
                <?php if ($totalVideos > 0): ?>
                    <p class="small-note area-videos-meta">Mostrando <?php echo count($videos); ?> de <?php echo $totalVideos; ?> videos (pagina <?php echo $currentPage; ?> de <?php echo $totalPages; ?>).</p>
                <?php endif; ?>

                <div class="video-cards-grid area-video-grid" id="videoCardsGrid" aria-label="Videos disponibles">
                    <?php foreach ($videos as $index => $video): ?>
                        <button
                            class="video-card-item area-video-card"
                            type="button"
                            data-id="<?php echo (int) $video['id']; ?>"
                            data-mime="<?php echo htmlspecialchars((string) ($video['mime_type'] ?? 'video/mp4'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-title="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span class="video-card-badge">Video <?php echo $index + 1; ?></span>
                            <span class="video-card-title"><?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="video-card-meta">Haz clic para reproducir</span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <section id="videoPlayerPanel" class="video-player-panel hidden" aria-live="polite">
                    <div class="player-panel-head">
                        <h3 id="selectedVideoTitle">Reproductor protegido</h3>
                        <p class="small-note">El video se reproduce en streaming protegido. Puedes adelantar o mover la linea de tiempo.</p>
                    </div>

                    <div class="player-wrap" id="playerWrap">
                        <video id="player" data-stream-token="<?php echo htmlspecialchars($streamToken, ENT_QUOTES, 'UTF-8'); ?>" disablePictureInPicture disableRemotePlayback controlsList="nodownload noplaybackrate noremoteplayback" playsinline preload="metadata" draggable="false">
                            <source id="playerSource" src="" type="<?php echo htmlspecialchars($firstVideoMime, ENT_QUOTES, 'UTF-8'); ?>">
                            Tu navegador no soporta video HTML5.
                        </video>
                    </div>

                    <div class="player-controls" aria-label="Controles del reproductor">
                        <button id="playToggle" type="button" class="player-btn">Reproducir</button>
                        <button id="rewindToggle" type="button" class="player-btn player-btn-subtle">-10s</button>
                        <button id="forwardToggle" type="button" class="player-btn player-btn-subtle">+10s</button>
                        <div class="volume-wrap">
                            <label for="volumeControl">Volumen</label>
                            <input id="volumeControl" type="range" min="0" max="1" step="0.05" value="1">
                        </div>
                        <button id="fullscreenToggle" type="button" class="player-btn">Pantalla completa</button>
                    </div>

                    <div class="seek-wrap">
                        <label for="seekControl">Linea de tiempo</label>
                        <input id="seekControl" type="range" min="0" max="1000" step="1" value="0">
                        <div class="seek-times">
                            <span id="seekCurrent">00:00</span>
                            <span id="seekDuration">00:00</span>
                        </div>
                    </div>
                </section>

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
<script src="public/assets/js/portal.js?v=20260507-2"></script>
<script src="public/assets/js/player.js?v=20260507-2"></script>
</body>
</html>
