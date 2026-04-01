<?php

declare(strict_types=1);

const APP_NAME = 'Videos HAQ';
const SYSTEM_AREA_KEY = 'systems';

const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg'];
const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];
const PRIVATE_VIDEO_DIR = 'D:/VIDEOSHAQ';
const MAX_VIDEO_UPLOAD_BYTES = 10 * 1024 * 1024 * 1024;
const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_ATTEMPT_WINDOW_SECONDS = 300;
const SESSION_IDLE_TIMEOUT_SECONDS = 1800;
const SESSION_REGENERATE_SECONDS = 600;

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'videoshaq';
const DB_USER = 'root';
const DB_PASS = '';
const APP_EXPORT_ENCRYPTION_KEY = 'CHANGE_THIS_KEY_FOR_PRODUCTION_32CHARS_MIN';
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 465;
const SMTP_FROM_EMAIL = 'siscomha@gmail.com';
const SMTP_FROM_NAME = 'Sistemas HAQ';
const SMTP_USERNAME = 'siscomha@gmail.com';
const SMTP_APP_PASSWORD = 'elxh aedz ndnn tvyo';
const ACCESS_REQUEST_TO_EMAIL = 'ignacio.rayo@saludangeles.mx';
const MAIL_QUEUE_DIR = __DIR__ . '/../storage/mail_queue';
const MAIL_QUEUE_PENDING_DIR = MAIL_QUEUE_DIR . '/pending';
const MAIL_QUEUE_PROCESSING_DIR = MAIL_QUEUE_DIR . '/processing';
const MAIL_QUEUE_FAILED_DIR = MAIL_QUEUE_DIR . '/failed';
const MAIL_QUEUE_MAX_RETRIES = 5;
const MAIL_QUEUE_WORKER_TOKEN = 'haq_mail_worker_20260313';

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $now = time();
    $lastActivity = (int) ($_SESSION['__last_activity'] ?? 0);
    if ($lastActivity > 0 && ($now - $lastActivity) > SESSION_IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    $lastRegenerate = (int) ($_SESSION['__last_regenerate'] ?? 0);
    if ($lastRegenerate === 0 || ($now - $lastRegenerate) > SESSION_REGENERATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['__last_regenerate'] = $now;
    }

    $_SESSION['__last_activity'] = $now;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none';");
}

function csrf_token(): string
{
    $token = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): bool
{
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $submitted = (string) ($_POST['csrf_token'] ?? '');

    if ($submitted === '') {
        $submitted = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    return $sessionToken !== '' && $submitted !== '' && hash_equals($sessionToken, $submitted);
}

function enforce_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!verify_csrf()) {
        http_response_code(403);
        exit('CSRF token invalido');
    }
}

function throttle_is_limited(string $key): bool
{
    $bucket = (array) ($_SESSION['login_throttle'][$key] ?? []);
    $first = (int) ($bucket['first'] ?? 0);
    $count = (int) ($bucket['count'] ?? 0);

    if ($first === 0 || (time() - $first) > LOGIN_ATTEMPT_WINDOW_SECONDS) {
        return false;
    }

    return $count >= MAX_LOGIN_ATTEMPTS;
}

function throttle_hit(string $key): void
{
    $bucket = (array) ($_SESSION['login_throttle'][$key] ?? []);
    $first = (int) ($bucket['first'] ?? 0);
    $count = (int) ($bucket['count'] ?? 0);

    if ($first === 0 || (time() - $first) > LOGIN_ATTEMPT_WINDOW_SECONDS) {
        $_SESSION['login_throttle'][$key] = [
            'first' => time(),
            'count' => 1,
        ];
        return;
    }

    $_SESSION['login_throttle'][$key] = [
        'first' => $first,
        'count' => $count + 1,
    ];
}

function throttle_reset(string $key): void
{
    unset($_SESSION['login_throttle'][$key]);
}

function smtp_read_response($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if (!is_string($line)) {
            break;
        }

        $response .= $line;

        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    return $response;
}

function smtp_expect_code($socket, array $expected): bool
{
    $response = smtp_read_response($socket);
    if ($response === '' || strlen($response) < 3) {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return in_array($code, $expected, true);
}

function smtp_send_command($socket, string $command, array $expected): bool
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        return false;
    }

    return smtp_expect_code($socket, $expected);
}

