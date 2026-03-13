<div class="systems-actions">
    <button type="button" class="player-btn systems-primary" data-open-modal="createAreaModal">Crear area</button>
    <button type="button" class="player-btn systems-secondary" data-open-modal="uploadVideoModal">Subir video</button>
    <form method="post" class="inline-export-form">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="export_area_keys">
        <button type="submit" class="player-btn systems-tertiary">Exportar claves de las areas</button>
    </form>
</div>

<section class="admin-col mt-10">
    <div class="section-head">
        <h3>Areas registradas</h3>
        <p class="section-subtitle">Administra nombre, estado y codigo de cada area.</p>
    </div>
    <div class="areas-table-wrap">
        <table class="areas-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Videos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminAreas as $row): ?>
                    <?php $areaKey = (string) $row['area_key']; ?>
                    <?php $isSystems = $areaKey === SYSTEM_AREA_KEY; ?>
                    <?php $videoCount = $isSystems ? 0 : (isset($allVideos[$areaKey]) ? count($allVideos[$areaKey]) : 0); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['area_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" class="inline-status-form" autocomplete="off">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="action" value="update_area">
                                <input type="hidden" name="area_key" value="<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="area_label" value="<?php echo htmlspecialchars((string) $row['area_label'], ENT_QUOTES, 'UTF-8'); ?>">

                                <label class="status-toggle <?php echo (bool) $row['is_active'] ? 'is-active' : 'is-inactive'; ?>">
                                    <input
                                        type="checkbox"
                                        name="area_active"
                                        class="js-status-toggle"
                                        <?php echo (bool) $row['is_active'] ? 'checked' : ''; ?>
                                        <?php echo $isSystems ? 'disabled' : ''; ?>
                                    >
                                    <span><?php echo (bool) $row['is_active'] ? 'Activa' : 'Inactiva'; ?></span>
                                </label>
                            </form>
                        </td>
                        <td><?php echo $isSystems ? '-' : (string) $videoCount; ?></td>
                        <td>
                            <button
                                type="button"
                                class="ghost action-inline open-edit-area"
                                data-open-modal="editAreaModal"
                                data-area-key="<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>"
                                data-area-label="<?php echo htmlspecialchars((string) $row['area_label'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-area-active="<?php echo (bool) $row['is_active'] ? '1' : '0'; ?>"
                                data-area-systems="<?php echo $isSystems ? '1' : '0'; ?>"
                                data-area-has-videos="<?php echo $videoCount > 0 ? '1' : '0'; ?>"
                            >
                                Editar area
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="list-header">Videos registrados</div>
<div class="video-admin-grid">
    <?php foreach ($adminAreas as $areaRow): ?>
        <?php $key = (string) $areaRow['area_key']; ?>
        <?php if ($key === SYSTEM_AREA_KEY) { continue; } ?>
        <section class="admin-col">
            <h3><?php echo htmlspecialchars((string) $areaRow['area_label'], ENT_QUOTES, 'UTF-8'); ?> <small>(<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>)</small></h3>
            <?php if (!isset($allVideos[$key]) || count($allVideos[$key]) === 0): ?>
                <p class="empty">Sin videos.</p>
            <?php else: ?>
                <ul class="admin-list video-admin-list">
                    <?php foreach ($allVideos[$key] as $video): ?>
                        <li class="video-admin-item">
                            <div class="video-item-head">
                                <span class="video-item-title"><?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <button
                                    type="button"
                                    class="ghost action-inline open-edit-video"
                                    data-open-modal="editVideoModal"
                                    data-video-id="<?php echo (int) $video['id']; ?>"
                                    data-video-title="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-video-area="<?php echo htmlspecialchars((string) $areaRow['area_label'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    Editar
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
