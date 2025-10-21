<?php
/**
 * AJAX: Guardar snapshot de simulación (temporal)
 * Ruta: includes/features/simulator/ajax/guardar-simulacion.php
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Logger básico al archivo debug-saas-torneos.log del plugin.
 * (Reutilizamos misma firma)
 */
if (!function_exists('str_saas_log')) {
    function str_saas_log($message, $context = []) {
        $upload_dir = wp_upload_dir();
        $base = dirname(dirname(__DIR__)); // .../includes -> base del plugin
        $log_path = trailingslashit($base) . 'debug-saas-torneos.log';
        if (!is_writable($base)) {
            $log_path = trailingslashit($upload_dir['basedir']) . 'debug-saas-torneos.log';
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($message) ? $message : wp_json_encode($message, JSON_UNESCAPED_UNICODE));
        if (!empty($context)) {
            $line .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;
        @file_put_contents($log_path, $line, FILE_APPEND);
    }
}

/**
 * Helpers (solo si no existen ya)
 */
if (!function_exists('str_coste_para_grupos')) {
    function str_coste_para_grupos($P, $G, $target_size, $es_premios_por_grupo, $fase_final, $plazas_por_fase) {
        if ($G < 1) return PHP_INT_MAX;
        $base  = intdiv($P, $G);
        $resto = $P % $G;

        $min_size = $base;
        $max_size = $base + ($resto > 0 ? 1 : 0);

        $dev_min = ($min_size - $target_size);
        $dev_max = ($max_size - $target_size);
        $cost_tamano = ($G - $resto) * ($dev_min * $dev_min) + $resto * ($dev_max * $dev_max);

        $cost_fase = 0;
        if ($es_premios_por_grupo) {
            $plazas_req = isset($plazas_por_fase[$fase_final]) ? $plazas_por_fase[$fase_final] : 2;
            if ($min_size < $plazas_req) {
                $cost_fase = 100000 * ($plazas_req - $min_size);
            }
        }

        $cost_tiny = 0;
        if ($min_size < 3) {
            $cost_tiny = 200 * (3 - $min_size);
        }

        $pref_g = 4;
        $cost_pref_g = 2 * (($G - $pref_g) * ($G - $pref_g));

        return $cost_tamano + $cost_fase + $cost_tiny + $cost_pref_g;
    }
}
if (!function_exists('str_elegir_num_grupos_optimo')) {
    function str_elegir_num_grupos_optimo($P, $es_premios_por_grupo, $fase_final, $plazas_por_fase) {
        $target_size = 4;
        $max_candidatos = max(1, min(12, $P));
        $mejorG = 1;
        $mejorCoste = PHP_INT_MAX;

        for ($G = 1; $G <= $max_candidatos; $G++) {
            $coste = str_coste_para_grupos($P, $G, $target_size, $es_premios_por_grupo, $fase_final, $plazas_por_fase);
            if ($coste < $mejorCoste) {
                $mejorCoste = $coste;
                $mejorG = $G;
            }
        }
        return max(1, (int) $mejorG);
    }
}

/**
 * Hooks AJAX
 */
add_action('wp_ajax_str_guardar_simulacion', 'str_guardar_simulacion');
// add_action('wp_ajax_nopriv_str_guardar_simulacion', 'str_guardar_simulacion'); // si lo quieres público

/**
 * Handler: guarda snapshot de la simulación en un transient (48h)
 */
if (!function_exists('str_guardar_simulacion')) {
function str_guardar_simulacion() {
    try {
        // Seguridad básica
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'Permisos insuficientes (login requerido).'], 403);
        }
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_ajax_nonce']), 'str_nonce')) {
            wp_send_json_error(['msg' => 'Nonce inválido. Recarga la página.'], 400);
        }
        if (!current_user_can('read')) {
            wp_send_json_error(['msg' => 'Permisos insuficientes.'], 403);
        }

        // Entradas mínimas
        $n_jugadores     = isset($_POST['n_jugadores']) ? absint($_POST['n_jugadores']) : 0;
        $n_grupos_input  = isset($_POST['n_grupos'])    ? absint($_POST['n_grupos'])    : 0;
        $fase_final_raw  = isset($_POST['fase_final'])  ? sanitize_text_field($_POST['fase_final']) : 'final';
        $organizar_final = isset($_POST['organizar_final']) ? sanitize_text_field($_POST['organizar_final']) : 'premios_grupo';

        if ($n_jugadores < 2) {
            wp_send_json_error(['msg' => 'Introduce un número de jugadores válido (mínimo 2).'], 400);
        }

        // Siempre por parejas
        $P = (int) floor($n_jugadores / 2);
        if ($P < 2) {
            wp_send_json_error(['msg' => 'No hay suficientes parejas para guardar la simulación.'], 400);
        }

        // Normalizar fase y modo
        $fase_final = strtolower($fase_final_raw); // 'final' | 'semifinal' | 'cuartos' | 'octavos'
        $org_val    = strtolower($organizar_final);
        $es_premios_por_grupo = (strpos($org_val, 'grupo') !== false || $org_val === 'premios' || $org_val === 'premios_grupo');

        // Plazas por fase
        $plazas_por_fase = [
            'final'     => 2,
            'semifinal' => 4,
            'cuartos'   => 8,
            'octavos'   => 16,
        ];

        // Elegir nº de grupos efectivo
        if ($n_grupos_input > 0) {
            $n_grupos = max(1, $n_grupos_input);
            $modo_grupos = 'fijo_cliente';
        } else {
            $n_grupos = str_elegir_num_grupos_optimo($P, $es_premios_por_grupo, $fase_final, $plazas_por_fase);
            $modo_grupos = 'auto_optimo';
        }

        // Generar grupos equilibrados con placeholders
        $base   = intdiv($P, $n_grupos);
        $resto  = $P % $n_grupos;
        $letras = range('A', 'Z');
        $grupos = [];
        $indice = 1;

        for ($i = 0; $i < $n_grupos; $i++) {
            $tam = $base + ($i < $resto ? 1 : 0);
            $nombre = isset($letras[$i]) ? $letras[$i] : 'G' . ($i + 1);
            $participantes = [];
            for ($p = 0; $p < $tam; $p++) {
                $participantes[] = "Pareja {$indice}";
                $indice++;
            }
            $grupos[] = [
                'nombre' => $nombre,
                'tam' => $tam,
                'participantes' => $participantes,
            ];
        }

        // Metadatos útiles
        $tam_min_grupo = min(array_map(function($g){ return (int)$g['tam']; }, $grupos));
        $partidos_garantizados_min = max(0, $tam_min_grupo - 1);

        // Semilla simple reproducible
        $semilla = wp_generate_uuid4();

        // Snapshot
        $snapshot = [
            'tipo_torneo'          => 'parejas',
            'n_jugadores'          => $n_jugadores,
            'n_parejas_calc'       => $P,
            'n_grupos'             => $n_grupos,
            'fase_final'           => $fase_final,
            'cuadro_opcion'        => $es_premios_por_grupo ? 'premios_grupo' : 'mezclar',
            'grupos'               => $grupos,
            'partidos_garantizados_min' => $partidos_garantizados_min,
            'semilla'              => $semilla,
            'modo_grupos'          => $modo_grupos,
            'user_id'              => get_current_user_id(),
            'created_at'           => current_time('mysql'),
            'status'               => 'draft',
        ];

        // Guardar snapshot en transient (48 horas)
        $sim_id = 's' . wp_generate_password(10, false, false);
        $transient_key = 'str_simulacion_' . $sim_id;

        $json = wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $ok = set_transient($transient_key, $json, 48 * HOUR_IN_SECONDS);

        if (!$ok) {
            // Fallback: opción autoload=no
            $ok_option = update_option($transient_key, $json, false);
            if (!$ok_option) {
                wp_send_json_error(['msg' => 'No se pudo guardar la simulación (almacenamiento temporal).'], 500);
            }
        }

        str_saas_log('SIMULACION guardada (transient)', [
            'simulacion_id' => $sim_id,
            'user_id'       => get_current_user_id(),
            'n_jugadores'   => $n_jugadores,
            'P'             => $P,
            'n_grupos'      => $n_grupos,
            'fase_final'    => $fase_final,
            'modo'          => $snapshot['cuadro_opcion'],
            'tam_min'       => $tam_min_grupo,
        ]);

        wp_send_json_success([
            'simulacion_id' => $sim_id,
            'prefill' => [
                'deporte'         => 'padel',
                'tipo_competicion'=> 'grupos_fase_final',
                'formato'         => 'round_robin',
                'n_jugadores'     => $n_jugadores,
                'n_parejas'       => $P,
                'n_grupos'        => $n_grupos,
                'fase_final'      => $fase_final,
                'modo_final'      => $snapshot['cuadro_opcion'],
            ],
        ]);

    } catch (\Throwable $e) {
        str_saas_log('SIMULACION excepcion', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        wp_send_json_error(['msg' => 'Error interno al guardar la simulación.'], 500);
    }
}}
