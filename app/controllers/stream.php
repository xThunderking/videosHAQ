<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405);
    exit('Metodo no permitido');
}

$area = (string) ($_SESSION['area'] ?? '');
$videoId = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['token'] ?? '');

$sessionToken = (string) ($_SESSION['stream_token'] ?? '');
$sessionTokenExpires = (int) ($_SESSION['stream_token_expires'] ?? 0);
$fetchMode = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
$fetchDest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));

if (!area_exists($area) || $videoId <= 0 || $token === '') {
    http_response_code(403);
    exit('Acceso denegado');
}

if ($sessionToken === '' || $sessionTokenExpires < time() || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    exit('Token invalido');
}

if ($fetchMode === 'navigate' || $fetchDest === 'document') {
    http_response_code(403);
    exit('Navegacion directa no permitida');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    // Release session lock early so logout and other actions are not blocked while streaming.
    session_write_close();
}

$video = get_video_by_id_for_area($videoId, $area);

if (!is_array($video)) {
    http_response_code(404);
    exit('Video no encontrado');
}

$storedName = basename((string) ($video['stored_name'] ?? ''));
$extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));

if (!in_array($extension, ALLOWED_VIDEO_EXTENSIONS, true)) {
    http_response_code(400);
    exit('Formato no permitido');
}

$path = get_area_video_path($area) . '/' . $storedName;

if (!is_file($path)) {
    http_response_code(404);
    exit('Video no encontrado');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('No se pudo leer el archivo');
}

$mime = (string) (mime_content_type($path) ?: 'application/octet-stream');

$start = 0;
$end = $size - 1;
$statusCode = 200;

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches)) {
    $rangeStart = $matches[1] === '' ? 0 : (int) $matches[1];
    $rangeEnd = $matches[2] === '' ? $end : (int) $matches[2];

    if ($rangeStart > $rangeEnd || $rangeStart > $end) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit('Rango no valido');
    }

    if ($rangeEnd > $end) {
        $rangeEnd = $end;
    }

    $start = $rangeStart;
    $end = $rangeEnd;
    $statusCode = 206;
}

$length = $end - $start + 1;

http_response_code($statusCode);
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Content-Disposition: inline; filename="video.' . $extension . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');

if ($statusCode === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fp = fopen($path, 'rb');

if ($fp === false) {
    http_response_code(500);
    exit('No se pudo abrir el video');
}

$buffer = 262144;
$remaining = $length;

fseek($fp, $start);

while (!feof($fp) && $remaining > 0) {
    $readLength = $remaining > $buffer ? $buffer : $remaining;
    $data = fread($fp, $readLength);

    if ($data === false) {
        break;
    }

    if ($data === '') {
        continue;
    }

    echo $data;
    $remaining -= strlen($data);
    if (function_exists('fastcgi_finish_request')) {
        flush();
    } else {
        @ob_flush();
        flush();
    }
}

fclose($fp);
exit;
