<?php
// AJAX: Standings de grupos (provisional: usa clasificación_grupo si existe; si no, lista participantes con ceros)
// Acción: saas_grupos_standings

if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_saas_grupos_standings', 'saas_grupos_standings');
add_action('wp_ajax_nopriv_saas_grupos_standings', 'saas_grupos_standings');

function saas_grupos_standings() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('grp_stand_', true);

    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    if (function_exists('str_escribir_log')) {
        str_escribir_log('[START] POST='.print_r($_POST, true).' | req_id='.$req_id, 'GROUPS:STANDINGS');
    }

    // Seguridad (puede permitir nopriv, pero siempre con nonce)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_escribir_log('[DENY] Nonce inválido | req_id='.$req_id, 'GROUPS:STANDINGS');
        return str_groups_json_error(['message' => 'Nonce inválido.']);
    }

    $comp_id = isset($_POST['competicion_id']) ? intval($_POST['competicion_id']) : 0;
    if (!$comp_id) {
        return str_groups_json_error(['message' => 'ID de competición requerido.']);
    }

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

    $out = ['grupos' => []];

    foreach ($grupos as $g) {
        $nombre = function_exists('get_field') ? get_field('nombre_grupo', $g->ID) : '';
        $letra  = $nombre ? $nombre : $g->post_title;

        $rows = [];

        // Si hay repeater 'clasificacion_grupo', lo usamos
        if (function_exists('have_rows') && have_rows('clasificacion_grupo', $g->ID)) {
            while (have_rows('clasificacion_grupo', $g->ID)) {
                the_row();
                $part = get_sub_field('clas_participante');
                $pos  = intval(get_sub_field('posicion'));
                $pts  = intval(get_sub_field('puntos'));

                // normalizar ID
                if (is_array($part)) { $part = reset($part); }
                $pid = (is_object($part) && isset($part->ID)) ? intval($part->ID) : intval($part);

                $rows[] = [
                    'id'        => $pid,
                    'title'     => get_the_title($pid) ?: ('Participante #'.$pid),
                    'pj'        => 0, 'pg' => 0, 'pp' => 0,
                    'pts'       => $pts,
                    'sets_f'    => 0, 'sets_c' => 0,
                    'juegos_f'  => 0, 'juegos_c'=> 0,
                    'pos'       => $pos,
                ];
            }
            // ordenar por pos asc, luego pts desc
            usort($rows, function($a,$b){
                if ($a['pos'] && $b['pos'] && $a['pos'] !== $b['pos']) return $a['pos'] <=> $b['pos'];
                return $b['pts'] <=> $a['pts'];
            });
        } else {
            // Si no hay clasificacion guardada, listar participantes actuales con valores a 0
            $miembros = function_exists('get_field') ? get_field('participantes_grupo', $g->ID) : [];
            $ids = [];
            if (is_array($miembros)) {
                foreach ($miembros as $item) { $ids[] = is_object($item) && isset($item->ID) ? intval($item->ID) : intval($item); }
            } elseif (is_numeric($miembros)) {
                $ids[] = intval($miembros);
            }
            foreach ($ids as $pid) {
                $rows[] = [
                    'id'       => $pid,
                    'title'    => get_the_title($pid) ?: ('Participante #'.$pid),
                    'pj'       => 0, 'pg'=>0, 'pp'=>0,
                    'pts'      => 0,
                    'sets_f'   => 0, 'sets_c'=>0,
                    'juegos_f' => 0, 'juegos_c'=>0,
                ];
            }
        }

        $out['grupos'][] = [
            'id'    => $g->ID,
            'letra' => $letra,
            'items' => $rows,
        ];
    }

    str_escribir_log('[END] OK grupos='.count($out['grupos']).' dur_ms='.round((microtime(true)-$t0)*1000).' | req_id='.$req_id, 'GROUPS:STANDINGS');
    return str_groups_json_ok($out);
}

// Helpers JSON
if (!function_exists('str_groups_json_ok')) {
    function str_groups_json_ok(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_success($data); }
}
if (!function_exists('str_groups_json_error')) {
    function str_groups_json_error(array $data){ if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); } wp_send_json_error($data); }
}
