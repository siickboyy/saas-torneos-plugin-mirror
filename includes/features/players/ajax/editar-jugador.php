<?php
// *Antiguo: includes/ajax/ajax-editar-jugador.php. AJAX: Cargar y guardar datos de jugador vía modal (sólo clientes/administradores)

// Obtener datos actuales del jugador (para rellenar el modal)
add_action('wp_ajax_str_get_datos_jugador', 'str_get_datos_jugador');
function str_get_datos_jugador() {
    if (!current_user_can('manage_options') && !current_user_can('cliente')) {
        wp_send_json_error(['mensaje' => 'No tienes permisos.']);
        exit;
    }

    $jugador_id = isset($_POST['jugador_id']) ? intval($_POST['jugador_id']) : 0;
    if (!$jugador_id) {
        wp_send_json_error(['mensaje' => 'ID de jugador no válido.']);
        exit;
    }

    // Recuperar campos ACF del jugador
    $response = [
        'nombre'   => get_field('nombre', $jugador_id) ?: '',
        'apellido' => get_field('apellido', $jugador_id) ?: '',
        'email'    => get_field('email', $jugador_id) ?: '',
        'telefono' => get_field('telefono', $jugador_id) ?: '',
    ];

    wp_send_json_success($response);
    exit;
}

// Guardar datos editados del jugador (modal)
add_action('wp_ajax_str_guardar_datos_jugador', 'str_guardar_datos_jugador');
function str_guardar_datos_jugador() {
    if (!current_user_can('manage_options') && !current_user_can('cliente')) {
        wp_send_json_error(['mensaje' => 'No tienes permisos para editar.']);
        exit;
    }

    $jugador_id = isset($_POST['jugador_id']) ? intval($_POST['jugador_id']) : 0;
    if (!$jugador_id) {
        wp_send_json_error(['mensaje' => 'ID de jugador no válido.']);
        exit;
    }

    // Recoger y sanear los datos recibidos
    $nombre   = isset($_POST['nombre'])   ? sanitize_text_field($_POST['nombre'])     : '';
    $apellido = isset($_POST['apellido']) ? sanitize_text_field($_POST['apellido'])   : '';
    $email    = isset($_POST['email'])    ? sanitize_email($_POST['email'])           : '';
    $telefono = isset($_POST['telefono']) ? sanitize_text_field($_POST['telefono'])   : '';

    // Validación simple
    if (!$nombre || !$apellido || !$email) {
        wp_send_json_error(['mensaje' => 'Nombre, apellido y email son obligatorios.']);
        exit;
    }

    // Guardar campos ACF
    update_field('nombre',   $nombre,   $jugador_id);
    update_field('apellido', $apellido, $jugador_id);
    update_field('email',    $email,    $jugador_id);
    update_field('telefono', $telefono, $jugador_id);

    // Nuevo título (nombre + apellido)
    $nuevo_titulo = $nombre . ' ' . $apellido;

    // Slug (sanitize_title genera un slug compatible con WP)
    $nuevo_slug = sanitize_title($nuevo_titulo);

    // Recuperar el post actual antes de actualizarlo para comparar el slug
    $post_actual = get_post($jugador_id);
    $slug_anterior = $post_actual->post_name;

    // Actualizar el título y el slug
    wp_update_post([
        'ID'         => $jugador_id,
        'post_title' => $nuevo_titulo,
        'post_name'  => $nuevo_slug
    ]);

    // Comprobar si la URL ha cambiado (el slug es distinto)
    $url_nueva = get_permalink($jugador_id);
    $url_antigua = home_url('/jugador_deportes/' . $slug_anterior . '/'); // Ajusta el CPT/slug si lo cambias

    $cambio_url = ($nuevo_slug !== $slug_anterior);

    // Respuesta AJAX
    $respuesta = [
        'mensaje'    => 'Datos actualizados correctamente.',
        'cambio_url' => $cambio_url,
        'url_nueva'  => $url_nueva,
        'url_antigua'=> $url_antigua
    ];

    wp_send_json_success($respuesta);
    exit;
}
