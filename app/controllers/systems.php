<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';

secure_session_start();

$authError = '';
$adminError = '';
$adminSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf()) {
        if ($action === 'systems_logout' || $action === 'exit_to_portal') {
            unset($_SESSION['systems_access']);
            header('Location: index.php');
            exit;
        }

        $authError = 'La sesion expiro. Recarga la pagina e intenta nuevamente.';
        goto render;
    }

    enforce_post_csrf();

    if ($action === 'systems_login') {
        if (throttle_is_limited('systems_login')) {
            $authError = 'Demasiados intentos. Espera unos minutos e intenta de nuevo.';
            goto render;
        }

        $code = trim((string) ($_POST['systems_code'] ?? ''));

        if (validate_systems_access($code)) {
            session_regenerate_id(true);
            $_SESSION['systems_access'] = true;
            throttle_reset('systems_login');
            header('Location: systems.php');
            exit;
        }

        throttle_hit('systems_login');
        $authError = 'Codigo de SISTEMAS incorrecto.';
    }

    if ($action === 'systems_logout') {
        unset($_SESSION['systems_access']);
        header('Location: systems.php');
        exit;
    }

    if ($action === 'exit_to_portal') {
        unset($_SESSION['systems_access']);
        header('Location: index.php');
        exit;
    }

    if ((bool) ($_SESSION['systems_access'] ?? false)) {
        if ($action === 'export_area_keys') {
            $rows = get_areas_for_admin();

            $tableData = [];
            $tableData[] = ['AREA', 'NOMBRE', 'CLAVE_ACCESO', 'ESTADO'];

            foreach ($rows as $row) {
                $areaKey = (string) $row['area_key'];
                $code = get_area_code_secret($areaKey);
                if ($code === '') {
                    $code = 'NO DISPONIBLE (actualiza clave)';
                }

                $tableData[] = [
                    $areaKey,
                    (string) $row['area_label'],
                    $code,
                    (bool) $row['is_active'] ? 'ACTIVA' : 'INACTIVA',
                ];
            }

            // Prefer native XLSX to avoid Excel warning about extension/content mismatch.
            if (class_exists('ZipArchive')) {
                $xlsxPath = tempnam(sys_get_temp_dir(), 'area_keys_');

                if ($xlsxPath !== false) {
                    $zip = new ZipArchive();

                    if ($zip->open($xlsxPath, ZipArchive::OVERWRITE) === true) {
                        $xmlEscape = static function (string $value): string {
                            return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                        };

                        $sheetRowsXml = '';
                        foreach ($tableData as $rIndex => $rowValues) {
                            $rowNumber = $rIndex + 1;
                            $sheetRowsXml .= '<row r="' . $rowNumber . '">';

                            foreach ($rowValues as $cIndex => $value) {
                                $columnLetter = chr(65 + $cIndex);
                                $cellRef = $columnLetter . $rowNumber;
                                $sheetRowsXml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $xmlEscape((string) $value) . '</t></is></c>';
                            }

                            $sheetRowsXml .= '</row>';
                        }

                        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                            . '<Default Extension="xml" ContentType="application/xml"/>'
                            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                            . '</Types>';

                        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                            . '</Relationships>';

                        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                            . '<sheets><sheet name="ClavesAreas" sheetId="1" r:id="rId1"/></sheets>'
                            . '</workbook>';

                        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                            . '</Relationships>';

                        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
                            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
                            . '<borders count="1"><border/></borders>'
                            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
                            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
                            . '</styleSheet>';

                        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                            . '<sheetData>' . $sheetRowsXml . '</sheetData>'
                            . '</worksheet>';

                        $zip->addFromString('[Content_Types].xml', $contentTypes);
                        $zip->addFromString('_rels/.rels', $rels);
                        $zip->addFromString('xl/workbook.xml', $workbook);
                        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
                        $zip->addFromString('xl/styles.xml', $styles);
                        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
                        $zip->close();

                        $filename = 'claves_areas_' . date('Ymd_His') . '.xlsx';
                        header_remove('Content-Security-Policy');
                        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                        header('Pragma: no-cache');
                        header('Expires: 0');
                        header('Content-Length: ' . filesize($xlsxPath));

                        readfile($xlsxPath);
                        @unlink($xlsxPath);
                        exit;
                    }

                    @unlink($xlsxPath);
                }
            }

            // Fallback to CSV if XLSX cannot be built on this server.
            header_remove('Content-Security-Policy');
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="claves_areas_' . date('Ymd_His') . '.csv"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'wb');
            if ($output !== false) {
                fwrite($output, "\xEF\xBB\xBF");
                foreach ($tableData as $line) {
                    fputcsv($output, $line);
                }
                fclose($output);
            }

            exit;
        }

        if ($action === 'create_area') {
            $label = trim((string) ($_POST['new_area_label'] ?? ''));
            $requestedKey = strtolower(trim((string) ($_POST['new_area_key'] ?? '')));
            $code = trim((string) ($_POST['new_area_code'] ?? ''));
            $isActive = isset($_POST['new_area_active']);

            $areaKey = $requestedKey !== '' ? $requestedKey : slugify_area_key($label);

            if ($label === '' || $code === '') {
                $adminError = 'Para crear un area debes ingresar nombre y codigo.';
            } elseif (!preg_match('/^[a-z0-9-]{2,40}$/', $areaKey)) {
                $adminError = 'La clave del area debe usar solo minusculas, numeros o guion (2 a 40 caracteres).';
            } elseif ($areaKey === SYSTEM_AREA_KEY) {
                $adminError = 'La clave systems esta reservada.';
            } else {
                try {
                    $created = create_area($areaKey, $label, $code, $isActive);
                    if ($created) {
                        $folder = get_area_video_path($areaKey);
                        if (!is_dir($folder)) {
                            @mkdir($folder, 0775, true);
                        }
                        $adminSuccess = 'Area creada correctamente.';
                    } else {
                        $adminError = 'No se pudo crear el area.';
                    }
                } catch (Throwable $e) {
                    $adminError = 'No se pudo crear el area. Verifica que la clave no este repetida.';
                }
            }
        }

        if ($action === 'update_area') {
            $areaKey = strtolower(trim((string) ($_POST['area_key'] ?? '')));
            $label = trim((string) ($_POST['area_label'] ?? ''));
            $newCode = trim((string) ($_POST['area_code'] ?? ''));
            $isActive = $areaKey === SYSTEM_AREA_KEY ? true : isset($_POST['area_active']);

            if ($areaKey === '' || $label === '') {
                $adminError = 'Area invalida para actualizar.';
            } elseif ($areaKey === SYSTEM_AREA_KEY && !$isActive) {
                $adminError = 'No puedes desactivar el acceso SISTEMAS.';
            } else {
                try {
                    $updated = update_area($areaKey, $label, $newCode === '' ? null : $newCode, $isActive);
                    if ($updated) {
                        $adminSuccess = 'Area actualizada correctamente.';
                    } else {
                        $adminError = 'No se pudo actualizar el area.';
                    }
                } catch (Throwable $e) {
                    $adminError = 'Error al actualizar el area.';
                }
            }
        }

        if ($action === 'delete_area') {
            $areaKey = strtolower(trim((string) ($_POST['area_key'] ?? '')));

            if ($areaKey === '' || $areaKey === SYSTEM_AREA_KEY) {
                $adminError = 'No se puede eliminar esta area.';
            } elseif (count_videos_for_area($areaKey) > 0) {
                $adminError = 'No puedes eliminar un area que tiene videos registrados.';
            } else {
                try {
                    $deleted = delete_area($areaKey);
                    if ($deleted) {
                        $adminSuccess = 'Area eliminada correctamente.';
                    } else {
                        $adminError = 'No se pudo eliminar el area.';
                    }
                } catch (Throwable $e) {
                    $adminError = 'Error al eliminar el area.';
                }
            }
        }

        if ($action === 'update_video_title') {
            $videoId = (int) ($_POST['video_id'] ?? 0);
            $title = trim((string) ($_POST['video_title'] ?? ''));

            if ($videoId <= 0 || $title === '') {
                $adminError = 'Datos invalidos para actualizar el titulo del video.';
            } elseif (mb_strlen($title) > 255) {
                $adminError = 'El titulo del video no puede exceder 255 caracteres.';
            } else {
                try {
                    $updated = update_video_title($videoId, $title);
                    if ($updated) {
                        $adminSuccess = 'Titulo de video actualizado correctamente.';
                    } else {
                        $adminError = 'No se pudo actualizar el titulo del video.';
                    }
                } catch (Throwable $e) {
                    $adminError = 'Error al actualizar el titulo del video.';
                }
            }
        }

        if ($action === 'delete_video') {
            $videoId = (int) ($_POST['video_id'] ?? 0);

            if ($videoId <= 0) {
                $adminError = 'Video invalido para eliminar.';
            } else {
                try {
                    $video = get_video_by_id_admin($videoId);
                    if (!is_array($video)) {
                        $adminError = 'El video no existe.';
                    } else {
                        $areaKey = (string) $video['area_key'];
                        $storedName = basename((string) $video['stored_name']);
                        $filePath = get_area_video_path($areaKey) . '/' . $storedName;

                        $deleted = delete_video_record($videoId);
                        if ($deleted) {
                            if (is_file($filePath)) {
                                @unlink($filePath);
                            }
                            $adminSuccess = 'Video eliminado correctamente.';
                        } else {
                            $adminError = 'No se pudo eliminar el video.';
                        }
                    }
                } catch (Throwable $e) {
                    $adminError = 'Error al eliminar el video.';
                }
            }
        }
    }
}

