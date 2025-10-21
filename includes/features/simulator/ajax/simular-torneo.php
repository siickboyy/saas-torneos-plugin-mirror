<?php
/**
 * AJAX: Simular Torneo (SIEMPRE por parejas)
 * Ruta: includes/features/simulator/ajax/simular-torneo.php
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Logger básico al archivo debug-saas-torneos.log del plugin.
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
 * Helpers compartidos (protegidos contra redeclare)
 */
if (!function_exists('str_coste_para_grupos')) {
    /**
     * Coste para un nº de grupos dado (heurística)
     */
    function str_coste_para_grupos($P, $G, $target_size, $es_premios_por_grupo, $fase_final, $plazas_por_fase) {
        if ($G < 1) return PHP_INT_MAX;

        $base  = intdiv($P, $G);
        $resto = $P % $G;

        $min_size = $base;
        $max_size = $base + ($resto > 0 ? 1 : 0);

        // Desviación respecto al objetivo
        $dev_min = ($min_size - $target_size);
        $dev_max = ($max_size - $target_size);
        $cost_tamano = ($G - $resto) * ($dev_min * $dev_min) + $resto * ($dev_max * $dev_max);

        // Penalización por incompatibilidad de fase (solo premios por grupo)
        $cost_fase = 0;
        if ($es_premios_por_grupo) {
            $plazas_req = isset($plazas_por_fase[$fase_final]) ? $plazas_por_fase[$fase_final] : 2;
            if ($min_size < $plazas_req) {
                $cost_fase = 100000 * ($plazas_req - $min_size);
            }
        }

        // Penalización por grupos demasiado pequeños
        $cost_tiny = 0;
        if ($min_size < 3) {
            $cost_tiny = 200 * (3 - $min_size);
        }

        // Preferencia ligera por nº de grupos cercanos a 4
        $pref_g = 4;
        $cost_pref_g = 2 * (($G - $pref_g) * ($G - $pref_g));

        return $cost_tamano + $cost_fase + $cost_tiny + $cost_pref_g;
    }
}

