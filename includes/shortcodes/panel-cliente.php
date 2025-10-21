<?php
/**
 * Shortcode: Panel del Cliente
 * Archivo: /includes/shortcodes/panel-cliente.php
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo

// Shortcode principal del panel del cliente
function str_shortcode_panel_cliente() {
    if ( ! is_user_logged_in() || ( ! current_user_can('cliente') && ! current_user_can('administrator') ) ) {
        return '<p>Acceso restringido. Debes iniciar sesión como cliente.</p>';
    }

    ob_start();

    ?>
    <div class="str-dashboard-container">
        <h2 class="str-dashboard-title">Panel del Cliente</h2>

        <div class="str-dashboard-menu">
            <ul>
                <li><a href="<?php echo esc_url(add_query_arg('seccion', 'mis-competiciones')); ?>">Mis competiciones</a></li>
                <li><a href="<?php echo esc_url(add_query_arg('seccion', 'jugadores')); ?>">Jugadores</a></li>
                <li><a href="<?php echo esc_url(add_query_arg('seccion', 'ajustes')); ?>">Ajustes</a></li>
                <li><a href="<?php echo esc_url(add_query_arg('seccion', 'nueva-competicion')); ?>">Crear nueva competición</a></li>
            </ul>
        </div>

        <div class="str-dashboard-content">
            <?php
            $seccion = isset($_GET['seccion']) ? sanitize_text_field($_GET['seccion']) : 'mis-competiciones';

            switch ($seccion) {
                case 'mis-competiciones':
                    $file = STR_PLUGIN_PATH . 'templates/dashboard/mis-competiciones.php';
                    if ( file_exists($file) ) {
                        include $file;
                    } else {
                        echo '<p>No se encontró la plantilla de competiciones.</p>';
                    }
                    break;

                case 'jugadores':
                    $file = STR_PLUGIN_PATH . 'templates/dashboard/jugadores.php';
                    if ( file_exists($file) ) {
                        include $file;
                    } else {
                        echo '<p>No se encontró la plantilla de jugadores.</p>';
                    }
                    break;

                case 'ajustes':
                    $file = STR_PLUGIN_PATH . 'templates/dashboard/ajustes.php';
                    if ( file_exists($file) ) {
                        include $file;
                    } else {
                        echo '<p>No se encontró la plantilla de ajustes.</p>';
                    }
                    break;

                case 'nueva-competicion':
                    $form_nueva_competicion = STR_PLUGIN_PATH . 'templates/dashboard/form-nueva-competicion.php';
                    if ( file_exists($form_nueva_competicion) ) {
                        include $form_nueva_competicion;
                    } else {
                        echo '<p>El formulario de nueva competición no está disponible.</p>';
                        if ( function_exists('str_escribir_log') ) {
                            str_escribir_log('El archivo form-nueva-competicion.php no existe. Include saltado.', 'panel-cliente.php');
                        }
                    }
                    break;

                default:
                    echo '<p>Selecciona una opción del menú.</p>';
                    break;
            }
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('panel_cliente', 'str_shortcode_panel_cliente');
