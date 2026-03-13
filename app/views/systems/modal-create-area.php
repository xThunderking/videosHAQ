<div id="createAreaModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="createAreaTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="createAreaTitle">Crear nueva area</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" class="area-form-grid" autocomplete="off">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="create_area">

            <label for="new_area_label">Nombre del area</label>
            <input id="new_area_label" name="new_area_label" type="text" required>

            <label for="new_area_key">Clave interna (opcional)</label>
            <input id="new_area_key" name="new_area_key" type="text" placeholder="ejemplo: laboratorio">

            <label for="new_area_code">Codigo de acceso</label>
            <input id="new_area_code" name="new_area_code" type="password" required>

            <label class="check-inline"><input type="checkbox" name="new_area_active" checked> Activa para visualizacion</label>
            <button type="submit" class="player-btn">Crear area</button>
        </form>
    </div>
</div>