function smtp_send_plain_email(string $toEmail, string $subject, string $body): bool
{
    $socket = @stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, 20);

    $ok = smtp_expect_code($socket, [220])
        && smtp_send_command($socket, 'EHLO localhost', [250])
        && smtp_send_command($socket, 'AUTH LOGIN', [334])
        && smtp_send_command($socket, base64_encode(SMTP_USERNAME), [334])
        && smtp_send_command($socket, base64_encode(str_replace(' ', '', SMTP_APP_PASSWORD)), [235])
        && smtp_send_command($socket, 'MAIL FROM:<' . SMTP_FROM_EMAIL . '>', [250])
        && smtp_send_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251])
        && smtp_send_command($socket, 'DATA', [354]);

    if (!$ok) {
        @fclose($socket);
        return false;
    }

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
    $normalizedBody = str_replace("\n.", "\n..", $normalizedBody);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = [];
    $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $encodedSubject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $normalizedBody) . "\r\n.\r\n";
    $written = fwrite($socket, $payload);

    if ($written === false || !smtp_expect_code($socket, [250])) {
        @fclose($socket);
        return false;
    }

    smtp_send_command($socket, 'QUIT', [221]);
    @fclose($socket);
    return true;
}

function send_access_code_request_email(string $name, string $areaLabel, string $employeeNumber): bool
{
    $subject = 'Solicitud de codigo de acceso - Videos HAQ';
    $body = "Se recibio una nueva solicitud de codigo de acceso.\n\n"
        . 'Nombre: ' . $name . "\n"
        . 'Area: ' . $areaLabel . "\n"
        . 'Numero de empleado: ' . $employeeNumber . "\n"
        . 'Fecha: ' . date('Y-m-d H:i:s') . "\n"
        . 'IP: ' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . "\n";

    return smtp_send_plain_email(ACCESS_REQUEST_TO_EMAIL, $subject, $body);
}

function ensure_mail_queue_directories(): void
{
    $dirs = [MAIL_QUEUE_DIR, MAIL_QUEUE_PENDING_DIR, MAIL_QUEUE_PROCESSING_DIR, MAIL_QUEUE_FAILED_DIR];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

function queue_access_code_request(string $name, string $areaLabel, string $employeeNumber): bool
{
    ensure_mail_queue_directories();

    $payload = [
        'name' => $name,
        'area_label' => $areaLabel,
        'employee_number' => $employeeNumber,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'),
        'requested_at' => date('Y-m-d H:i:s'),
        'attempts' => 0,
    ];

    $filename = MAIL_QUEUE_PENDING_DIR . '/req_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.json';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($json)) {
        return false;
    }

    return file_put_contents($filename, $json, LOCK_EX) !== false;
}

