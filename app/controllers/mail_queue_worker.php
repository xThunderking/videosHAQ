<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

$token = (string) ($_GET['token'] ?? '');
if ($token === '' || !hash_equals(MAIL_QUEUE_WORKER_TOKEN, $token)) {
    http_response_code(403);
    exit('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Metodo no permitido');
}

$processed = process_access_request_mail_queue(8);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode([
    'ok' => true,
    'processed' => $processed,
]);
