<?php
// AJAX: Asignar una pareja a un grupo
// Acción: saas_grupo_asignar_pareja

if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_saas_grupo_asignar_pareja', 'saas_grupo_asignar_pareja');

function saas_grupo_asignar_pareja() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('grp_asignar_', true);

    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    if (function_exists('str_escribir_log')) {
        str_escribir_log('[START] POST='.print_r($_POST, true).' | req_id='.$req_id, 'GROUPS:ASIGNAR');
    }

    // Seguridad
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_escribir_log('[DENY] Nonce inválido | req_id='.$req_id, 'GROUPS:ASIGNAR');
        return str_groups_json_error(['message' => 'Nonce inválido.']);
    }
    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        str_escribir_log('[DENY] Permisos insuficientes | req_id='.$req_id, 'GROUPS:ASIGNAR');
        return str_groups_json_error(['message' => 'Permisos insuficientes.']);
    }

    $comp_id  = isset($_POST['competicion_id']) ? intval($_POST['competicion_id']) : 0;
    $grupo_id = isset($_POST['grupo_id']) ? intval($_POST['grupo_id']) : 0;
    $pareja_id = isset($_POST['pareja_id']) ? intval($_POST['pareja_id']) : 0;

    if (!$comp_id || !$grupo_id || !$pareja_id) {
        str_escribir_log('[ERROR] Datos incompletos | req_id='.$req_id, 'GROUPS:ASIGNAR');
        return str_groups_json_error(['message' => 'Datos incompletos.']);
    }

    // Validar que el grupo pertenece a la competición
    $torneo_g = function_exists('get_field') ? get_field('torneo_asociado', $grupo_id) : 0;
    $ok_comp  = false;
    if (is_array($torneo_g)) {
        foreach ($torneo_g as $item) {
            $ok_comp = $ok_comp || ( (is_object($item) && isset($item->ID) ? intval($item->ID) : intval($item)) === $comp_id );
        }
    } else {
        $ok_comp = (intval($torneo_g) === $comp_id);
    }
    if (!$ok_comp) {
        str_escribir_log('[DENY] Grupo no pertenece a comp='.$comp_id.' | req_id='.$req_id, 'GROUPS:ASIGNAR');
        return str_groups_json_error(['message' => 'Grupo no pertenece a la competición.']);
    }

    // Obtener participantes actuales
    $miembros = function_exists('get_field') ? get_field('participantes_grupo', $grupo_id) : [];
    $ids = [];
    if (is_array($miembros)) {
        foreach ($miembros as $item) { $ids[] = is_object($item) && isset($item->ID) ? intval($item->ID) : intval($item); }
    } elseif (is_numeric($miembros)) {
        $ids[] = intval($miembros);
    }

    // Evitar duplicados
    if (in_array($pareja_id, $ids, true)) {
        str_escribir_log('[END] Ya estaba asignada | pareja='.$pareja_id.' grupo='.$grupo_id.' | req_id='.$req_id, 'GROUPS:ASIGNAR');
        return str_groups_json_ok(['message' => 'Pareja ya estaba en el grupo.']);
    }

    $ids[] = $pareja_id;
    if (function_exists('update_field')) {
        update_field('participantes_grupo', $ids, $grupo_id);
    } else {
        update_post_meta($grupo_id, 'participantes_grupo', $ids);
    }

    str_escribir_log('[END] OK pareja='.$pareja_id.' grupo='.$grupo_id.' dur_ms='.round((microtime(true)-$t0)*1000).' | req_id='.$req_id, 'GROUPS:ASIGNAR');
    return str_groups_json_ok(['message' => 'Asignado']);
}

// Helpers JSON (si no están ya cargados por otro archivo)
if (!function_exists('str_groups_json_ok')) {
    function str_groups_json_ok(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_success($data); }
}
if (!function_exists('str_groups_json_error')) {
    function str_groups_json_error(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_error($data); }
}
