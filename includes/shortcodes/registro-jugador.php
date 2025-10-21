<?php
// /includes/shortcodes/registro-jugador.php

function str_shortcode_registro_jugador() {
    ob_start();

    // 1. Obtener token de la URL (?token=xxxxx)
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;

    $es_admin = in_array('administrator', $roles);
    $es_cliente = in_array('cliente', $roles);

    // Si es admin o cliente: NO necesita token y muestra formulario vacío para pruebas
    if ($es_admin || $es_cliente) {
        // Datos de ejemplo (vacíos para que puedan probar)
        $nombre = '';
        $apellidos = '';
        $email = '';
        $telefono = '';
        $jugador_id = '';
    } else {
        // Si no hay token: acceso denegado
        if (!$token) {
            return '<div class="str-aviso-error">Acceso denegado. Token no proporcionado.</div>';
        }

        // 2. Buscar el jugador correspondiente a ese token
        $jugador = null;
        $query = new WP_Query([
            'post_type' => 'jugador_deportes',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'token_invitacion',
                    'value' => $token,
                    'compare' => '='
                ]
            ]
        ]);

        if ($query->have_posts()) {
            $jugador = $query->posts[0];
        } else {
            echo '<div class="str-registro-error">Token inválido o expirado.</div>';
            return ob_get_clean();
        }

        // 3. Comprobar si ya está aceptado/registrado (campo ACF "estado_invitacion")
        $estado = get_field('estado_invitacion', $jugador->ID);
        if ($estado === 'aceptado') {
            echo '<div class="str-registro-error">Ya estás registrado. <a href="' . wp_login_url() . '">Inicia sesión aquí</a>.</div>';
            return ob_get_clean();
        }

        // 4. Obtener los datos para rellenar el formulario por defecto
        $nombre = get_field('nombre', $jugador->ID);
        $apellidos = get_field('apellido', $jugador->ID);
        $email = get_field('email', $jugador->ID);
        $telefono = get_field('telefono', $jugador->ID);
        $jugador_id = $jugador->ID;
    }

    // 5. Cargar CSS y JS (asegúrate de que existan los archivos en /assets/css/ y /assets/js/)
    echo '<link rel="stylesheet" href="' . STR_PLUGIN_URL . 'assets/css/registro-jugador.css">';
    echo '<script src="' . STR_PLUGIN_URL . 'assets/js/registro-jugador.js"></script>';

    // 6. Renderizar el formulario
    ?>
    <div class="str-registro-jugador-wrapper">
        <h2>Registro de jugador</h2>
        <form id="str-form-registro-jugador" autocomplete="off">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="jugador_id" value="<?php echo esc_attr($jugador_id); ?>">
            <div>
                <label>Nombre*</label>
                <input type="text" name="nombre" value="<?php echo esc_attr($nombre); ?>" required>
            </div>
            <div>
                <label>Apellidos*</label>
                <input type="text" name="apellidos" value="<?php echo esc_attr($apellidos); ?>" required>
            </div>
            <div>
                <label>Email*</label>
                <input type="email" name="email" value="<?php echo esc_attr($email); ?>" required>
            </div>
            <div>
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?php echo esc_attr($telefono); ?>">
            </div>
            <div>
                <label>Contraseña*</label>
                <input type="password" name="password" required>
            </div>
            <div>
                <label>Confirmar contraseña*</label>
                <input type="password" name="password2" required>
            </div>
            <button type="submit" id="btn-registro-jugador">Registrarme</button>
            <div id="mensaje-registro-jugador" style="margin-top:10px;"></div>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('registro_jugador', 'str_shortcode_registro_jugador');
