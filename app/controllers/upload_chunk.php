<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();
enforce_post_csrf();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (!((bool) ($_SESSION['systems_access'] ?? false))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$area = strtolower(trim((string) ($_POST['area'] ?? '')));
$title = trim((string) ($_POST['title'] ?? ''));
$uploadId = trim((string) ($_POST['upload_id'] ?? ''));
$chunkIndex = (int) ($_POST['chunk_index'] ?? -1);
$totalChunks = (int) ($_POST['total_chunks'] ?? 0);
$totalSize = (int) ($_POST['total_size'] ?? 0);
$originalName = basename((string) ($_POST['original_name'] ?? 'video'));

if (!is_known_area($area)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Area no valida']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/', $uploadId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'upload_id invalido']);
    exit;
}

if ($chunkIndex < 0 || $totalChunks <= 0 || $chunkIndex >= $totalChunks) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Datos de chunk invalidos']);
    exit;
}

if ($totalSize <= 0 || $totalSize > MAX_VIDEO_UPLOAD_BYTES) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Tamano de video no permitido']);
    exit;
}

if (!isset($_FILES['chunk']) || !is_array($_FILES['chunk'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Chunk faltante']);
    exit;
}

$chunkFile = $_FILES['chunk'];

if ((int) ($chunkFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Error al subir chunk']);
    exit;
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, ALLOWED_VIDEO_EXTENSIONS, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Formato no permitido']);
    exit;
}

$chunkBase = PRIVATE_VIDEO_DIR . '/_chunks';
$uploadTmpDir = $chunkBase . '/' . $uploadId;

if (!is_dir($uploadTmpDir) && !mkdir($uploadTmpDir, 0775, true) && !is_dir($uploadTmpDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear carpeta temporal']);
    exit;
}

$chunkPath = $uploadTmpDir . '/' . str_pad((string) $chunkIndex, 8, '0', STR_PAD_LEFT) . '.part';

if (!move_uploaded_file((string) $chunkFile['tmp_name'], $chunkPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo guardar chunk']);
    exit;
}

$receivedChunks = glob($uploadTmpDir . '/*.part');
$receivedCount = is_array($receivedChunks) ? count($receivedChunks) : 0;

if ($receivedCount < $totalChunks) {
    echo json_encode([
        'ok' => true,
        'complete' => false,
        'received' => $receivedCount,
        'total' => $totalChunks,
    ]);
    exit;
}

$targetDir = get_area_video_path($area);

if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear carpeta de destino']);
    exit;
}

$storedName = bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = $targetDir . '/' . $storedName;
$out = fopen($targetPath, 'wb');

if ($out === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear archivo final']);
    exit;
}

$assembleOk = true;

for ($i = 0; $i < $totalChunks; $i++) {
    $part = $uploadTmpDir . '/' . str_pad((string) $i, 8, '0', STR_PAD_LEFT) . '.part';

    if (!is_file($part)) {
        $assembleOk = false;
        break;
    }

    $in = fopen($part, 'rb');

    if ($in === false) {
        $assembleOk = false;
        break;
    }

    while (!feof($in)) {
        $buffer = fread($in, 1048576);

        if ($buffer === false) {
            $assembleOk = false;
            break;
        }

        if ($buffer === '') {
            continue;
        }

        if (fwrite($out, $buffer) === false) {
            $assembleOk = false;
            break;
        }
    }

    fclose($in);

    if (!$assembleOk) {
        break;
    }
}

fclose($out);

if (!$assembleOk) {
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo ensamblar el video']);
    exit;
}

$detectedMime = (string) (mime_content_type($targetPath) ?: 'application/octet-stream');
$size = filesize($targetPath);

if ($size === false) {
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo leer el video final']);
    exit;
}

if ($size > MAX_VIDEO_UPLOAD_BYTES) {
    @unlink($targetPath);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Video excede el tamano maximo permitido']);
    exit;
}

if (!in_array(strtolower($detectedMime), ALLOWED_VIDEO_MIME_TYPES, true)) {
    @unlink($targetPath);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Tipo MIME de video no permitido']);
    exit;
}

$resolvedTitle = $title !== '' ? $title : pathinfo($originalName, PATHINFO_FILENAME);

$saved = register_video([
    'area_key' => $area,
    'title' => $resolvedTitle,
    'original_name' => $originalName,
    'stored_name' => $storedName,
    'relative_path' => 'storage/uploads/private_videos/' . $area . '/' . $storedName,
    'mime_type' => $detectedMime,
    'file_size' => $size,
]);

if (!$saved) {
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo registrar en base de datos']);
    exit;
}

$parts = glob($uploadTmpDir . '/*.part');
if (is_array($parts)) {
    foreach ($parts as $partPath) {
        @unlink($partPath);
    }
}
@rmdir($uploadTmpDir);

echo json_encode([
    'ok' => true,
    'complete' => true,
    'message' => 'Video subido y registrado correctamente',
]);
