<?php
// Antiguo /includes/ajax/ajax-cargar-form-invitacion-automatica.php

add_action('wp_ajax_str_cargar_form_invitacion_automatica', 'str_cargar_form_invitacion_automatica');
add_action('wp_ajax_nopriv_str_cargar_form_invitacion_automatica', 'str_cargar_form_invitacion_automatica');

function str_cargar_form_invitacion_automatica() {
    ob_start();
    include STR_PLUGIN_PATH . 'includes/forms/form-invitacion-jugador-automatica.php';
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
    exit;
}