function trigger_mail_queue_worker_async(): void
{
    $hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $host = parse_url('http://' . $hostHeader, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $host = '127.0.0.1';
    }

    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if ($port <= 0) {
        $port = 80;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($isHttps && $port === 80) {
        $port = 443;
    }

    $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    $endpointPath = ($basePath === '' ? '' : $basePath) . '/mail_queue_worker.php?token=' . rawurlencode(MAIL_QUEUE_WORKER_TOKEN);

    $target = ($isHttps ? 'ssl://' : 'tcp://') . $host;
    $socket = @stream_socket_client($target . ':' . $port, $errorNumber, $errorMessage, 1.5, STREAM_CLIENT_CONNECT);

    if (!is_resource($socket)) {
        return;
    }

    stream_set_timeout($socket, 1);

    $request = "GET " . $endpointPath . " HTTP/1.1\r\n"
        . 'Host: ' . $hostHeader . "\r\n"
        . "Connection: Close\r\n\r\n";

    @fwrite($socket, $request);
    @fclose($socket);
}

function process_access_request_mail_queue(int $maxJobs = 5): int
{
    ensure_mail_queue_directories();

    $lockPath = MAIL_QUEUE_DIR . '/worker.lock';
    $lockHandle = @fopen($lockPath, 'c+');

    if (!is_resource($lockHandle)) {
        return 0;
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        return 0;
    }

    $processed = 0;
    $files = glob(MAIL_QUEUE_PENDING_DIR . '/*.json');

    if (!is_array($files)) {
        $files = [];
    }

    sort($files);

    foreach ($files as $filePath) {
        if ($processed >= $maxJobs) {
            break;
        }

        if (!is_string($filePath) || !is_file($filePath)) {
            continue;
        }

        $processingPath = MAIL_QUEUE_PROCESSING_DIR . '/' . basename($filePath);
        if (!@rename($filePath, $processingPath)) {
            continue;
        }

        $content = file_get_contents($processingPath);
        $data = is_string($content) ? json_decode($content, true) : null;

        if (!is_array($data)) {
            @unlink($processingPath);
            continue;
        }

        $name = trim((string) ($data['name'] ?? ''));
        $areaLabel = trim((string) ($data['area_label'] ?? ''));
        $employeeNumber = trim((string) ($data['employee_number'] ?? ''));
        $attempts = (int) ($data['attempts'] ?? 0);

        if ($name === '' || $areaLabel === '' || $employeeNumber === '') {
            @unlink($processingPath);
            continue;
        }

        $sent = send_access_code_request_email($name, $areaLabel, $employeeNumber);

        if ($sent) {
            @unlink($processingPath);
            $processed++;
            continue;
        }

        $attempts++;
        $data['attempts'] = $attempts;
        $updatedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($updatedJson)) {
            @unlink($processingPath);
            continue;
        }

        if ($attempts >= MAIL_QUEUE_MAX_RETRIES) {
            $failedPath = MAIL_QUEUE_FAILED_DIR . '/' . basename($processingPath);
            @file_put_contents($failedPath, $updatedJson, LOCK_EX);
            @unlink($processingPath);
            continue;
        }

        @file_put_contents($processingPath, $updatedJson, LOCK_EX);
        @rename($processingPath, MAIL_QUEUE_PENDING_DIR . '/' . basename($processingPath));
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return $processed;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensure_area_code_secret_table(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS area_code_secrets (
            area_key VARCHAR(30) NOT NULL PRIMARY KEY,
            code_enc TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_area_code_secrets_area_key FOREIGN KEY (area_key)
                REFERENCES area_codes(area_key)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );

    $checked = true;
}

function encryption_key_bytes(): string
{
    return hash('sha256', APP_EXPORT_ENCRYPTION_KEY, true);
}

function encrypt_area_code(string $plain): string
{
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLength);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', encryption_key_bytes(), OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        return '';
    }

    return base64_encode($iv . $cipher);
}

function decrypt_area_code(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false) {
        return '';
    }

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    if (strlen($raw) <= $ivLength) {
        return '';
    }

    $iv = substr($raw, 0, $ivLength);
    $cipher = substr($raw, $ivLength);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', encryption_key_bytes(), OPENSSL_RAW_DATA, $iv);

    return $plain === false ? '' : $plain;
}

function set_area_code_secret(string $areaKey, string $plainCode): void
{
    ensure_area_code_secret_table();

    $encrypted = encrypt_area_code($plainCode);
    if ($encrypted === '') {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO area_code_secrets (area_key, code_enc) VALUES (:area_key, :code_enc)
         ON DUPLICATE KEY UPDATE code_enc = VALUES(code_enc), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'area_key' => $areaKey,
        'code_enc' => $encrypted,
    ]);
}

function get_area_code_secret(string $areaKey): string
{
    ensure_area_code_secret_table();

    $stmt = db()->prepare('SELECT code_enc FROM area_code_secrets WHERE area_key = :area_key LIMIT 1');
    $stmt->execute(['area_key' => $areaKey]);
    $enc = (string) $stmt->fetchColumn();

    if ($enc === '') {
        return '';
    }

    return decrypt_area_code($enc);
}

function remove_area_code_secret(string $areaKey): void
{
    ensure_area_code_secret_table();
    $stmt = db()->prepare('DELETE FROM area_code_secrets WHERE area_key = :area_key LIMIT 1');
    $stmt->execute(['area_key' => $areaKey]);
}

