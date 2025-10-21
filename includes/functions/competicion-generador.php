<?php
/**
 * Ruta: /saas-torneos-de-raqueta/includes/functions/competicion-generador.php
 * Lógica de generación de estructura de competición desde snapshot.
 */

if (!defined('ABSPATH')) { exit; }

/** Logger básico (usa el mismo que en otros archivos si ya existe) */
if (!function_exists('str_saas_log')) {
    function str_saas_log($message, $context = []) {
        $base = dirname(dirname(__DIR__));
        $log_path = trailingslashit($base) . 'debug-saas-torneos.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($message) ? $message : wp_json_encode($message, JSON_UNESCAPED_UNICODE));
        if (!empty($context)) { $line .= ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE); }
        $line .= PHP_EOL;
        @file_put_contents($log_path, $line, FILE_APPEND);
    }
}

/** Carga snapshot desde transient/option por simulacion_id */
function str_cargar_snapshot_por_id($simulacion_id) {
    $key = 'str_simulacion_' . $simulacion_id;
    $json = get_transient($key);
    if (!$json) { $json = get_option($key, ''); }
    if (!$json) { return null; }
    $data = json_decode($json, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

/** Crea parejas placeholder ligadas a una competición y devuelve array de IDs */
function str_crear_parejas_placeholder($competicion_id, $total_parejas) {
    $ids = [];
    for ($i = 1; $i <= $total_parejas; $i++) {
        $title = 'Pareja ' . $i;
        $post_id = wp_insert_post([
            'post_type'   => 'pareja',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);
        if (is_wp_error($post_id)) {
            str_saas_log('ERROR creando pareja placeholder', ['error' => $post_id->get_error_message(), 'i' => $i]);
            continue;
        }
        update_post_meta($post_id, 'placeholder', 1);
        update_post_meta($post_id, 'competicion_id', $competicion_id);
        $ids[] = $post_id;
    }
    return $ids;
}

/** Crea un post de grupo y devuelve su ID */
function str_crear_grupo($competicion_id, $letra, $orden, $tamano) {
    $post_id = wp_insert_post([
        'post_type'   => 'grupo',
        'post_title'  => 'Grupo ' . $letra,
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);
    if (is_wp_error($post_id)) {
        str_saas_log('ERROR creando grupo', ['letra' => $letra, 'error' => $post_id->get_error_message()]);
        return 0;
    }
    update_post_meta($post_id, 'competicion_id', $competicion_id);
    update_post_meta($post_id, 'letra', $letra);
    update_post_meta($post_id, 'orden', $orden);
    update_post_meta($post_id, 'tam', $tamano);
    return $post_id;
}

/** Genera todos los partidos round-robin dentro de un grupo (con parejas asignadas) */
function str_generar_partidos_grupo($competicion_id, $grupo_id, $parejas_ids) {
    $n = count($parejas_ids);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $p1 = $parejas_ids[$i];
            $p2 = $parejas_ids[$j];
            $pid = wp_insert_post([
                'post_type'   => 'partido',
                'post_title'  => 'Grupo ' . get_post_meta($grupo_id, 'letra', true) . ': ' . get_the_title($p1) . ' vs ' . get_the_title($p2),
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ]);
            if (is_wp_error($pid)) {
                str_saas_log('ERROR creando partido grupo', ['grupo_id' => $grupo_id, 'error' => $pid->get_error_message()]);
                continue;
            }
            // Meta: enlazamos correctamente
            update_post_meta($pid, 'competicion_padel', $competicion_id); // ACF relación a competición
            update_post_meta($pid, 'grupo_id', $grupo_id);
            update_post_meta($pid, 'ronda', 'Grupos'); // coincide con tu ACF "Ronda"
            update_post_meta($pid, 'estado', 'Pendiente');
            // Relaciones a parejas (ACF espera IDs)
            update_post_meta($pid, 'pareja_1', $p1);
            update_post_meta($pid, 'pareja_2', $p2);
        }
    }
}

/** Crea un partido del cuadro con slots (pareja_a/b vacías hasta que se conozcan). Devuelve ID */
function str_crear_partido_bracket($competicion_id, $ronda_label, $slot_a, $slot_b, $orden = 1, $grupo_letra = '') {
    $title = ($grupo_letra ? "Grupo $grupo_letra – " : '') . "$ronda_label: $slot_a vs $slot_b";
    $pid = wp_insert_post([
        'post_type'   => 'partido',
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);
    if (is_wp_error($pid)) {
        str_saas_log('ERROR creando partido bracket', ['slot_a' => $slot_a, 'slot_b' => $slot_b, 'error' => $pid->get_error_message()]);
        return 0;
    }
    update_post_meta($pid, 'competicion_padel', $competicion_id);
    update_post_meta($pid, 'ronda', $ronda_label); // 'Cuartos' | 'Semis' | 'Final'
    update_post_meta($pid, 'estado', 'Pendiente');
    update_post_meta($pid, 'slot_a', $slot_a);
    update_post_meta($pid, 'slot_b', $slot_b);
    update_post_meta($pid, 'orden', intval($orden));
    if ($grupo_letra) { update_post_meta($pid, 'grupo_bracket', $grupo_letra); }
    // Sin pareja_1/pareja_2 todavía (se rellenarán cuando haya clasificación)
    return $pid;
}

/** Genera el bracket (premios por grupo o mezclar) en función de la fase final */
function str_generar_bracket($competicion_id, $modo_final, $fase_final, $grupos_info) {
    // $grupos_info: array [['letra'=>'A','tam'=>4,...], ...]
    $fase_final = strtolower($fase_final); // final|semifinal|cuartos|octavos
    $modo_final = strtolower($modo_final); // premios_grupo|mezclar

    if ($modo_final === 'premios_grupo') {
        // Minibrackets independientes por grupo
        foreach ($grupos_info as $g) {
            $L = $g['letra'];
            switch ($fase_final) {
                case 'final':
                    str_crear_partido_bracket($competicion_id, 'Final', "1º $L", "2º $L", 1, $L);
                    break;
                case 'semifinal':
                    str_crear_partido_bracket($competicion_id, 'Semis', "1º $L", "4º $L", 1, $L);
                    str_crear_partido_bracket($competicion_id, 'Semis', "2º $L", "3º $L", 2, $L);
                    str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1 ($L)", "Ganador S2 ($L)", 3, $L);
                    break;
                case 'cuartos':
                    // Cuartos: 1º vs 4º y 2º vs 3º (dos cruces)
                    str_crear_partido_bracket($competicion_id, 'Cuartos', "1º $L", "4º $L", 1, $L);
                    str_crear_partido_bracket($competicion_id, 'Cuartos', "2º $L", "3º $L", 2, $L);
                    // Semis con Ganadores de Cuartos
                    str_crear_partido_bracket($competicion_id, 'Semis', "Ganador Q1 ($L)", "Ganador Q2 ($L)", 3, $L);
                    // Final
                    str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1 ($L)", "Ganador S2 ($L)", 4, $L);
                    break;
                case 'octavos':
                    // Demasiado grande por grupo en la práctica; dejamos estructura indicativa:
                    for ($i=1; $i<=4; $i++) {
                        str_crear_partido_bracket($competicion_id, 'Octavos', "Seed ".(2*$i-1)." $L", "Seed ".(2*$i)." $L", $i, $L);
                    }
                    // Cuartos, Semis, Final como progresión
                    for ($i=1; $i<=2; $i++) {
                        str_crear_partido_bracket($competicion_id, 'Cuartos', "Ganador O".(2*$i-1)." ($L)", "Ganador O".(2*$i)." ($L)", $i, $L);
                    }
                    str_crear_partido_bracket($competicion_id, 'Semis', "Ganador C1 ($L)", "Ganador C2 ($L)", 1, $L);
                    str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1 ($L)", "Ganador S2 ($L)", 1, $L);
                    break;
            }
        }
        return;
    }

    // MODO: MEZCLAR GRUPOS (bracket único)
    $letras = array_map(function($g){ return $g['letra']; }, $grupos_info);
    $G = count($letras);

    switch ($fase_final) {
        case 'final':
            // Suponiendo 2 plazas totales: 1º de los dos mejores grupos o del ranking general
            // Estructura de slots: genérica
            str_crear_partido_bracket($competicion_id, 'Final', "1º A", (isset($letras[1]) ? "1º " . $letras[1] : "1º B"), 1);
            break;

        case 'semifinal':
            // 4 plazas: cruce 1ºA vs 2ºB y 1ºB vs 2ºA (si G>=2). Con más grupos, el mapeo puede ser A-B y C-D etc.
            if ($G >= 2) {
                str_crear_partido_bracket($competicion_id, 'Semis', "1º {$letras[0]}", "2º {$letras[1]}", 1);
                str_crear_partido_bracket($competicion_id, 'Semis', "1º {$letras[1]}", "2º {$letras[0]}", 2);
            } else {
                // Si sólo hay 1 grupo: 1º vs 2º
                str_crear_partido_bracket($competicion_id, 'Semis', "1º {$letras[0]}", "2º {$letras[0]}", 1);
                str_crear_partido_bracket($competicion_id, 'Semis', "3º {$letras[0]}", "4º {$letras[0]}", 2);
            }
            str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1", "Ganador S2", 3);
            break;

        case 'cuartos':
            // 8 plazas. Con G pares: A vs B, C vs D, etc. Slots típicos:
            // QF1: 1ºA vs 2ºB, QF2: 1ºB vs 2ºA, QF3: 1ºC vs 2ºD, QF4: 1ºD vs 2ºC, ...
            $orden = 1;
            for ($i = 0; $i < $G; $i += 2) {
                $L1 = $letras[$i];
                $L2 = isset($letras[$i+1]) ? $letras[$i+1] : $L1;
                str_crear_partido_bracket($competicion_id, 'Cuartos', "1º $L1", "2º $L2", $orden++);
                str_crear_partido_bracket($competicion_id, 'Cuartos', "1º $L2", "2º $L1", $orden++);
            }
            // Semis y Final (con ganadores ordenados)
            str_crear_partido_bracket($competicion_id, 'Semis', "Ganador Q1", "Ganador Q2", 1);
            str_crear_partido_bracket($competicion_id, 'Semis', "Ganador Q3", "Ganador Q4", 2);
            str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1", "Ganador S2", 3);
            break;

        case 'octavos':
            // 16 plazas, mapeo similar en bloques de 4 grupos. Por brevedad, estructura indicativa:
            for ($i=1; $i<=8; $i++) {
                str_crear_partido_bracket($competicion_id, 'Octavos', "Seed ".(2*$i-1), "Seed ".(2*$i), $i);
            }
            for ($i=1; $i<=4; $i++) {
                str_crear_partido_bracket($competicion_id, 'Cuartos', "Ganador O".(2*$i-1), "Ganador O".(2*$i), $i);
            }
            str_crear_partido_bracket($competicion_id, 'Semis', "Ganador C1", "Ganador C2", 1);
            str_crear_partido_bracket($competicion_id, 'Semis', "Ganador C3", "Ganador C4", 2);
            str_crear_partido_bracket($competicion_id, 'Final', "Ganador S1", "Ganador S2", 3);
            break;
    }
}

/**
 * FUNCIÓN PRINCIPAL: Generar estructura completa desde simulación
 * - Crea parejas placeholder
 * - Crea grupos y asigna parejas por orden
 * - Crea partidos round-robin
 * - Crea bracket por slots (sin asignar parejas aún)
 */
function str_generar_competicion_desde_snapshot($competicion_id, $simulacion_id) {
    $snapshot = str_cargar_snapshot_por_id($simulacion_id);
    if (!is_array($snapshot)) {
        str_saas_log('SNAPSHOT no encontrado', ['simulacion_id' => $simulacion_id]);
        return new WP_Error('snapshot_not_found', 'No se pudo cargar el snapshot de simulación.');
    }

    $P           = intval($snapshot['n_parejas_calc']);
    $n_grupos    = intval($snapshot['n_grupos']);
    $fase_final  = $snapshot['fase_final'];
    $modo_final  = $snapshot['cuadro_opcion']; // 'premios_grupo' | 'mezclar'
    $grupos_snap = $snapshot['grupos'];        // [['nombre'=>'A','tam'=>X,'participantes'=>[...] ]...]

    if ($P < 2 || $n_grupos < 1 || empty($grupos_snap)) {
        return new WP_Error('snapshot_invalido', 'Snapshot de simulación incompleto.');
    }

    // 1) Parejas placeholder
    $parejas_ids = str_crear_parejas_placeholder($competicion_id, $P);
    if (count($parejas_ids) < $P) {
        str_saas_log('WARNING parejas placeholder incompletas', ['esperadas' => $P, 'creadas' => count($parejas_ids)]);
    }

    // 2) Crear grupos y asignar parejas por orden
    $letras = range('A', 'Z');
    $offset = 0;
    $grupos_creados = []; // para bracket
    foreach ($grupos_snap as $idx => $g) {
        $letra = isset($g['nombre']) ? $g['nombre'] : ( $letras[$idx] ?? 'G'.($idx+1) );
        $tam   = isset($g['tam']) ? intval($g['tam']) : 0;

        $grupo_id = str_crear_grupo($competicion_id, $letra, $idx+1, $tam);
        if ($grupo_id <= 0) { continue; }

        // Asignar parejas al grupo (por índice)
        $asignadas = array_slice($parejas_ids, $offset, $tam);
        $offset += $tam;

        // Guardar lista en meta (IDs de parejas en el grupo)
        update_post_meta($grupo_id, 'participantes', $asignadas);

        // 3) Fixtures round-robin de este grupo
        if (count($asignadas) >= 2) {
            str_generar_partidos_grupo($competicion_id, $grupo_id, $asignadas);
        }

        $grupos_creados[] = ['letra' => $letra, 'id' => $grupo_id, 'tam' => $tam];
    }

    // 4) Bracket (slots)
    str_generar_bracket($competicion_id, $modo_final, $fase_final, $grupos_creados);

    // 5) Guardar algunos metas globales para referencia
    update_post_meta($competicion_id, 'str_estructura_generada', 1);
    update_post_meta($competicion_id, 'str_mapa_slots_bracket', wp_json_encode([
        'modo'  => $modo_final,
        'fase'  => $fase_final,
        'grupos'=> array_map(function($g){ return $g['letra']; }, $grupos_creados)
    ], JSON_UNESCAPED_UNICODE));

    str_saas_log('ESTRUCTURA generada', [
        'competicion_id' => $competicion_id,
        'simulacion_id'  => $simulacion_id,
        'parejas'        => count($parejas_ids),
        'grupos'         => count($grupos_creados),
        'fase'           => $fase_final,
        'modo'           => $modo_final
    ]);

    return true;
}
