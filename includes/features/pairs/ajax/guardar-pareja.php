<?php
/**
 * Ruta: wp-content/plugins/saas-torneos-de-raqueta/includes/features/pairs/ajax/guardar-pareja.php
 * Endpoint: guardar pareja (multiselección)
 *
 * Frontend esperado:
 *   action: 'str_guardar_pareja_multiseleccion'  (o alias 'saas_guardar_pareja_multiseleccion')
 *   nonce:  wp_create_nonce('str_parejas_multiseleccion_nonce')  // mismo que LISTAR / BUSCAR
 *   torneo_id, jugador_1, jugador_2
 */

defined('ABSPATH') || exit;

/** Logger simple (usa el global si existe) */
if (!function_exists('str_pairs_log')) {
    function str_pairs_log($mensaje, $tag = 'PAIRS:GUARDAR') {
        $line = '[' . date('Y-m-d H:i:s') . "] [$tag] ";
        if (!is_string($mensaje)) $mensaje = print_r($mensaje, true);
        $line .= $mensaje . PHP_EOL;

        if (function_exists('str_escribir_log')) {
            str_escribir_log($line, $tag);
            return;
        }
        $base = defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH : plugin_dir_path(__FILE__);
        @file_put_contents(trailingslashit($base) . 'debug-saas-torneos.log', $line, FILE_APPEND | LOCK_EX);
    }
}

/** Hooks AJAX (acción principal + alias legacy) */
add_action('wp_ajax_str_guardar_pareja_multiseleccion', 'str_guardar_pareja_multiseleccion');
add_action('wp_ajax_saas_guardar_pareja_multiseleccion', 'str_guardar_pareja_multiseleccion'); // alias por si el front aún manda 'saas_*'
// Nota: NO exponemos nopriv para guardar.

/** Handler principal */
function str_guardar_pareja_multiseleccion() {
    $t0     = microtime(true);
    $req_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('req_', true);

    // Evitar cualquier salida antes del JSON
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    @ob_start();

    // ─────────────────────────────────────────────────────────────────────
    // 1) Seguridad: aceptar el MISMO nonce que LISTAR/BUSCAR
    //    (primario) 'str_parejas_multiseleccion_nonce'
    //    (fallback temporal) 'str_nonce'
    // ─────────────────────────────────────────────────────────────────────
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce'])
           : (isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : '');

    $nonce_ok = false;
    if ($nonce) {
        if (wp_verify_nonce($nonce, 'str_parejas_multiseleccion_nonce')) {
            $nonce_ok = true;
        } elseif (wp_verify_nonce($nonce, 'str_nonce')) {
            // Fallback temporal por compatibilidad
            $nonce_ok = true;
        }
    }
    if (!$nonce_ok) {
        str_pairs_log("[DENY] Nonce inválido o ausente | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'Nonce inválido.']);
    }

    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        str_pairs_log("[DENY] Permisos insuficientes | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'Permisos insuficientes.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2) Datos
    // ─────────────────────────────────────────────────────────────────────
    $torneo_id = isset($_POST['torneo_id']) ? intval($_POST['torneo_id']) : 0;
    $jugador_1 = isset($_POST['jugador_1']) ? intval($_POST['jugador_1']) : 0;
    $jugador_2 = isset($_POST['jugador_2']) ? intval($_POST['jugador_2']) : 0;

    str_pairs_log("[START] POST=" . print_r($_POST, true) . " | req_id={$req_id}");

    if ($torneo_id <= 0 || $jugador_1 <= 0 || $jugador_2 <= 0) {
        str_pairs_log("[ERROR] Datos incompletos | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'Datos incompletos.']);
    }

    if ($jugador_1 === $jugador_2) {
        str_pairs_log("[ERROR] Jugadores idénticos | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'Debes elegir dos jugadores distintos.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3) Evitar duplicados dentro del mismo torneo
    //    (ACF guarda arrays/serializado → LIKE con comillas)
    // ─────────────────────────────────────────────────────────────────────
    $duplicada = new WP_Query([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'torneo_asociado',
                'value'   => '"' . $torneo_id . '"',
                'compare' => 'LIKE',
            ],
            [
                'key'     => 'jugador_1',
                'value'   => '"' . $jugador_1 . '"',
                'compare' => 'LIKE',
            ],
            [
                'key'     => 'jugador_2',
                'value'   => '"' . $jugador_2 . '"',
                'compare' => 'LIKE',
            ],
        ],
    ]);

    if ($duplicada && $duplicada->have_posts()) {
        str_pairs_log("[DENY] Duplicada detectada | torneo={$torneo_id} | j1={$jugador_1} | j2={$jugador_2} | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'Esta pareja ya está registrada en el torneo.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4) Crear post 'pareja'
    // ─────────────────────────────────────────────────────────────────────
    $nombre_1      = get_the_title($jugador_1);
    $nombre_2      = get_the_title($jugador_2);
    $titulo_pareja = trim(($nombre_1 ?: 'Jugador 1') . ' + ' . ($nombre_2 ?: 'Jugador 2'));

    $post_id = wp_insert_post([
        'post_type'   => 'pareja',
        'post_status' => 'publish',
        'post_title'  => $titulo_pareja ?: 'Pareja',
        'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        $err = is_wp_error($post_id) ? $post_id->get_error_message() : 'ID=0';
        str_pairs_log("[ERROR] Fallo creando post pareja: {$err} | req_id={$req_id}");
        return _str_pairs_json_error(['msg' => 'No se pudo crear la pareja.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5) Guardar ACF (o meta) con el MISMO esquema que LISTAR/BUSCAR
    // ─────────────────────────────────────────────────────────────────────
    if (function_exists('update_field')) {
        update_field('jugador_1',      [$jugador_1], $post_id);
        update_field('jugador_2',      [$jugador_2], $post_id);
        update_field('torneo_asociado',[$torneo_id], $post_id);
    } else {
        update_post_meta($post_id, 'jugador_1',       [$jugador_1]);
        update_post_meta($post_id, 'jugador_2',       [$jugador_2]);
        update_post_meta($post_id, 'torneo_asociado', [$torneo_id]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6) OK
    // ─────────────────────────────────────────────────────────────────────
    $dur = round((microtime(true) - $t0) * 1000);
    str_pairs_log("[END] OK | post_id={$post_id} | torneo={$torneo_id} | j1={$jugador_1} | j2={$jugador_2} | dur_ms={$dur} | req_id={$req_id}");

    return _str_pairs_json_ok([
        'msg'        => 'Pareja guardada correctamente',
        'post_id'    => $post_id,
        'jugador_1'  => $jugador_1,
        'jugador_2'  => $jugador_2,
        'torneo_id'  => $torneo_id,
        'titulo'     => $titulo_pareja,
    ]);
}

/** Helpers JSON (limpian buffers antes de responder) */
function _str_pairs_json_ok(array $payload) {
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    wp_send_json_success($payload);
}
function _str_pairs_json_error(array $payload) {
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    wp_send_json_error($payload, 400);
}