function is_known_area(string $area): bool
{
    if ($area === '' || $area === SYSTEM_AREA_KEY) {
        return false;
    }

    $stmt = db()->prepare('SELECT 1 FROM area_codes WHERE area_key = :area AND is_active = 1 AND area_key <> :systems LIMIT 1');
    $stmt->execute([
        'area' => $area,
        'systems' => SYSTEM_AREA_KEY,
    ]);

    return $stmt->fetchColumn() !== false;
}

function area_exists(string $area): bool
{
    return is_known_area($area);
}

function get_area_label(string $area): string
{
    $stmt = db()->prepare('SELECT area_label FROM area_codes WHERE area_key = :area LIMIT 1');
    $stmt->execute(['area' => $area]);
    $label = $stmt->fetchColumn();

    return is_string($label) && $label !== '' ? $label : 'Area';
}

function get_video_areas(bool $onlyActive = true): array
{
    $sql = 'SELECT area_key, area_label, is_active FROM area_codes WHERE area_key <> :systems';

    if ($onlyActive) {
        $sql .= ' AND is_active = 1';
    }

    $sql .= ' ORDER BY area_label ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute(['systems' => SYSTEM_AREA_KEY]);
    $rows = $stmt->fetchAll();

    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'area_key' => (string) $row['area_key'],
            'area_label' => (string) $row['area_label'],
            'is_active' => (int) $row['is_active'] === 1,
        ];
    }, $rows);
}

function get_areas_for_admin(): array
{
    $stmt = db()->query('SELECT area_key, area_label, is_active FROM area_codes ORDER BY area_key = "systems" DESC, area_label ASC');
    $rows = $stmt->fetchAll();

    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'area_key' => (string) $row['area_key'],
            'area_label' => (string) $row['area_label'],
            'is_active' => (int) $row['is_active'] === 1,
        ];
    }, $rows);
}

function validate_area_access(string $area, string $code): bool
{
    if (!is_known_area($area) || $code === '') {
        return false;
    }

    $stmt = db()->prepare('SELECT code_hash FROM area_codes WHERE area_key = :area LIMIT 1');
    $stmt->execute(['area' => $area]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return false;
    }

    $storedHash = (string) ($row['code_hash'] ?? '');

    if ($storedHash === '') {
        return false;
    }

    if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$argon2') === 0) {
        return password_verify($code, $storedHash);
    }

    return hash_equals(hash('sha256', $code), $storedHash);
}

function validate_systems_access(string $code): bool
{
    if ($code === '') {
        return false;
    }

    $stmt = db()->prepare('SELECT code_hash FROM area_codes WHERE area_key = :area LIMIT 1');
    $stmt->execute(['area' => SYSTEM_AREA_KEY]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return false;
    }

    $storedHash = (string) ($row['code_hash'] ?? '');

    if ($storedHash === '') {
        return false;
    }

    if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$argon2') === 0) {
        return password_verify($code, $storedHash);
    }

    return hash_equals(hash('sha256', $code), $storedHash);
}

function get_area_video_path(string $area): string
{
    return PRIVATE_VIDEO_DIR . '/' . $area;
}

function get_videos_for_area(string $area, ?int $limit = null, int $offset = 0): array
{
    if ($area === '') {
        return [];
    }

    $sql = 'SELECT id, title, stored_name FROM videos WHERE area_key = :area ORDER BY created_at DESC, id DESC';
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = db()->prepare($sql);
    $stmt->bindValue(':area', $area, PDO::PARAM_STR);

    if ($limit !== null && $limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'stored_name' => (string) $row['stored_name'],
        ];
    }, $rows);
}

function get_videos_grouped_by_areas(array $areaKeys): array
{
    $normalized = [];
    foreach ($areaKeys as $areaKey) {
        $key = strtolower(trim((string) $areaKey));
        if ($key === '' || $key === SYSTEM_AREA_KEY) {
            continue;
        }
        $normalized[$key] = [];
    }

    if (count($normalized) === 0) {
        return [];
    }

    $keys = array_keys($normalized);
    $placeholders = [];
    $params = [];

    foreach ($keys as $index => $key) {
        $name = ':k' . $index;
        $placeholders[] = $name;
        $params[$name] = $key;
    }

    $sql = 'SELECT id, area_key, title, stored_name FROM videos WHERE area_key IN (' . implode(', ', $placeholders) . ') ORDER BY area_key ASC, created_at DESC, id DESC';
    $stmt = db()->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return $normalized;
    }

    foreach ($rows as $row) {
        $key = (string) ($row['area_key'] ?? '');
        if (!array_key_exists($key, $normalized)) {
            $normalized[$key] = [];
        }

        $normalized[$key][] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'stored_name' => (string) $row['stored_name'],
        ];
    }

    return $normalized;
}

