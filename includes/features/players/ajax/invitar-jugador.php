<?php
// Antiguo /includes/ajax/ajax-invitar-jugador.php

// Hook para usuarios logueados (administrador o cliente)
add_action('wp_ajax_str_invitar_jugador', 'str_ajax_invitar_jugador');

function str_ajax_invitar_jugador() {
    if ( !current_user_can('administrator') && !current_user_can('cliente') ) {
        wp_send_json_error(['mensaje' => 'No tienes permisos para invitar jugadores.']);
        exit;
    }

    // Recoger y sanear datos del POST
    $nombre    = isset($_POST['nombre'])    ? sanitize_text_field($_POST['nombre'])     : '';
    $apellidos = isset($_POST['apellidos']) ? sanitize_text_field($_POST['apellidos'])  : '';
    $email     = isset($_POST['email'])     ? sanitize_email($_POST['email'])           : '';
    $telefono  = isset($_POST['telefono'])  ? sanitize_text_field($_POST['telefono'])   : '';
    $asunto    = isset($_POST['asunto'])    ? sanitize_text_field($_POST['asunto'])     : 'Invitación a un torneo';
    $mensaje   = isset($_POST['mensaje'])   ? sanitize_textarea_field($_POST['mensaje']): '';
    $torneo_id = isset($_POST['torneo_id']) ? intval($_POST['torneo_id'])               : 0;

    // Validación básica
    if (!$nombre || !$apellidos || !$email || !$torneo_id || !$asunto) {
        wp_send_json_error(['mensaje' => 'Faltan datos obligatorios.']);
        exit;
    }

    // Comprobamos si el email ya existe como usuario
    $user = get_user_by('email', $email);

    if ($user) {
        $cpt_query = new WP_Query([
            'post_type'  => 'jugador_deportes',
            'meta_query' => [
                [
                    'key'   => 'user_id',
                    'value' => $user->ID,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
        ]);
        if ($cpt_query->have_posts()) {
            $jugador_post = $cpt_query->posts[0];
            $torneos = get_field('torneos_asociados', $jugador_post->ID) ?: [];
            if (!in_array($torneo_id, $torneos)) {
                $torneos[] = $torneo_id;
                update_field('torneos_asociados', $torneos, $jugador_post->ID);
            }
            wp_send_json_success(['mensaje' => 'El jugador ya está registrado. Se ha añadido al torneo.']);
            exit;
        }
        // Si existe usuario pero no CPT, creamos el CPT y lo vinculamos
        $jugador_post_id = wp_insert_post([
            'post_type'   => 'jugador_deportes',
            'post_title'  => $nombre . ' ' . $apellidos,
            'post_status' => 'publish'
        ]);
        update_field('nombre', $nombre, $jugador_post_id);
        update_field('apellido', $apellidos, $jugador_post_id);
        update_field('email', $email, $jugador_post_id);
        update_field('telefono', $telefono, $jugador_post_id);
        update_field('user_id', $user->ID, $jugador_post_id);
        update_field('torneos_asociados', [$torneo_id], $jugador_post_id);
        update_field('estado_invitacion', 'aceptado', $jugador_post_id);
        wp_send_json_success(['mensaje' => 'Usuario ya registrado. CPT jugador creado y añadido al torneo.']);
        exit;
    }

    // Buscar si existe ya como CPT (sin usuario asociado)
    $args = [
        'post_type'  => 'jugador_deportes',
        'meta_query' => [
            [
                'key'     => 'email',
                'value'   => $email,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1,
    ];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $jugador_post = $query->posts[0];

        // 1. --- Genera SIEMPRE un nuevo token único para la invitación de este torneo ---
        $token = wp_generate_password(24, false, false);
        update_field('token_invitacion', $token, $jugador_post->ID);
        update_field('fecha_envio_invitacion', current_time('Y-m-d H:i:s'), $jugador_post->ID);

        // Añadir torneo si no está ya
        $torneos = get_field('torneos_asociados', $jugador_post->ID) ?: [];
        if (!in_array($torneo_id, $torneos)) {
            $torneos[] = $torneo_id;
            update_field('torneos_asociados', $torneos, $jugador_post->ID);
        }

        // --- Enlace único con parámetros: token, id y torneo
        $enlace_registro = site_url('/registro-jugador/?token=' . urlencode($token) . '&id=' . $jugador_post->ID . '&torneo=' . $torneo_id);

        $cuerpo_email = $mensaje . "\n\n";
        $cuerpo_email .= "Haz clic aquí para unirte al torneo: " . $enlace_registro . "\n";
        $cuerpo_email .= "\nSi no reconoces este mensaje, ignóralo.";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $mail_ok = wp_mail($email, $asunto, $cuerpo_email, $headers);

        if ($mail_ok) {
            update_field('estado_invitacion', 'enviado', $jugador_post->ID);
            wp_send_json_success(['mensaje' => 'El jugador ya existía como CPT. Invitación de registro enviada por email.']);
        } else {
            update_field('estado_invitacion', 'fallo', $jugador_post->ID);
            wp_send_json_error(['mensaje' => 'No se pudo enviar el email de invitación. Comprueba el servidor de correo.']);
        }
        exit;
    }

    // Si NO existe, creamos el CPT jugador
    $jugador_post_id = wp_insert_post([
        'post_type'   => 'jugador_deportes',
        'post_title'  => $nombre . ' ' . $apellidos,
        'post_status' => 'publish'
    ]);
    update_field('nombre', $nombre, $jugador_post_id);
    update_field('apellido', $apellidos, $jugador_post_id);
    update_field('email', $email, $jugador_post_id);
    update_field('telefono', $telefono, $jugador_post_id);
    update_field('torneos_asociados', [$torneo_id], $jugador_post_id);

    // --- Token, estado, fecha ---
    $token = wp_generate_password(24, false, false);
    update_field('token_invitacion', $token, $jugador_post_id);
    update_field('fecha_envio_invitacion', current_time('Y-m-d H:i:s'), $jugador_post_id);

    // --- Enlace único con parámetros: token, id y torneo
    $enlace_registro = site_url('/registro-jugador/?token=' . urlencode($token) . '&id=' . $jugador_post_id . '&torneo=' . $torneo_id);

    $cuerpo_email = $mensaje . "\n\n";
    $cuerpo_email .= "Haz clic aquí para unirte al torneo: " . $enlace_registro . "\n";
    $cuerpo_email .= "\nSi no reconoces este mensaje, ignóralo.";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $mail_ok = wp_mail($email, $asunto, $cuerpo_email, $headers);

    if ($mail_ok) {
        update_field('estado_invitacion', 'enviado', $jugador_post_id);
        wp_send_json_success(['mensaje' => 'Jugador añadido y email de invitación enviado.']);
    } else {
        update_field('estado_invitacion', 'fallo', $jugador_post_id);
        wp_send_json_error(['mensaje' => 'No se pudo enviar el email de invitación. Comprueba el servidor de correo.']);
    }
    exit;
}
