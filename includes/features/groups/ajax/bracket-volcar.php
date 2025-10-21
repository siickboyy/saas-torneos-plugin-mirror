<?php
// AJAX: Volcar clasificados a bracket (stub con logs)
// Acción: saas_bracket_volcar

if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_saas_bracket_volcar', 'saas_bracket_volcar');

function saas_bracket_volcar() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('br_volcar_', true);

    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    if (function_exists('str_escribir_log')) {
        str_escribir_log('[START] POST='.print_r($_POST, true).' | req_id='.$req_id, 'GROUPS:BRACKET');
    }

    // Seguridad
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_escribir_log('[DENY] Nonce inválido | req_id='.$req_id, 'GROUPS:BRACKET');
        return str_groups_json_error(['message' => 'Nonce inválido.']);
    }
    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        str_escribir_log('[DENY] Permisos insuficientes | req_id='.$req_id, 'GROUPS:BRACKET');
        return str_groups_json_error(['message' => 'Permisos insuficientes.']);
    }

    // Aún no implementamos la lógica; solo confirmamos la traza
    str_escribir_log('[END] STUB OK dur_ms='.round((microtime(true)-$t0)*1000).' | req_id='.$req_id, 'GROUPS:BRACKET');
    return str_groups_json_ok(['message' => 'Volcado a bracket (stub).']);
}

// Helpers JSON
if (!function_exists('str_groups_json_ok')) {
    function str_groups_json_ok(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_success($data); }
}
if (!function_exists('str_groups_json_error')) {
    function str_groups_json_error(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_error($data); }
}
