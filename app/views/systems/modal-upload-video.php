<div id="uploadVideoModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="uploadVideoTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="uploadVideoTitle">Subir video</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="chunkUploadForm" class="upload-form" autocomplete="off">
            <?php echo csrf_input(); ?>
            <label for="area">Area destino</label>
            <select id="area" name="area" required>
                <option value="">Seleccionar</option>
                <?php foreach ($videoAreas as $areaRow): ?>
                    <option value="<?php echo htmlspecialchars($areaRow['area_key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($areaRow['area_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="title">Titulo (opcional)</label>
            <input id="title" name="title" type="text" placeholder="Si lo dejas vacio se usa el nombre del archivo">

            <label for="video_file">Archivo de video</label>
            <input id="video_file" name="video_file" type="file" accept=".mp4,.webm,.ogg" required>

            <div class="progress-wrap" aria-hidden="true">
                <div id="uploadProgressBar" class="progress-bar"></div>
            </div>
            <p id="uploadProgressText" class="progress-text">Listo para subir.</p>

            <button type="submit">Subir video</button>
        </form>
    </div>
</div>
