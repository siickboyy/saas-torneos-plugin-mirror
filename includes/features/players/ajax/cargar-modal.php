<?php
/**
 * AJAX: Cargar modal de invitaci¨®n de jugador (players)
 * Ruta: wp-content/plugins/saas-torneos-de-raqueta/includes/features/players/ajax/cargar-modal.php
 */
if ( ! defined('ABSPATH') ) exit;

// Logger opcional (seguro si no existe)
if ( ! function_exists('str_escribir_log') ) {
    function str_escribir_log( $m, $tag = 'PLAYERS-MODAL' ) { /* noop en prod */ }
}

/**
 * Handler ¨²nico con alias para acciones nuevas (saas_) y legacy (str_)
 */
$__saas_players_modal_handler = function () {
    $nonce   = isset($_POST['nonce'])   ? $_POST['nonce']   : '';
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    // Verificaci¨®n de nonce: debe coincidir con el que localizamos desde PHP (str_nonce)
    if ( ! wp_verify_nonce( $nonce, 'str_nonce' ) ) {
        str_escribir_log('[ERR] Nonce inv¨¢lido o ausente');
        wp_send_json_error([ 'message' => 'Nonce inv¨¢lido' ], 403);
    }

    // Cargamos la vista del modal (feature ¡ú legacy fallback)
    ob_start();

    $view_feature = STR_PLUGIN_PATH . 'includes/features/players/views/modal-invitacion.php';
    $view_legacy  = STR_PLUGIN_PATH . 'includes/forms/form-invitacion-jugador.php';

    if ( file_exists($view_feature) ) {
        include $view_feature;
    } elseif ( file_exists($view_legacy) ) {
        include $view_legacy;
    } else {
        echo '<div class="saas-tr-hint">Vista de modal no encontrada.</div>';
        str_escribir_log('[ERR] No existe view ni legacy para el modal');
    }

    $html = ob_get_clean();

    wp_send_json_success([
        'html'    => $html,
        'post_id' => $post_id,
    ], 200);
};

// Acciones nuevas (prefijo saas_)
add_action('wp_ajax_saas_cargar_modal_invitacion',        $__saas_players_modal_handler);
add_action('wp_ajax_nopriv_saas_cargar_modal_invitacion', $__saas_players_modal_handler);

// Alias legacy (prefijo str_), por si algo del c¨®digo viejo los sigue usando
add_action('wp_ajax_str_cargar_modal_invitacion',         $__saas_players_modal_handler);
add_action('wp_ajax_nopriv_str_cargar_modal_invitacion',  $__saas_players_modal_handler);
