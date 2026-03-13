<div id="editVideoModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="editVideoTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="editVideoTitle">Editar video</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <p id="editVideoContext" class="small-note"></p>

        <form method="post" id="editVideoForm" class="area-form-grid" autocomplete="off">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_video_title">
            <input type="hidden" name="video_id" id="edit_video_id">

            <label for="edit_video_title_input">Titulo del video</label>
            <input id="edit_video_title_input" name="video_title" type="text" maxlength="255" required>

            <button type="submit" class="player-btn">Guardar cambios</button>
        </form>

        <form method="post" id="deleteVideoForm" class="area-form-grid" autocomplete="off">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="delete_video">
            <input type="hidden" name="video_id" id="delete_video_id">
            <button type="submit" id="deleteVideoButton" class="ghost danger" data-confirm="Esta accion eliminara el video seleccionado. Continuar?">Eliminar video</button>
        </form>
    </div>
</div>
