<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Bootstrap Groups v2 (REST + flag + enqueue)
 * Se invoca desde el mu-plugin loader.
 */
function str_groups_v2_bootstrap( $plugin_dir ) {
    // Guardamos ruta en constante local si no existe
    if ( ! defined('STR_GROUPS_V2_DIR') ) {
        define('STR_GROUPS_V2_DIR', trailingslashit($plugin_dir) . 'includes/features/groups_v2/');
    }
    if ( ! defined('STR_GROUPS_V2_URL') ) {
        $base_url = trailingslashit( plugin_dir_url( $plugin_dir . '/saas-torneos-de-raqueta.php' ) ); // fallback genérico
        // Mejor: derivar desde el path real del plugin
        $base_url = trailingslashit( plugin_dir_url( $plugin_dir . '/' ) . 'saas-torneos-de-raqueta' );
        define('STR_GROUPS_V2_URL', $base_url . 'includes/features/groups_v2/');
    }

    require_once STR_GROUPS_V2_DIR . 'rest.php';

    add_action('rest_api_init', function() {
        (new STR_Groups_V2_REST())->register_routes();
    });

    // Flag v2
    if ( ! function_exists('str_groups_v2_is_enabled') ) {
        function str_groups_v2_is_enabled() {
            // GET tiene preferencia para pruebas: ?groups_engine=v2
            if ( isset($_GET['groups_engine']) && $_GET['groups_engine'] === 'v2' ) return true;
            $opt = get_option('str_groups_engine');
            if ( is_string($opt) && strtolower($opt) === 'v2' ) return true;
            return false;
        }
    }

    // Enqueue del JS v2 SOLO si flag activo y estamos en una competición (CPT)
    add_action('wp_enqueue_scripts', function() use ($plugin_dir) {
        if ( ! str_groups_v2_is_enabled() ) return;
        if ( ! is_singular('competicion') ) return;

        global $post;
        if ( empty($post) ) return;

        // Puedes afinar por deporte/tipo con ACF si quieres (comentado):
        // $deporte = function_exists('get_field') ? get_field('deporte', $post->ID) : '';
        // $tipo    = function_exists('get_field') ? get_field('tipo_competicion', $post->ID) : '';
        // if ( strtolower($deporte) !== 'padel' || strtolower($tipo) !== 'torneo' ) return;

        // Encolar JS v2
        $assets_js_rel  = 'assets/js/torneo-grupos-v2.js';
        $assets_js_abs  = trailingslashit($plugin_dir) . $assets_js_rel;
        $assets_js_url  = trailingslashit( plugin_dir_url($plugin_dir) ) . 'saas-torneos-de-raqueta/' . $assets_js_rel;

        $ver = file_exists($assets_js_abs) ? @filemtime($assets_js_abs) : '1.0.0';
        wp_enqueue_script( 'str-groups-v2-js', $assets_js_url, ['jquery'], $ver, true );

        // Datos para el JS
        wp_localize_script( 'str-groups-v2-js', 'strTorneoV2', [
            'restBase'       => esc_url_raw( rest_url('saas/v1') ),
            'restNonce'      => wp_create_nonce('wp_rest'),
            'competicionId'  => (int) $post->ID,
            'reqId'          => function_exists('str_req_id') ? str_req_id() : substr(uniqid('r', true), 0, 12),
        ]);

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/ENQUEUE', 'JS encolado', ['post_id'=>$post->ID, 'ver'=>$ver]);
        }
    }, 25);
}
