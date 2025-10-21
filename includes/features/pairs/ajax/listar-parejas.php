<?php
// Antiguo /includes/ajax/ajax-listar-parejas-multiseleccion.php
// ===========================
// AJAX: Listar parejas confirmadas (multiselección)
// ===========================

if (!defined('ABSPATH')) { exit; }

/** Logger central (opcional) */
if (!function_exists('str_saas_log')) {
    function str_saas_log($message, $context = []) {
        $payload = is_string($message) ? $message : wp_json_encode($message, JSON_UNESCAPED_UNICODE);
        if (!empty($context)) { $payload .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE); }
        if (function_exists('str_escribir_log')) {
            str_escribir_log($payload, 'PAIRS:LISTAR');
            return;
        }
        $log_path = (defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH : plugin_dir_path(__FILE__));
        $log_file = trailingslashit($log_path) . 'debug-saas-torneos.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [PAIRS:LISTAR] ' . $payload . PHP_EOL;
        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    }
}

add_action('wp_ajax_str_listar_parejas_multiseleccion', 'str_ajax_listar_parejas_multiseleccion');
add_action('wp_ajax_nopriv_str_listar_parejas_multiseleccion', 'str_ajax_listar_parejas_multiseleccion');

function str_ajax_listar_parejas_multiseleccion() {
    $t0 = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('req_', true);

    // Nota: este endpoint históricamente permite nopriv; se mantiene tal cual.
    try {
        check_ajax_referer('str_parejas_multiseleccion_nonce', 'nonce');
    } catch (Throwable $e) {
        str_saas_log('[DENY] Nonce inválido', ['req_id'=>$req_id, 'err'=>$e->getMessage()]);
        wp_send_json_error(['message' => 'Nonce inválido.']);
    }

    $torneo_id = isset($_POST['torneo_id']) ? intval($_POST['torneo_id']) : 0;
    $resultado = array();

    str_saas_log('[START] Listar parejas', ['req_id'=>$req_id, 'torneo_id'=>$torneo_id]);

    // Obtener TODAS las parejas publicadas
    $parejas_args = array(
        'post_type'      => 'pareja',
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    );
    $parejas = get_posts($parejas_args);

    foreach ($parejas as $pareja) {
        // 1. Leer campo 'torneo_asociado'
        $torneo_asociado = get_field('torneo_asociado', $pareja->ID);

        // Normalizar: Puede ser ID, array de IDs, objeto WP_Post o array de WP_Post
        $torneos_ids = array();

        if (empty($torneo_asociado)) continue;

        if (is_array($torneo_asociado)) {
            foreach ($torneo_asociado as $item) {
                if (is_object($item) && isset($item->ID)) {
                    $torneos_ids[] = intval($item->ID);
                } else {
                    $torneos_ids[] = intval($item);
                }
            }
        } elseif (is_object($torneo_asociado) && isset($torneo_asociado->ID)) {
            $torneos_ids[] = intval($torneo_asociado->ID);
        } else {
            $torneos_ids[] = intval($torneo_asociado);
        }

        // Si esta pareja NO está asociada a este torneo, saltar
        if (!in_array($torneo_id, $torneos_ids)) continue;

        // 2. Jugador 1 y 2 (normalizar: ID, array, objeto)
        $j1 = get_field('jugador_1', $pareja->ID);
        $j2 = get_field('jugador_2', $pareja->ID);

        $j1_id = null;
        $j2_id = null;

        if (is_array($j1)) {
            $j1_id = is_object($j1[0]) && isset($j1[0]->ID) ? $j1[0]->ID : $j1[0];
        } elseif (is_object($j1) && isset($j1->ID)) {
            $j1_id = $j1->ID;
        } else {
            $j1_id = $j1;
        }

        if (is_array($j2)) {
            $j2_id = is_object($j2[0]) && isset($j2[0]->ID) ? $j2[0]->ID : $j2[0];
        } elseif (is_object($j2) && isset($j2->ID)) {
            $j2_id = $j2->ID;
        } else {
            $j2_id = $j2;
        }

        // 3. Recoger nombre y apellido de cada jugador
        $nombre_1 = $j1_id ? get_field('nombre', $j1_id) : '';
        $apellido_1 = $j1_id ? get_field('apellido', $j1_id) : '';
        $nombre_2 = $j2_id ? get_field('nombre', $j2_id) : '';
        $apellido_2 = $j2_id ? get_field('apellido', $j2_id) : '';

        $resultado[] = array(
            'jugador_1_nombre' => trim($nombre_1 . ' ' . $apellido_1),
            'jugador_2_nombre' => trim($nombre_2 . ' ' . $apellido_2),
        );
    }

    str_saas_log('[END] Listado parejas OK', [
        'req_id'=>$req_id,
        'torneo_id'=>$torneo_id,
        'total'=>count($resultado),
        'dur_ms'=>round((microtime(true)-$t0)*1000)
    ]);

    wp_send_json_success(array('parejas' => $resultado));
}
