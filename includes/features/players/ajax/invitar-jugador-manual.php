<?php
// Antiguo /includes/ajax/ajax-invitar-jugador-manual.php

add_action('wp_ajax_str_invitar_jugador_manual', 'str_ajax_invitar_jugador_manual');

function str_ajax_invitar_jugador_manual() {
    if ( !current_user_can('administrator') && !current_user_can('cliente') ) {
        wp_send_json_error(['mensaje' => 'No tienes permisos para añadir jugadores.']);
        exit;
    }

    $nombre    = isset($_POST['nombre'])    ? sanitize_text_field($_POST['nombre'])     : '';
    $apellidos = isset($_POST['apellidos']) ? sanitize_text_field($_POST['apellidos'])  : '';
    $email     = isset($_POST['email'])     ? sanitize_email($_POST['email'])           : '';
    $telefono  = isset($_POST['telefono'])  ? sanitize_text_field($_POST['telefono'])   : '';
    $torneo_id = isset($_POST['torneo_id']) ? intval($_POST['torneo_id'])               : 0;

    if (!$nombre || !$apellidos || !$email || !$torneo_id) {
        wp_send_json_error(['mensaje' => 'Faltan datos obligatorios.']);
        exit;
    }

    // Comprobar si ya existe jugador por email en este torneo
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
        // Añadir torneo si no está ya
        $torneos = get_field('torneos_asociados', $jugador_post->ID) ?: [];
        if (!in_array($torneo_id, $torneos)) {
            $torneos[] = $torneo_id;
            update_field('torneos_asociados', $torneos, $jugador_post->ID);
        }
        wp_send_json_success(['mensaje' => 'Jugador ya existía y se ha añadido al torneo.']);
        exit;
    }

    // Si no existe, creamos el CPT jugador
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

    wp_send_json_success(['mensaje' => 'Jugador guardado correctamente.']);
    exit;
}