if (!function_exists('str_elegir_num_grupos_optimo')) {
    /**
     * Elección del nº de grupos óptimo cuando el cliente no lo fija.
     */
    function str_elegir_num_grupos_optimo($P, $es_premios_por_grupo, $fase_final, $plazas_por_fase) {
        $target_size = 4; // preferencia general
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

if (!function_exists('generar_mensajes_sugerencias')) {
    /**
     * Mensajes educativos / sugerencias informativas
     */
    function generar_mensajes_sugerencias($grupos, $fase_final, $org_val) {
        $total_grupos = count($grupos);
        $k_min = min(array_map(function($g){ return (int)$g['tam']; }, $grupos));
        $partidos_min_por_grupo = max(0, $k_min - 1);

        $modo = (strpos(strtolower($org_val), 'grupo') !== false) ? 'Premios por grupo' : 'Mezclar grupos';
        $fase_txt = ucfirst($fase_final);

        $msg  = '<div class="simulador-msg-sugerencias">';
        $msg .= '<div class="msg-ok">Distribución realizada: <b>' . esc_html($total_grupos) . ' grupo(s)</b> de parejas. ';
        $msg .= 'Partidos garantizados por pareja en el grupo más pequeño: <b>' . esc_html($partidos_min_por_grupo) . '</b>.</div>';
        $msg .= '<div class="msg-warning">Modo de cuadro final: <b>' . esc_html($modo) . '</b> | Fase final: <b>' . esc_html($fase_txt) . '</b>.</div>';
        $msg .= '</div>';

        return $msg;
    }
}

if (!function_exists('render_cuadro_por_grupo')) {
    /**
     * Render: cuadro por grupo
     */
    function render_cuadro_por_grupo($letra_grupo, $fase_final) {
        $slots = [];
        switch ($fase_final) {
            case 'octavos':
                $slots = [
                    ['1º Grupo ' . $letra_grupo, '16º Grupo ' . $letra_grupo],
                    ['8º Grupo ' . $letra_grupo, '9º Grupo ' . $letra_grupo],
                    ['5º Grupo ' . $letra_grupo, '12º Grupo ' . $letra_grupo],
                    ['4º Grupo ' . $letra_grupo, '13º Grupo ' . $letra_grupo],
                    ['6º Grupo ' . $letra_grupo, '11º Grupo ' . $letra_grupo],
                    ['3º Grupo ' . $letra_grupo, '14º Grupo ' . $letra_grupo],
                    ['7º Grupo ' . $letra_grupo, '10º Grupo ' . $letra_grupo],
                    ['2º Grupo ' . $letra_grupo, '15º Grupo ' . $letra_grupo],
                ];
                break;
            case 'cuartos':
                $slots = [
                    ['1º Grupo ' . $letra_grupo, '8º Grupo ' . $letra_grupo],
                    ['4º Grupo ' . $letra_grupo, '5º Grupo ' . $letra_grupo],
                    ['3º Grupo ' . $letra_grupo, '6º Grupo ' . $letra_grupo],
                    ['2º Grupo ' . $letra_grupo, '7º Grupo ' . $letra_grupo],
                ];
                break;
            case 'semifinal':
                $slots = [
                    ['1º Grupo ' . $letra_grupo, '4º Grupo ' . $letra_grupo],
                    ['2º Grupo ' . $letra_grupo, '3º Grupo ' . $letra_grupo],
                ];
                break;
            default: // final
                $slots = [
                    ['1º Grupo ' . $letra_grupo, '2º Grupo ' . $letra_grupo],
                ];
                break;
        }

        $html  = '<div class="simulador-cuadro-ronda">';
        $html .= '<div class="simulador-cuadro-titulo">' . esc_html(ucfirst($fase_final)) . '</div>';
        $html .= '<div class="simulador-cuadro-matches">';
        foreach ($slots as $s) {
            $html .= '<div class="simulador-cuadro-match"><span>' . esc_html($s[0]) . '</span> <b>vs</b> <span>' . esc_html($s[1]) . '</span></div>';
        }
        $html .= '</div></div>';

        if ($fase_final === 'cuartos') {
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Semifinal</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF1</span> <b>vs</b> <span>Ganador QF2</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF3</span> <b>vs</b> <span>Ganador QF4</span></div>';
            $html .= '</div></div>';
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Final</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador SF1</span> <b>vs</b> <span>Ganador SF2</span></div>';
            $html .= '</div></div>';
        } elseif ($fase_final === 'semifinal') {
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Final</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador SF1</span> <b>vs</b> <span>Ganador SF2</span></div>';
            $html .= '</div></div>';
        } elseif ($fase_final === 'octavos') {
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Cuartos</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador R16-1</span> <b>vs</b> <span>Ganador R16-2</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador R16-3</span> <b>vs</b> <span>Ganador R16-4</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador R16-5</span> <b>vs</b> <span>Ganador R16-6</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador R16-7</span> <b>vs</b> <span>Ganador R16-8</span></div>';
            $html .= '</div></div>';
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Semifinal</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF1</span> <b>vs</b> <span>Ganador QF2</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF3</span> <b>vs</b> <span>Ganador QF4</span></div>';
            $html .= '</div></div>';
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Final</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador SF1</span> <b>vs</b> <span>Ganador SF2</span></div>';
            $html .= '</div></div>';
        }

        return $html;
    }
}

if (!function_exists('render_cuadro_mezclado')) {
    /**
     * Render: Cuadro mezclado (simplificado)
     */
    function render_cuadro_mezclado($grupos, $fase_final) {
        $slots = [];
        switch ($fase_final) {
            case 'cuartos':
                $slots = [
                    ['1º Grupo A', '2º Grupo B'],
                    ['1º Grupo C', '2º Grupo D'],
                    ['1º Grupo B', '2º Grupo A'],
                    ['1º Grupo D', '2º Grupo C'],
                ];
                break;
            case 'semifinal':
                $slots = [
                    ['1º Grupo A', '2º Grupo B'],
                    ['1º Grupo C', '2º Grupo D'],
                ];
                break;
            default:
                $slots = [
                    ['1º Grupo A', '1º Grupo B'],
                ];
                break;
        }

        $html  = '<div class="simulador-cuadro-ronda">';
        $html .= '<div class="simulador-cuadro-titulo">' . esc_html(ucfirst($fase_final)) . '</div>';
        $html .= '<div class="simulador-cuadro-matches">';
        foreach ($slots as $s) {
            $html .= '<div class="simulador-cuadro-match"><span>' . esc_html($s[0]) . '</span> <b>vs</b> <span>' . esc_html($s[1]) . '</span></div>';
        }
        $html .= '</div></div>';

        if ($fase_final === 'cuartos') {
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Semifinal</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF1</span> <b>vs</b> <span>Ganador QF2</span></div>';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador QF3</span> <b>vs</b> <span>Ganador QF4</span></div>';
            $html .= '</div></div>';
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Final</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador SF1</span> <b>vs</b> <span>Ganador SF2</span></div>';
            $html .= '</div></div>';
        } elseif ($fase_final === 'semifinal') {
            $html .= '<div class="simulador-cuadro-ronda"><div class="simulador-cuadro-titulo">Final</div><div class="simulador-cuadro-matches">';
            $html .= '<div class="simulador-cuadro-match"><span>Ganador SF1</span> <b>vs</b> <span>Ganador SF2</span></div>';
            $html .= '</div></div>';
        }

        return $html;
    }
}

if (!function_exists('generar_resultados_html')) {
    /**
     * Generación de HTML de resultados (grupos + cuadro final)
     */
    function generar_resultados_html($grupos, $fase_final, $org_val) {
        $es_premios_por_grupo = (strpos(strtolower($org_val), 'grupo') !== false);

        $html = '<div class="simulador-visual-wrap">';

        // Bloque: Grupos (lista)
        $html .= '<div class="simulador-grupos-lista">';
        foreach ($grupos as $g) {
            $html .= '<div class="simulador-grupo-bloque">';
            $html .= '<div class="simulador-grupo-nombre">Grupo ' . esc_html($g['nombre']) . '</div>';
            $html .= '<ul>';
            $top_n = 0;
            switch ($fase_final) {
                case 'octavos':   $top_n = 16; break;
                case 'cuartos':   $top_n = 8;  break;
                case 'semifinal': $top_n = 4;  break;
                default:          $top_n = 2;  break;
            }
            foreach ($g['participantes'] as $idx => $name) {
                $class = ($idx < $top_n) ? ' class="simulador-clasificado"' : '';
                $html .= '<li' . $class . '>' . esc_html($name) . '</li>';
            }
            $garantizados = max(0, $g['tam'] - 1);
            $html .= '</ul><div class="simulador-grupo-info">Partidos garantizados por pareja: ' . esc_html((string)$garantizados) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // .simulador-grupos-lista

        // Bloque: Cuadro(s) final(es)
        $html .= '<div class="simulador-cuadro-final">';
        if ($es_premios_por_grupo) {
            $html .= '<h3>Cuadros finales por grupo</h3>';
            foreach ($grupos as $g) {
                $html .= '<div class="simulador-cuadro-grupo">';
                $html .= '<div class="simulador-cuadro-grupo-titulo">Grupo ' . esc_html($g['nombre']) . '</div>';
                $html .= render_cuadro_por_grupo($g['nombre'], $fase_final);
                $html .= '</div>';
            }
        } else {
            $html .= '<h3>Cuadro final (mezcla de grupos)</h3>';
            $html .= render_cuadro_mezclado($grupos, $fase_final);
        }
        $html .= '</div>'; // .simulador-cuadro-final

        $html .= '</div>'; // .simulador-visual-wrap

        return $html;
    }
}

/**
 * Hooks AJAX
 */
add_action('wp_ajax_str_simular_torneo', 'str_simular_torneo');
// add_action('wp_ajax_nopriv_str_simular_torneo', 'str_simular_torneo'); // si quieres público

/**
 * Handler principal (SIEMPRE por parejas)
 */
if (!function_exists('str_simular_torneo')) {
function str_simular_torneo() {
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

        // Entrada (sin tipo_torneo / sin n_pistas)
        $n_jugadores     = isset($_POST['n_jugadores']) ? absint($_POST['n_jugadores']) : 0;
        $n_grupos_input  = isset($_POST['n_grupos'])    ? absint($_POST['n_grupos'])    : 0;
        $fase_final_raw  = isset($_POST['fase_final'])  ? sanitize_text_field($_POST['fase_final']) : 'final';
        $organizar_final = isset($_POST['organizar_final']) ? sanitize_text_field($_POST['organizar_final']) : 'premios_grupo';

        $fase_final  = strtolower($fase_final_raw);         // 'final' | 'semifinal' | 'cuartos' | 'octavos'
        $org_val     = strtolower($organizar_final);        // 'premios_grupo' | 'mezclar' | ...

        if ($n_jugadores < 2) {
            wp_send_json_error(['msg' => 'Introduce un número de jugadores válido (mínimo 2).'], 400);
        }

        // Participantes competitivos (SIEMPRE por parejas)
        $P = (int) floor($n_jugadores / 2);
        if ($P < 2) {
            wp_send_json_error(['msg' => 'No hay suficientes parejas para simular.'], 400);
        }

        // Plazas por fase
        $plazas_por_fase = [
            'final'     => 2,
            'semifinal' => 4,
            'cuartos'   => 8,
            'octavos'   => 16,
        ];
        $plazas_requeridas = isset($plazas_por_fase[$fase_final]) ? $plazas_por_fase[$fase_final] : 2;

        // Premios por grupo o mezclar
        $es_premios_por_grupo = (strpos($org_val, 'grupo') !== false || $org_val === 'premios' || $org_val === 'premios_grupo');

        // Elegir nº de grupos:
        if ($n_grupos_input > 0) {
            $n_grupos = max(1, $n_grupos_input);
            $modo_grupos = 'fijo_cliente';
        } else {
            $n_grupos = str_elegir_num_grupos_optimo($P, $es_premios_por_grupo, $fase_final, $plazas_por_fase);
            $modo_grupos = 'auto_optimo';
        }

        // Reparto equilibrado base+resto
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

        // Tamaño mínimo por grupo
        $tam_min_grupo = min(array_map(function($g){ return (int) $g['tam']; }, $grupos));

        str_saas_log('SIMULADOR entrada/selección G (parejas)', [
            'P' => $P,
            'fase' => $fase_final,
            'organizar_final' => $org_val,
            'n_grupos_input' => $n_grupos_input,
            'n_grupos_usado' => $n_grupos,
            'modo_grupos' => $modo_grupos,
            'tam_min_grupo' => $tam_min_grupo,
        ]);

        // Si es premios por grupo e incompatible => aviso con CTAs
        if ($es_premios_por_grupo && $plazas_requeridas > $tam_min_grupo) {
            // Fase compatible por tamaño mínimo actual
            $fase_compatible = 'final';
            if ($tam_min_grupo >= 16)      $fase_compatible = 'octavos';
            elseif ($tam_min_grupo >= 8)   $fase_compatible = 'cuartos';
            elseif ($tam_min_grupo >= 4)   $fase_compatible = 'semifinal';

            // Nº de grupos recomendado que haría compatible la fase elegida
            $n_grupos_recomendado = str_recomendar_grupos_compatibles($P, $fase_final, $plazas_por_fase);

            // Mensaje
            $titulo = '<div class="simulador-msg-sugerencias"><div class="msg-warning" style="font-weight:700;margin-bottom:6px;">⚠ No hay suficientes parejas por grupo para la fase final seleccionada.</div>';
            $detalle  = '<div style="margin-bottom:8px;">Has elegido <b>Premios por grupo</b> con la fase final <b>' . esc_html(ucfirst($fase_final)) . '</b>. ';
            $detalle .= 'Con <b>' . esc_html($n_grupos) . ' grupo(s)</b> y un mínimo de <b>' . esc_html($tam_min_grupo) . '</b> pareja(s) en el grupo más pequeño, ';
            $detalle .= 'la fase máxima posible por grupo es <b>' . esc_html(ucfirst($fase_compatible)) . '</b>.</div>';

            $acciones = '<div style="margin-top:10px;">';
            $acciones .= '<div style="margin-bottom:8px;">Para mantener <b>' . esc_html(ucfirst($fase_final)) . '</b> por grupo, cada grupo necesita <b>al menos ' . esc_html($plazas_requeridas) . '</b> parejas.</div>';
            $acciones .= '<div style="display:flex;gap:10px;flex-wrap:wrap;">';

            // Añadir participantes
            $acciones .= '<button type="button" class="str-btn-ajustar-jugadores" data-min-por-grupo="' . esc_attr($plazas_requeridas) . '" data-g-efectivo="' . esc_attr($n_grupos) . '" style="padding:8px 12px;border:1px solid #2152ff;border-radius:8px;background:#f1f6ff;cursor:pointer;">📈 Añadir participantes y simular de nuevo</button>';

            // Cambiar a fase compatible
            $acciones .= '<button type="button" class="str-btn-ajustar-fase" data-fase-compatible="' . esc_attr($fase_compatible) . '" style="padding:8px 12px;border:1px solid #15803d;border-radius:8px;background:#ecfdf5;cursor:pointer;">🔄 Cambiar a ' . esc_html(ucfirst($fase_compatible)) . ' y simular</button>';

            // Ajustar nº de grupos recomendado
            if ($n_grupos_recomendado !== $n_grupos) {
                $acciones .= '<button type="button" class="str-btn-ajustar-grupos" data-grupos-recomendados="' . esc_attr($n_grupos_recomendado) . '" style="padding:8px 12px;border:1px solid #8b5cf6;border-radius:8px;background:#f5f3ff;cursor:pointer;">🧩 Ajustar a ' . esc_html($n_grupos_recomendado) . ' grupo(s) y simular</button>';
            }

            $acciones .= '</div></div></div>';

            $sugerencias_html = $titulo . $detalle . $acciones;

            str_saas_log('SIMULADOR incompatibilidad (premios por grupo)', [
                'fase_elegida' => $fase_final,
                'tam_min_grupo' => $tam_min_grupo,
                'n_grupos_actual' => $n_grupos,
                'n_grupos_recomendado' => $n_grupos_recomendado,
                'fase_compatible' => $fase_compatible,
            ]);

            wp_send_json_success([
                'sugerencias'      => $sugerencias_html,
                'resultados_html'  => '',
                'n_grupos_actual'      => $n_grupos,
                'n_grupos_recomendado' => $n_grupos_recomendado,
            ]);
        }

        // Compatible o modo "mezclar grupos": generar resultados
        if (!function_exists('str_recomendar_grupos_compatibles')) {
            // Garantizamos que existe si se llamara por error aquí (no debería, ya que se usa antes)
            function str_recomendar_grupos_compatibles($P, $fase_final, $plazas_por_fase) {
                $plazas_req = isset($plazas_por_fase[$fase_final]) ? $plazas_por_fase[$fase_final] : 2;
                $max_grupos_compatibles = max(1, (int) floor($P / $plazas_req));
                $target_size = 4; $mejorG = 1; $mejorCoste = PHP_INT_MAX;
                for ($G = 1; $G <= $max_grupos_compatibles; $G++) {
                    $base  = intdiv($P, $G);
                    $resto = $P % $G;
                    $min_size = $base;
                    $max_size = $base + ($resto > 0 ? 1 : 0);
                    $dev_min = ($min_size - $target_size);
                    $dev_max = ($max_size - $target_size);
                    $cost_tamano = ($G - $resto) * ($dev_min * $dev_min) + $resto * ($dev_max * $dev_max);
                    $cost_tiny = ($min_size < 3) ? 200 * (3 - $min_size) : 0;
                    $pref_g = 4; $cost_pref_g = 2 * (($G - $pref_g) * ($G - $pref_g));
                    $coste = $cost_tamano + $cost_tiny + $cost_pref_g;
                    if ($coste < $mejorCoste) { $mejorCoste = $coste; $mejorG = $G; }
                }
                return max(1, (int) $mejorG);
            }
        }

        $sugerencias_html = generar_mensajes_sugerencias($grupos, $fase_final, $org_val);
        $resultados_html  = generar_resultados_html($grupos, $fase_final, $org_val);

        str_saas_log('SIMULADOR generación completada', [
            'n_grupos' => $n_grupos,
            'len_sugerencias' => strlen($sugerencias_html),
            'len_resultados'  => strlen($resultados_html),
        ]);

        wp_send_json_success([
            'sugerencias'     => $sugerencias_html,
            'resultados_html' => $resultados_html,
            'n_grupos_actual' => $n_grupos,
        ]);

    } catch (\Throwable $e) {
        str_saas_log('SIMULADOR excepción', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        wp_send_json_error(['msg' => 'Error interno en la simulación.'], 500);
    }
}}