render:

$systemsAccess = (bool) ($_SESSION['systems_access'] ?? false);
$allVideos = [];
$videoAreas = [];
$adminAreas = [];

if ($systemsAccess) {
    $videoAreas = get_video_areas(true);
    $adminAreas = get_areas_for_admin();
    $areaKeys = array_map(static function (array $row): string {
        return (string) ($row['area_key'] ?? '');
    }, $adminAreas);
    $allVideos = get_videos_grouped_by_areas($areaKeys);
}

send_security_headers();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SISTEMAS - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Work+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/assets/css/main.css?v=20260317-1">
</head>
<body class="systems-page">
<div class="bg-layer"></div>
<main class="layout">
    <?php if (!$systemsAccess): ?>
        <section class="card login-card">
            <h2>Acceso SISTEMAS</h2>
            <p>Ingresa el codigo de SISTEMAS para administrar videos.</p>
            <?php if ($authError !== ''): ?>
                <div class="alert"><?php echo htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="systems_login">
                <label for="systems_code">Codigo SISTEMAS</label>
                <input id="systems_code" name="systems_code" type="password" required>
                <button type="submit">Ingresar a SISTEMAS</button>
            </form>
            <a class="systems-link" href="index.php">Volver al acceso de videos</a>
        </section>
    <?php else: ?>
        <section class="card dashboard-card systems-dashboard">
            <div class="dashboard-header">
                <div>
                    <p class="chip">Sesion activa</p>
                    <h2>PANEL SISTEMAS</h2>
                </div>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="systems_logout">
                    <button type="submit" class="ghost logout-btn">Cerrar sesion SISTEMAS</button>
                </form>
            </div>

            <?php if ($adminError !== ''): ?>
                <div class="hidden" id="serverToastError" data-toast-kind="error" data-toast-message="<?php echo htmlspecialchars($adminError, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endif; ?>
            <?php if ($adminSuccess !== ''): ?>
                <div class="hidden" id="serverToastSuccess" data-toast-kind="success" data-toast-message="<?php echo htmlspecialchars($adminSuccess, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endif; ?>

            <?php require __DIR__ . '/../views/systems/dashboard.php'; ?>
            <?php require __DIR__ . '/../views/systems/modal-create-area.php'; ?>
            <?php require __DIR__ . '/../views/systems/modal-edit-area.php'; ?>
            <?php require __DIR__ . '/../views/systems/modal-edit-video.php'; ?>
            <?php require __DIR__ . '/../views/systems/modal-upload-video.php'; ?>
            <form method="post" class="inline-exit-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="exit_to_portal">
                <button type="submit" class="systems-link-button">Ir al portal de visualizacion</button>
            </form>
        </section>
    <?php endif; ?>
</main>
<?php if ($systemsAccess): ?>
<div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
<script src="public/assets/js/systems.js"></script>
<?php endif; ?>
</body>
</html>