function slugify_area_key(string $label): string
{
    $value = strtolower(trim($label));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');

    return $value;
}

function create_area(string $areaKey, string $areaLabel, string $code, bool $isActive = true): bool
{
    $stmt = db()->prepare(
        'INSERT INTO area_codes (area_key, area_label, code_hash, is_active) VALUES (:area_key, :area_label, :code_hash, :is_active)'
    );

    $created = $stmt->execute([
        'area_key' => $areaKey,
        'area_label' => $areaLabel,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'is_active' => $isActive ? 1 : 0,
    ]);

    if ($created) {
        set_area_code_secret($areaKey, $code);
    }

    return $created;
}

function update_area(string $areaKey, string $areaLabel, ?string $newCode, bool $isActive): bool
{
    if ($newCode !== null && $newCode !== '') {
        $stmt = db()->prepare(
            'UPDATE area_codes SET area_label = :area_label, code_hash = :code_hash, is_active = :is_active WHERE area_key = :area_key LIMIT 1'
        );

        $updated = $stmt->execute([
            'area_label' => $areaLabel,
            'code_hash' => password_hash($newCode, PASSWORD_DEFAULT),
            'is_active' => $isActive ? 1 : 0,
            'area_key' => $areaKey,
        ]);

        if ($updated) {
            set_area_code_secret($areaKey, $newCode);
        }

        return $updated;
    }

    $stmt = db()->prepare(
        'UPDATE area_codes SET area_label = :area_label, is_active = :is_active WHERE area_key = :area_key LIMIT 1'
    );

    return $stmt->execute([
        'area_label' => $areaLabel,
        'is_active' => $isActive ? 1 : 0,
        'area_key' => $areaKey,
    ]);
}

function delete_area(string $areaKey): bool
{
    remove_area_code_secret($areaKey);

    $stmt = db()->prepare('DELETE FROM area_codes WHERE area_key = :area_key LIMIT 1');

    return $stmt->execute(['area_key' => $areaKey]);
}

function count_videos_for_area(string $areaKey): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM videos WHERE area_key = :area_key');
    $stmt->execute(['area_key' => $areaKey]);

    return (int) $stmt->fetchColumn();
}

function get_video_by_id_for_area(int $videoId, string $area): ?array
{
    if ($videoId <= 0 || !is_known_area($area)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, area_key, title, stored_name FROM videos WHERE id = :id AND area_key = :area LIMIT 1');
    $stmt->execute([
        'id' => $videoId,
        'area' => $area,
    ]);

    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function register_video(array $payload): bool
{
    $stmt = db()->prepare(
        'INSERT INTO videos (area_key, title, original_name, stored_name, relative_path, mime_type, file_size) VALUES (:area, :title, :original_name, :stored_name, :relative_path, :mime_type, :file_size)'
    );

    return $stmt->execute([
        'area' => $payload['area_key'],
        'title' => $payload['title'],
        'original_name' => $payload['original_name'],
        'stored_name' => $payload['stored_name'],
        'relative_path' => $payload['relative_path'],
        'mime_type' => $payload['mime_type'],
        'file_size' => $payload['file_size'],
    ]);
}

function get_video_by_id_admin(int $videoId): ?array
{
    if ($videoId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, area_key, title, stored_name FROM videos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $videoId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function update_video_title(int $videoId, string $title): bool
{
    $stmt = db()->prepare('UPDATE videos SET title = :title WHERE id = :id LIMIT 1');

    return $stmt->execute([
        'title' => $title,
        'id' => $videoId,
    ]);
}

function delete_video_record(int $videoId): bool
{
    $stmt = db()->prepare('DELETE FROM videos WHERE id = :id LIMIT 1');

    return $stmt->execute(['id' => $videoId]);
}
