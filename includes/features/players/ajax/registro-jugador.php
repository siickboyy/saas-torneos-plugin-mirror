<?php
// Antiguo /includes/ajax/ajax-registro-jugador.php

add_action('wp_ajax_nopriv_str_registro_jugador', 'str_ajax_registro_jugador');
add_action('wp_ajax_str_registro_jugador', 'str_ajax_registro_jugador');

function str_ajax_registro_jugador() {
    // 1. Recoger datos POST
    $token      = isset($_POST['token'])      ? sanitize_text_field($_POST['token'])      : '';
    $jugador_id = isset($_POST['jugador_id']) ? intval($_POST['jugador_id'])              : 0;
    $nombre     = isset($_POST['nombre'])     ? sanitize_text_field($_POST['nombre'])     : '';
    $apellidos  = isset($_POST['apellidos'])  ? sanitize_text_field($_POST['apellidos'])  : '';
    $email      = isset($_POST['email'])      ? sanitize_email($_POST['email'])           : '';
    $telefono   = isset($_POST['telefono'])   ? sanitize_text_field($_POST['telefono'])   : '';
    $password   = isset($_POST['password'])   ? $_POST['password']                        : '';

    // 2. Validaciones mínimas
    if (!$token || !$jugador_id || !$nombre || !$apellidos || !$email || !$password) {
        wp_send_json_error(['mensaje' => 'Faltan datos obligatorios.']);
        exit;
    }

    // 3. Buscar el post de jugador correspondiente
    $jugador_post = get_post($jugador_id);
    if (!$jugador_post || get_post_type($jugador_id) !== 'jugador_deportes') {
        wp_send_json_error(['mensaje' => 'Token inválido o jugador no encontrado.']);
        exit;
    }

    // 4. Validar token
    $token_cpt = get_field('token_invitacion', $jugador_id);
    if ($token !== $token_cpt) {
        wp_send_json_error(['mensaje' => 'Token inválido o expirado.']);
        exit;
    }

    // 5. Comprobar si ya existe usuario por email
    if (email_exists($email)) {
        wp_send_json_error(['mensaje' => 'Ya existe una cuenta con ese email. Si ya te registraste, inicia sesión.']);
        exit;
    }

    // 6. Crear usuario de WP
    $username = sanitize_user(strtolower(str_replace(' ', '', $nombre . $apellidos)));
    $username = wp_unique_username($username, $email);

    $user_id = wp_insert_user([
        'user_login'    => $username,
        'user_email'    => $email,
        'user_pass'     => $password,
        'display_name'  => $nombre . ' ' . $apellidos,
        'first_name'    => $nombre,
        'last_name'     => $apellidos,
        'role'          => 'jugador' // Asegúrate de que este rol existe
    ]);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['mensaje' => 'No se pudo crear el usuario. ' . $user_id->get_error_message()]);
        exit;
    }

    // 7. Actualizar el CPT jugador
    update_field('nombre', $nombre, $jugador_id);
    update_field('apellido', $apellidos, $jugador_id);
    update_field('email', $email, $jugador_id);
    update_field('telefono', $telefono, $jugador_id);
    update_field('user_id', $user_id, $jugador_id); // Relación con el usuario
    update_field('estado_invitacion', 'aceptado', $jugador_id);

    // Opcional: Borrar token para que no se pueda reutilizar
    update_field('token_invitacion', '', $jugador_id);

    // 8. ENVIAR EMAIL DE BIENVENIDA
    // Datos adicionales
    $torneos = get_field('torneos_asociados', $jugador_id);
    $redirect_url = home_url('/');
    $nombre_torneo = '';
    $fecha_torneo = '';
    $hora_torneo = '';

    if ($torneos && is_array($torneos) && count($torneos)) {
        $torneo_id = $torneos[0];
        $redirect_url = get_permalink($torneo_id);
        $nombre_torneo = get_the_title($torneo_id);
        $grupo_torneo = get_field('tipo_competicion_torneo', $torneo_id);
        if (is_array($grupo_torneo)) {
            $fecha_torneo = isset($grupo_torneo['fecha_torneo']) ? $grupo_torneo['fecha_torneo'] : '';
            $hora_torneo  = isset($grupo_torneo['hora_inicio']) ? $grupo_torneo['hora_inicio'] : '';
        }
    }

    // Mensaje de bienvenida
    $asunto = 'Bienvenido a padelXperience';
    $cuerpo = "¡Hola $nombre!\n\n";
    $cuerpo .= "Te has registrado en padelXperience para poder jugar la competición \"$nombre_torneo\" el $fecha_torneo";
    if ($hora_torneo) $cuerpo .= " a las $hora_torneo";
    $cuerpo .= ".\n\n";
    $cuerpo .= "Podrás consultar tus estadísticas, partidos e incluso apuntar los resultados. Tus datos no se compartirán con ningún tercero.\n";
    $cuerpo .= "Una vez iniciado sesión con nosotros, podrás participar en otros torneos y deportes, teniendo siempre acceso a estadísticas y posibilidad de apuntar resultados de partidos.\n\n";
    $cuerpo .= "👉 Accede a tu perfil: " . wp_login_url() . "\n";
    if ($redirect_url) {
        $cuerpo .= "👉 Accede al torneo: $redirect_url\n";
    }
    $cuerpo .= "\nGracias por confiar en nosotros.\n\nEquipo padelXperience";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $mail_ok = wp_mail($email, $asunto, $cuerpo, $headers);

    // Si quieres, puedes guardar log del envío en el ACF del jugador
    update_field('estado_bienvenida', $mail_ok ? 'enviado' : 'fallo', $jugador_id);

    // 9. Redirección: Por ahora, redirigimos a la home o a la URL de torneo si tienes la info en el CPT
    wp_send_json_success([
        'mensaje' => '¡Registro completado!',
        'redirect_url' => $redirect_url
    ]);
    exit;
}

// Utilidad: Generar un username único
if (!function_exists('wp_unique_username')) {
    function wp_unique_username($base, $email) {
        $username = $base;
        $i = 1;
        while (username_exists($username) || email_exists($username)) {
            $username = $base . $i;
            $i++;
        }
        return $username;
    }
}
