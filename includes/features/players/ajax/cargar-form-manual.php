<?php
// antiguo /includes/ajax/ajax-cargar-form-invitacion-manual.php

add_action('wp_ajax_str_cargar_form_invitacion_manual', 'str_ajax_cargar_form_invitacion_manual');
add_action('wp_ajax_nopriv_str_cargar_form_invitacion_manual', 'str_ajax_cargar_form_invitacion_manual');

function str_ajax_cargar_form_invitacion_manual() {
    // Sanea y recoge el torneo_id enviado por AJAX (si llega)
    $torneo_id = isset($_POST['torneo_id']) ? intval($_POST['torneo_id']) : 0;

    ob_start();
    ?>
    <!-- Enlaza aquí el CSS personalizado para el formulario manual -->
    <link rel="stylesheet" href="<?php echo esc_url(STR_PLUGIN_URL . 'assets/css/form-invitacion-jugador-manual.css'); ?>">

    <form id="form-invitacion-jugador-manual" autocomplete="off" class="form-invitacion-jugador-manual">
        <div class="form-group">
            <label>Nombre*</label>
            <input type="text" name="nombre" required>
        </div>
        <div class="form-group">
            <label>Apellidos*</label>
            <input type="text" name="apellidos" required>
        </div>
        <div class="form-group">
            <label>Email*</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="telefono">
        </div>
        <input type="hidden" name="torneo_id" id="inv-torneo-id-manual" value="<?php echo esc_attr($torneo_id); ?>">
        <button type="submit" id="btn-enviar-invitacion-jugador-manual" class="btn-enviar-invitacion">Guardar jugador</button>
        <div id="mensaje-invitacion-jugador-manual" style="margin-top:8px;"></div>
    </form>
    <?php

    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
    exit;
}
