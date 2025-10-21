<?php
/**
 * Procesa el registro de una pareja (dos jugadores) en una competición.
 * Ruta: /includes/functions/registro-pareja.php
 */

// Hooks para AJAX de WordPress (logueado y visitante)
add_action('wp_ajax_str_registrar_pareja', 'str_registrar_pareja');
add_action('wp_ajax_nopriv_str_registrar_pareja', 'str_registrar_pareja');

function str_registrar_pareja() {
    // --- LOG: Registra el payload recibido en el debug.log para depuración ---
    error_log('Payload recibido en str_registrar_pareja: ' . print_r($_POST, true));

    // --- Recoger y sanitizar los datos del formulario ---
    // Si se ha seleccionado un jugador existente, recibimos el ID, si es nuevo recibimos los datos completos
    $jugador1_id = isset($_POST['jugador_1_id']) && is_numeric($_POST['jugador_1_id']) ? intval($_POST['jugador_1_id']) : null;
    $jugador2_id = isset($_POST['jugador_2_id']) && is_numeric($_POST['jugador_2_id']) ? intval($_POST['jugador_2_id']) : null;

    $nombre_1    = sanitize_text_field($_POST['nombre_jugador_1'] ?? '');
    $apellido_1  = sanitize_text_field($_POST['apellidos_jugador_1'] ?? '');
    $email_1     = sanitize_email($_POST['email_jugador_1'] ?? '');
    $telefono_1  = sanitize_text_field($_POST['telefono_jugador_1'] ?? '');
    $nombre_2    = sanitize_text_field($_POST['nombre_jugador_2'] ?? '');
    $apellido_2  = sanitize_text_field($_POST['apellidos_jugador_2'] ?? '');
    $email_2     = sanitize_email($_POST['email_jugador_2'] ?? '');
    $telefono_2  = sanitize_text_field($_POST['telefono_jugador_2'] ?? '');
    $deporte     = sanitize_text_field($_POST['deporte'] ?? '');
    $torneo_id   = absint($_POST['torneo_id'] ?? 0);

    // --- Validación básica de campos obligatorios ---
    if (!$deporte || !$torneo_id) {
        wp_send_json_error(['mensaje' => 'Faltan datos obligatorios.']);
    }

    // --- Validación: emails de los dos jugadores no pueden coincidir ---
    if ($email_1 && $email_2 && $email_1 === $email_2) {
        wp_send_json_error(['mensaje' => 'Los dos jugadores deben tener emails distintos.']);
    }

    // --- Lógica para obtener/crear los posts de jugador ---
    // Auxiliar para buscar/crear un jugador solo si no existe
    function str_get_or_create_jugador($nombre, $apellido, $email, $telefono, $deporte) {
        // Busca jugador por email (clave única)
        $args = [
            'post_type' => 'jugador_deportes',
            'meta_query' => [[
                'key' => 'email',
                'value' => $email,
                'compare' => '=',
            ]],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ];
        $q = get_posts($args);
        if ($q && count($q) > 0) {
            // Jugador existente: no se actualiza, solo se recupera el ID
            return $q[0];
        } else {
            // Crear jugador nuevo si no existe ese email
            $jugador_id = wp_insert_post([
                'post_type' => 'jugador_deportes',
                'post_title' => $nombre . ' ' . $apellido,
                'post_status' => 'publish',
            ]);
            if ($jugador_id && !is_wp_error($jugador_id)) {
                update_field('nombre', $nombre, $jugador_id);
                update_field('apellido', $apellido, $jugador_id);
                update_field('email', $email, $jugador_id);
                update_field('telefono', $telefono, $jugador_id);
                update_field('deporte', $deporte, $jugador_id);
                return $jugador_id;
            }
        }
        return false;
    }

    // --- Comprobación/creación de los dos jugadores ---
    // Si se seleccionó un jugador existente (ID numérico), se usa ese ID.
    // Si no, se crea uno nuevo con los datos rellenados
    if (!$jugador1_id) {
        // No se seleccionó existente, crear nuevo
        if (!$nombre_1 || !$apellido_1 || !$email_1) {
            wp_send_json_error(['mensaje' => 'Faltan datos del Jugador 1.']);
        }
        $jugador1_id = str_get_or_create_jugador($nombre_1, $apellido_1, $email_1, $telefono_1, $deporte);
    }
    if (!$jugador2_id) {
        if (!$nombre_2 || !$apellido_2 || !$email_2) {
            wp_send_json_error(['mensaje' => 'Faltan datos del Jugador 2.']);
        }
        $jugador2_id = str_get_or_create_jugador($nombre_2, $apellido_2, $email_2, $telefono_2, $deporte);
    }

    if (!$jugador1_id || !$jugador2_id) {
        wp_send_json_error(['mensaje' => 'No se pudo crear o encontrar a los jugadores.']);
    }
    if ($jugador1_id === $jugador2_id) {
        wp_send_json_error(['mensaje' => 'Debes elegir dos jugadores distintos.']);
    }

    // --- Comprobar si ya existe la pareja para ese torneo (en cualquier orden) ---
    // Esto previene duplicados. Busca una pareja donde:
    // - Ambos jugadores coincidan (jugador1/jugador2 o jugador2/jugador1)
    // - El torneo asociado sea el actual
    $pareja_args = [
        'post_type' => 'pareja',
        'posts_per_page' => 1,
        'meta_query' => [
            'relation' => 'AND',
            [ // Coincidencia de los dos jugadores, sin importar el orden
                'relation' => 'OR',
                [ // Jugador1 en jugador_1 Y jugador2 en jugador_2
                    'relation' => 'AND',
                    [ 'key' => 'jugador_1', 'value' => $jugador1_id, 'compare' => '=' ],
                    [ 'key' => 'jugador_2', 'value' => $jugador2_id, 'compare' => '=' ],
                ],
                [ // Jugador2 en jugador_1 Y jugador1 en jugador_2 (pareja invertida)
                    'relation' => 'AND',
                    [ 'key' => 'jugador_1', 'value' => $jugador2_id, 'compare' => '=' ],
                    [ 'key' => 'jugador_2', 'value' => $jugador1_id, 'compare' => '=' ],
                ],
            ],
            [ // El mismo torneo asociado (comparación exacta, ID puro)
                'key' => 'torneo_asociado',
                'value' => $torneo_id,
                'compare' => '='
            ],
        ],
        'fields' => 'ids',
    ];
    $parejas = get_posts($pareja_args);
    if ($parejas && count($parejas) > 0) {
        // Ya existe una pareja igual para este torneo, devolvemos error
        wp_send_json_error(['mensaje' => 'Ya existe una pareja con estos jugadores en este torneo.']);
    }

    // --- Crear la pareja si todo es correcto ---
    $titulo_pareja = get_the_title($jugador1_id) . ' + ' . get_the_title($jugador2_id);
    $pareja_id = wp_insert_post([
        'post_type' => 'pareja',
        'post_title' => $titulo_pareja,
        'post_status' => 'publish',
    ]);
    if ($pareja_id && !is_wp_error($pareja_id)) {
        // Asociamos campos ACF
        update_field('jugador_1', $jugador1_id, $pareja_id);
        update_field('jugador_2', $jugador2_id, $pareja_id);
        update_field('deporte', $deporte, $pareja_id);
        update_field('torneo_asociado', $torneo_id, $pareja_id);
        wp_send_json_success(['mensaje' => 'Pareja registrada correctamente.']);
    } else {
        wp_send_json_error(['mensaje' => 'No se pudo crear la pareja.']);
    }
}
