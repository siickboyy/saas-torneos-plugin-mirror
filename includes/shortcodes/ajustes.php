<?php
// Shortcode: [ajustes_cliente]
function str_shortcode_ajustes_cliente() {
    if (!is_user_logged_in() || (!current_user_can('cliente') && !current_user_can('administrator'))) {
        return '<p>Acceso restringido. Debes iniciar sesión como cliente.</p>';
    }

    ob_start();

    $current_user = wp_get_current_user();
    ?>

    <div class="str-dashboard-container">
        <h2 class="str-bienvenida">Ajustes de cuenta</h2>

        <ul style="list-style: none; padding: 0; font-size: 16px;">
            <li><strong>Usuario:</strong> <?php echo esc_html($current_user->user_login); ?></li>
            <li><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></li>
            <li><strong>Nombre mostrado:</strong> <?php echo esc_html($current_user->display_name); ?></li>
        </ul>

        <p>En futuras versiones podrás cambiar el logo del torneo, personalizar el texto de los correos, y ajustar tu panel.</p>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('ajustes_cliente', 'str_shortcode_ajustes_cliente');
