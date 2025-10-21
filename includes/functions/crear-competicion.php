<?php
/**
 * Guardado de competiciones (frontend cliente)
 * - Lee los campos del formulario estándar.
 * - (Nuevo) Si llega un snapshot del SIMULADOR, lo persiste en metas
 *   y crea automáticamente los posts "grupo" hijos de la competición.
 */

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Logger de apoyo (escritura en debug-saas-torneos.log)
 */
if (!function_exists('str_escribir_log')) {
    function str_escribir_log($mensaje, $origen = '') {
        $ruta_log = plugin_dir_path(dirname(__FILE__, 2)) . 'debug-saas-torneos.log';
        $timestamp = date('[Y-m-d H:i:s]');
        $origen = $origen ? "[$origen]" : '';
        if (is_array($mensaje) || is_object($mensaje)) {
            $mensaje = print_r($mensaje, true);
        }
        @file_put_contents($ruta_log, $timestamp . " $origen " . $mensaje . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Acción de guardado
 *
 * Importante: este handler se invoca desde admin-post.php con action=str_guardar_competicion
 */
function str_guardar_competicion_cliente() {

    // Seguridad y permisos
    if ( !is_user_logged_in() || ( !current_user_can('cliente') && !current_user_can('administrator') ) ) {
        wp_die('No autorizado');
    }

    if ( !isset($_POST['str_crear_competicion']) ) {
        wp_redirect( home_url('/panel-del-cliente') );
        exit;
    }

    // Sanitizar campos base del formulario
    $titulo      = isset($_POST['titulo']) ? sanitize_text_field($_POST['titulo']) : '';
    $deporte     = isset($_POST['deporte']) ? sanitize_text_field($_POST['deporte']) : '';
    $tipo        = isset($_POST['tipo_competicion']) ? sanitize_text_field($_POST['tipo_competicion']) : 'Torneo';
    $categoria   = isset($_POST['categoria']) ? sanitize_text_field($_POST['categoria']) : '';
    $formato     = isset($_POST['formato_competicion']) ? sanitize_text_field($_POST['formato_competicion']) : '';
    $sistema     = isset($_POST['sistema_puntuacion']) ? sanitize_text_field($_POST['sistema_puntuacion']) : '';
    $n_jugadores = isset($_POST['n_jugadores']) ? intval($_POST['n_jugadores']) : 0;
    $descripcion = isset($_POST['descripcion_competicion']) ? sanitize_textarea_field($_POST['descripcion_competicion']) : '';

    // Crear post de competición
    $post_id = wp_insert_post([
        'post_type'   => 'competicion',
        'post_title'  => $titulo ? $titulo : 'Nueva competición',
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);

    if ( ! $post_id ) {
        wp_die('Error al crear la competición.');
    }

    // Guardado ACF "base"
    if ( function_exists('update_field') ) {
        update_field('deporte', $deporte, $post_id);
        update_field('tipo_competicion', $tipo, $post_id);
        update_field('categoria', $categoria, $post_id);
        update_field('formato_competicion', $formato, $post_id);
        update_field('sistema_puntuacion', $sistema, $post_id);
        update_field('n_jugadores', $n_jugadores, $post_id);
        update_field('descripcion_competicion', $descripcion, $post_id);
    } else {
        // Fallback a metas normales si ACF no estuviera cargado
        update_post_meta($post_id, 'deporte', $deporte);
        update_post_meta($post_id, 'tipo_competicion', $tipo);
        update_post_meta($post_id, 'categoria', $categoria);
        update_post_meta($post_id, 'formato_competicion', $formato);
        update_post_meta($post_id, 'sistema_puntuacion', $sistema);
        update_post_meta($post_id, 'n_jugadores', $n_jugadores);
        update_post_meta($post_id, 'descripcion_competicion', $descripcion);
    }

    // Guardar campos condicionales de Torneo / Ranking (ACF)
    if ( $tipo === 'Torneo' ) {
        $fecha_torneo = isset($_POST['fecha_torneo']) ? sanitize_text_field($_POST['fecha_torneo']) : '';
        $hora_inicio  = isset($_POST['hora_inicio'])  ? sanitize_text_field($_POST['hora_inicio'])  : '';
        $hora_fin     = isset($_POST['hora_fin'])     ? sanitize_text_field($_POST['hora_fin'])     : '';

        if ( function_exists('update_field') ) {
            update_field('tipo_competicion_torneo', [
                'fecha_torneo' => $fecha_torneo,
                'hora_inicio'  => $hora_inicio,
                'hora_fin'     => $hora_fin
            ], $post_id);
        } else {
            update_post_meta($post_id, 'fecha_torneo', $fecha_torneo);
            update_post_meta($post_id, 'hora_inicio', $hora_inicio);
            update_post_meta($post_id, 'hora_fin', $hora_fin);
        }
    }

    if ( $tipo === 'Ranking' ) {
        $fecha_inicio = isset($_POST['fecha_inicio']) ? sanitize_text_field($_POST['fecha_inicio']) : '';
        $fecha_fin    = isset($_POST['fecha_fin'])    ? sanitize_text_field($_POST['fecha_fin'])    : '';

        if ( function_exists('update_field') ) {
            update_field('tipo_competicion_ranking', [
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin'    => $fecha_fin
            ], $post_id);
        } else {
            update_post_meta($post_id, 'fecha_inicio', $fecha_inicio);
            update_post_meta($post_id, 'fecha_fin', $fecha_fin);
        }
    }

    /**
     * ─────────────────────────────────────────────────────────
     * NUEVO: Lectura del snapshot del SIMULADOR y bootstrap
     *        de metas + creación de grupos
     * ─────────────────────────────────────────────────────────
     */
    $snapshot_json = null;

    // Aceptamos varias claves por robustez
    if ( !empty($_POST['sim_snapshot_b64']) ) {
        $decoded = base64_decode( sanitize_text_field($_POST['sim_snapshot_b64']) );
        if ($decoded) { $snapshot_json = $decoded; }
    } elseif ( !empty($_POST['sim_snapshot']) ) {
        $snapshot_json = wp_unslash($_POST['sim_snapshot']); // puede venir JSON puro
    } elseif ( !empty($_POST['sim']) ) {
        // por si llega bajo el mismo nombre que en query
        $tmp = wp_unslash($_POST['sim']);
        // si parece base64, intentamos decodificar
        if ( preg_match('~^[A-Za-z0-9+/=_-]+$~', $tmp) ) {
            $maybe = base64_decode(strtr($tmp, '-_', '+/'));
            $snapshot_json = $maybe ? $maybe : $tmp;
        } else {
            $snapshot_json = $tmp;
        }
    }

    $snapshot = null;
    if ( $snapshot_json ) {
        $snapshot = json_decode($snapshot_json, true);
    }

    // Valores por defecto (por si no llega snapshot)
    $fase_final_slug     = 'final';
    $modo_final          = 'premios_grupo'; // o 'mezclar'
    $n_grupos_sugeridos  = 0;
    $n_parejas_aprox     = ($n_jugadores > 0) ? max(1, intval($n_jugadores / 2)) : 0;

    if ( is_array($snapshot) ) {
        // Intentar mapear claves comunes del simulador
        if ( isset($snapshot['fase_final']) ) {
            $fase_final_slug = sanitize_key($snapshot['fase_final']); // final|semifinal|cuartos|octavos
        } elseif ( isset($snapshot['fase_final_slug']) ) {
            $fase_final_slug = sanitize_key($snapshot['fase_final_slug']);
        }

        if ( isset($snapshot['modo_final']) ) {
            // premios_grupo | mezclar
            $modo_final = ($snapshot['modo_final'] === 'mezclar') ? 'mezclar' : 'premios_grupo';
        } elseif ( isset($snapshot['cuadro_opcion']) ) {
            $modo_final = ($snapshot['cuadro_opcion'] === 'mezclar') ? 'mezclar' : 'premios_grupo';
        }

        if ( isset($snapshot['n_grupos']) ) {
            $n_grupos_sugeridos = intval($snapshot['n_grupos']);
        } elseif ( isset($snapshot['n_grupos_sugeridos']) ) {
            $n_grupos_sugeridos = intval($snapshot['n_grupos_sugeridos']);
        } elseif ( isset($snapshot['grupos']) && is_array($snapshot['grupos']) ) {
            $n_grupos_sugeridos = count($snapshot['grupos']);
        }

        if ( isset($snapshot['n_parejas_calc']) ) {
            $n_parejas_aprox = intval($snapshot['n_parejas_calc']);
        }

        // Guardar snapshot crudo para trazabilidad
        update_post_meta($post_id, 'str_snapshot_sim', wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    }

    // Persistimos metas que la plantilla torneo.php mostrará en el resumen
    update_post_meta($post_id, 'str_fase_final_slug',    $fase_final_slug);
    update_post_meta($post_id, 'str_modo_final',         $modo_final);
    update_post_meta($post_id, 'str_n_grupos_sugeridos', $n_grupos_sugeridos);
    update_post_meta($post_id, 'str_n_parejas_aprox',    $n_parejas_aprox);

    // Crear posts "grupo" si tenemos un número sugerido > 0 y aún no existen
    if ( $n_grupos_sugeridos > 0 ) {

        // ¿Ya hay grupos creados para esta competición?
        $ya_existen = get_posts([
            'post_type'      => 'grupo',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'post_parent'    => $post_id,
            'fields'         => 'ids',
        ]);

        if ( empty($ya_existen) ) {
            $letras = range('A','Z');
            for ($i = 0; $i < $n_grupos_sugeridos; $i++) {
                $letra = isset($letras[$i]) ? $letras[$i] : (string)($i+1);

                $grupo_id = wp_insert_post([
                    'post_type'   => 'grupo',
                    'post_title'  => 'Grupo ' . $letra,
                    'post_status' => 'publish',
                    'post_parent' => $post_id,
                    'post_author' => get_current_user_id(),
                ]);

                if ( $grupo_id ) {
                    // Metas mínimas que usa el JS/AJAX de grupos
                    update_post_meta($grupo_id, 'letra', $letra);
                    update_post_meta($grupo_id, 'orden', ($i + 1));
                    update_post_meta($grupo_id, 'competicion_id', $post_id); // por si algún endpoint usa meta en vez de post_parent
                }
            }
            str_escribir_log("Creación automática de {$n_grupos_sugeridos} grupos para competicion {$post_id}", 'CREAR-COMPETICION');
        } else {
            str_escribir_log("Ya existían grupos; no se crean duplicados para competicion {$post_id}", 'CREAR-COMPETICION');
        }
    } else {
        str_escribir_log("No se creó ningún grupo (n_grupos_sugeridos={$n_grupos_sugeridos}) para competicion {$post_id}", 'CREAR-COMPETICION');
    }

    // Redirección final a la ficha de la competición
    wp_redirect( get_permalink($post_id) );
    exit;
}
add_action('admin_post_str_guardar_competicion', 'str_guardar_competicion_cliente');
