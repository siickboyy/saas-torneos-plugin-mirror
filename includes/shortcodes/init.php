<?php
/**
 * Registro centralizado de todos los shortcodes largos del plugin
 */

require_once __DIR__ . '/panel-cliente.php';
require_once __DIR__ . '/mis-competiciones.php';
require_once __DIR__ . '/jugadores.php';
require_once __DIR__ . '/ajustes.php';
require_once __DIR__ . '/crear-competicion.php';
require_once __DIR__ . '/registro-jugador.php';


add_action('wp_enqueue_scripts', 'str_enqueue_css_nueva_competicion');
function str_enqueue_css_nueva_competicion() {
    if (is_page('crear-competicion')) {
        wp_enqueue_style(
            'str-nueva-competicion-css',
            STR_PLUGIN_URL . 'assets/css/nueva-competicion.css',
            [],
            '1.1'
        );
    }
}
