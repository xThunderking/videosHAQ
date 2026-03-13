<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_post_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($action === 'login') {
        if (throttle_is_limited('area_login')) {
            $error = 'Demasiados intentos. Espera unos minutos e intenta de nuevo.';
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
    <link rel="stylesheet" href="public/assets/css/main.css">
</head>
<body>
<div class="bg-layer"></div>
<main class="layout">
    <header class="hero">
        <p class="kicker">Plataforma Interna</p>
        <h1>Visualizacion de Videos por Area</h1>
        <p class="subtitle">Hospital Angeles Queretaro: acceso restringido por codigo para visualizacion de videos de capacitacion por area.</p>
    </header>

    <?php if (!$hasAccess): ?>
        <section class="card login-card">
            <h2>Ingresar</h2>
            <p>Selecciona el area e ingresa el codigo de acceso.</p>
            <?php if ($error !== ''): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
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
            <a class="systems-link" href="systems.php">SISTEMAS</a>
        </section>
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
                    <button type="submit" class="ghost">Cerrar sesion</button>
                </form>
            </div>

            <?php if (count($videos) === 0): ?>
                <p class="empty">No hay videos cargados en esta area. Coloca archivos mp4, webm u ogg en la carpeta privada correspondiente.</p>
            <?php else: ?>
                <div class="player-wrap">
                    <video id="player" data-stream-token="<?php echo htmlspecialchars($streamToken, ENT_QUOTES, 'UTF-8'); ?>" disablePictureInPicture playsinline preload="metadata">
                        <source id="playerSource" src="stream.php?id=<?php echo $firstVideoId; ?>&token=<?php echo rawurlencode($streamToken); ?>" type="video/mp4">
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
<script src="public/assets/js/player.js"></script>
</body>
</html>
