<?php
// AJAX: Cargar grupos de una competición + parejas libres
// Acción: saas_grupos_cargar

if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_saas_grupos_cargar', 'saas_grupos_cargar');
add_action('wp_ajax_nopriv_saas_grupos_cargar', 'saas_grupos_cargar'); // si no procede, quítalo

function saas_grupos_cargar() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('grp_cargar_', true);

    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    // LOG: entrada cruda
    if (function_exists('str_escribir_log')) {
        str_escribir_log('[START] saas_grupos_cargar POST=' . print_r($_POST, true) . ' | req_id='.$req_id, 'GROUPS:CARGAR');
    }

    // Seguridad
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_escribir_log('[DENY] Nonce inválido | req_id='.$req_id, 'GROUPS:CARGAR');
        return str_groups_json_error(['message' => 'Nonce inválido.']);
    }

    $comp_id = isset($_POST['competicion_id']) ? intval($_POST['competicion_id']) : (isset($_POST['post_id']) ? intval($_POST['post_id']) : 0);
    if (!$comp_id) {
        str_escribir_log('[DENY] competicion_id vacío | req_id='.$req_id, 'GROUPS:CARGAR');
        return str_groups_json_error(['message' => 'ID de competición requerido.']);
    }

    // Query de grupos vinculados a esta competición (CPT: grupo, ACF: torneo_asociado)
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

    // Todas las parejas del torneo (CPT: pareja, ACF: torneo_asociado)
    $parejas_torneo = get_posts([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . $comp_id . '"',
            'compare' => 'LIKE',
        ]]
    ]);

    $parejas_ids_torneo = array_map(function($p){ return intval($p->ID); }, $parejas_torneo);

    // Construir estructura grupos
    $payload_grupos = [];
    $parejas_asignadas = [];

    foreach ($grupos as $g) {
        $nombre   = function_exists('get_field') ? get_field('nombre_grupo', $g->ID) : '';
        $letra    = $nombre ? $nombre : $g->post_title;
        $miembros = function_exists('get_field') ? get_field('participantes_grupo', $g->ID) : [];

        // Normalizar lista de participantes a IDs
        $ids = [];
        if (is_array($miembros)) {
            foreach ($miembros as $item) {
                if (is_object($item) && isset($item->ID)) { $ids[] = intval($item->ID); }
                else { $ids[] = intval($item); }
            }
        } elseif (is_numeric($miembros)) {
            $ids[] = intval($miembros);
        }

        // Mapear a items del frontend
        $items = [];
        foreach ($ids as $pid) {
            $title = get_the_title($pid);
            $items[] = [
                'id'          => $pid,
                'title'       => $title ?: ('Pareja #'.$pid),
                'placeholder' => false,
            ];
            $parejas_asignadas[] = $pid;
        }

        $payload_grupos[] = [
            'id'            => $g->ID,
            'letra'         => $letra,
            'tam'           => max(count($ids), 0),
            'participantes' => $items,
        ];
    }

    // Parejas libres = todas del torneo - asignadas a algún grupo
    $asignadas = array_unique($parejas_asignadas);
    $libres    = array_values(array_diff($parejas_ids_torneo, $asignadas));
    $libres_fmt = array_map(function($pid){
        return [
            'id'    => $pid,
            'title' => get_the_title($pid) ?: ('Pareja #'.$pid),
        ];
    }, $libres);

    $meta = [
        'n_grupos'   => count($payload_grupos),
        'n_parejas'  => count($parejas_ids_torneo),
        'fase_final' => '',    // reservado
        'modo_final' => '',    // reservado
    ];

    $out = [
        'meta'           => $meta,
        'grupos'         => $payload_grupos,
        'parejas_libres' => $libres_fmt,
    ];

    if (function_exists('str_escribir_log')) {
        str_escribir_log('[END] OK grupos='.count($payload_grupos).' libres='.count($libres_fmt).' dur_ms='.round((microtime(true)-$t0)*1000).' | req_id='.$req_id, 'GROUPS:CARGAR');
    }

    return str_groups_json_ok($out);
}

// Helpers JSON (protegidos para no redeclarar si vienen desde manage.php)
if ( ! function_exists('str_groups_json_ok') ) {
    function str_groups_json_ok($data = []) {
        if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
        wp_send_json_success($data);
    }
}

if ( ! function_exists('str_groups_json_error') ) {
    function str_groups_json_error($data = [], $code = 400) {
        if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
        // WP permite enviar código HTTP en el 2º parámetro
        wp_send_json_error($data, $code);
    }
}
