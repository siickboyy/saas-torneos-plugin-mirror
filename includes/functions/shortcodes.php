<?php
/**
 * Archivo de shortcodes del plugin SaaS Torneos de Raqueta
 */

// Shortcode: [str_login_form]
function str_shortcode_login_form() {
    ob_start();
    include STR_PLUGIN_PATH . 'templates/login/login-form.php';
    return ob_get_clean();
}
add_shortcode('str_login_form', 'str_shortcode_login_form');
