<?php
// AJAX: Rellenar grupos automáticamente con parejas libres
// Acción: saas_grupos_aleatorio

if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_saas_grupos_aleatorio', 'saas_grupos_aleatorio');

function saas_grupos_aleatorio() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('grp_auto_', true);

    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    if (function_exists('str_escribir_log')) {
        str_escribir_log('[START] POST='.print_r($_POST, true).' | req_id='.$req_id, 'GROUPS:ALEATORIO');
    }

    // Seguridad
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_escribir_log('[DENY] Nonce inválido | req_id='.$req_id, 'GROUPS:ALEATORIO');
        return str_groups_json_error(['message' => 'Nonce inválido.']);
    }
    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        str_escribir_log('[DENY] Permisos insuficientes | req_id='.$req_id, 'GROUPS:ALEATORIO');
        return str_groups_json_error(['message' => 'Permisos insuficientes.']);
    }

    $comp_id = isset($_POST['competicion_id']) ? intval($_POST['competicion_id']) : 0;
    if (!$comp_id) {
        return str_groups_json_error(['message' => 'ID de competición requerido.']);
    }

    // Grupos de la competición
    $grupos = get_posts([
        'post_type'      => 'grupo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . $comp_id . '"',
            'compare' => 'LIKE',
        ]]
    ]);
    if (!$grupos) {
        return str_groups_json_error(['message' => 'No hay grupos creados para esta competición.']);
    }

    // Parejas del torneo
    $parejas = get_posts([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . $comp_id . '"',
            'compare' => 'LIKE',
        ]]
    ]);
    $parejas_ids = array_map(function($p){ return intval($p->ID); }, $parejas);

    // Quitar las ya asignadas
    $asignadas = [];
    foreach ($grupos as $g) {
        $miembros = function_exists('get_field') ? get_field('participantes_grupo', $g->ID) : [];
        if (is_array($miembros)) {
            foreach ($miembros as $item) { $asignadas[] = is_object($item)&&isset($item->ID) ? intval($item->ID) : intval($item); }
        } elseif (is_numeric($miembros)) {
            $asignadas[] = intval($miembros);
        }
    }
    $asignadas = array_unique($asignadas);
    $libres    = array_values(array_diff($parejas_ids, $asignadas));

    shuffle($libres);

    // Distribución round-robin simple
    $i = 0;
    foreach ($libres as $pid) {
        $g = $grupos[$i % count($grupos)];
        $miembros = function_exists('get_field') ? get_field('participantes_grupo', $g->ID) : [];
        $ids = [];
        if (is_array($miembros)) {
            foreach ($miembros as $item) { $ids[] = is_object($item)&&isset($item->ID) ? intval($item->ID) : intval($item); }
        } elseif (is_numeric($miembros)) {
            $ids[] = intval($miembros);
        }
        if (!in_array($pid, $ids, true)) { $ids[] = $pid; }
        if (function_exists('update_field')) {
            update_field('participantes_grupo', $ids, $g->ID);
        } else {
            update_post_meta($g->ID, 'participantes_grupo', $ids);
        }
        $i++;
    }

    str_escribir_log('[END] OK auto-llenado libres='.count($libres).' dur_ms='.round((microtime(true)-$t0)*1000).' | req_id='.$req_id, 'GROUPS:ALEATORIO');
    return str_groups_json_ok(['message' => 'Grupos rellenados automáticamente', 'libres_asignados' => count($libres)]);
}

// Helpers JSON
if (!function_exists('str_groups_json_ok')) {
    function str_groups_json_ok(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_success($data); }
}
if (!function_exists('str_groups_json_error')) {
    function str_groups_json_error(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_error($data); }
}
