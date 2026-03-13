<div id="editAreaModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="editAreaTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="editAreaTitle">Editar area</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="post" id="editAreaForm" class="area-form-grid" autocomplete="off">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update_area">
            <input type="hidden" name="area_key" id="edit_area_key">

            <label>Clave</label>
            <input id="edit_area_key_view" type="text" disabled>

            <label for="edit_area_label">Nombre</label>
            <input id="edit_area_label" name="area_label" type="text" required>

            <label for="edit_area_code">Nuevo codigo (opcional)</label>
            <input id="edit_area_code" name="area_code" type="password" placeholder="Dejar vacio para mantener codigo actual">

            <label class="check-inline">
                <input type="checkbox" id="edit_area_active" name="area_active">
                Activa
            </label>

            <p id="editAreaHint" class="small-note"></p>
            <button type="submit" class="player-btn">Guardar cambios</button>
        </form>

        <form method="post" id="deleteAreaForm" class="area-form-grid" autocomplete="off">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="delete_area">
            <input type="hidden" name="area_key" id="delete_area_key">
            <button type="submit" id="deleteAreaButton" class="ghost danger" data-confirm="Esta accion eliminara el area si no tiene videos. Continuar?">Eliminar area</button>
        </form>
    </div>
</div>
