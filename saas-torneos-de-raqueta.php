<?php
/**
 * Plugin Name: SaaS Torneos de Raqueta
 * Description: Plugin para la gestión de competiciones de pádel, tenis, pickleball y tenis playa.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Text Domain: saas-torneos
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo

if ( ! defined( 'STR_PLUGIN_VERSION' ) ) {
    define( 'STR_PLUGIN_VERSION', '0.2.0' ); // pon aquí tu versión actual para probar
}

/**
 * Registrar el shortcode [str_version]
 * Devuelve la versión del plugin.
 */
add_action( 'init', function () {
    add_shortcode( 'str_version', function () {
        return 'SaaS Torneos versión: ' . esc_html( STR_PLUGIN_VERSION );
    } );
} );

// ======================================================
// Constantes
// ======================================================
define( 'STR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'STR_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ======================================================
// Guardián de errores críticos (solo LOG, sin alterar lógica)
// ======================================================
if ( ! function_exists('str_trap_php_errors_init') ) {

    // Fallback local por si str_escribir_log aún no existe cuando disparemos
    if ( ! function_exists('str__safe_write_log') ) {
        function str__safe_write_log( $msg, $tag = 'FATAL-GUARD' ) {
            $base = defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH : plugin_dir_path(__FILE__);
            $ruta = rtrim($base, '/\\') . '/debug-saas-torneos.log';
            $ts   = date('[Y-m-d H:i:s]');
            if (is_array($msg) || is_object($msg)) { $msg = print_r($msg, true); }
            @file_put_contents($ruta, $ts . " [$tag] " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    function str_trap_php_errors_init() {
        set_error_handler(function($errno, $errstr, $errfile, $errline){
            if (!(error_reporting() & $errno)) { return false; }
            $m = "[PHP-ERROR:$errno] $errstr | $errfile:$errline";
            if (function_exists('str_escribir_log')) { str_escribir_log($m, 'FATAL-GUARD'); }
            else { str__safe_write_log($m, 'FATAL-GUARD'); }
            return false;
        });

        set_exception_handler(function($ex){
            $m = get_class($ex) . ': ' . $ex->getMessage() . ' | ' . $ex->getFile() . ':' . $ex->getLine();
            if (function_exists('str_escribir_log')) { str_escribir_log($m, 'FATAL-GUARD-EX'); }
            else { str__safe_write_log($m, 'FATAL-GUARD-EX'); }
        });

        register_shutdown_function(function(){
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
                $m = "[{$e['type']}] {$e['message']} | {$e['file']}:{$e['line']}";
                if (function_exists('str_escribir_log')) { str_escribir_log($m, 'FATAL-SHUTDOWN'); }
                else { str__safe_write_log($m, 'FATAL-SHUTDOWN'); }
            }
        });
    }
    str_trap_php_errors_init();
}

// ======================================================
if ( ! function_exists( 'str_escribir_log' ) ) {
    function str_escribir_log( $mensaje, $origen = '' ) {
        $ruta_log = STR_PLUGIN_PATH . 'debug-saas-torneos.log';
        $timestamp = date('[Y-m-d H:i:s]');
        $origen = $origen ? "[$origen]" : '';
        if ( is_array($mensaje) || is_object($mensaje) ) {
            $mensaje = print_r($mensaje, true);
        }
        $linea = "$timestamp $origen $mensaje" . PHP_EOL;
        @file_put_contents( $ruta_log, $linea, FILE_APPEND | LOCK_EX );
    }
}

// ======================================================
// Diagnóstico mínimo (mantener arriba)
// ======================================================
require_once STR_PLUGIN_PATH . 'includes/diagnostico.php';

// ======================================================
// ENDPOINT simulador: /panel/simulador-torneo/
// ======================================================
add_action('init', function() {
    add_rewrite_rule('^panel/simulador-torneo/?$', 'index.php?simulador_torneo=1', 'top');
});
add_filter('query_vars', function($vars){
    $vars[] = 'simulador_torneo';
    return $vars;
});
add_action('template_include', function($template){
    if (intval(get_query_var('simulador_torneo')) === 1) {
        if (is_user_logged_in() && (current_user_can('cliente') || current_user_can('administrator'))) {
            return STR_PLUGIN_PATH . 'includes/features/simulator/index.php';
        } else {
            wp_redirect( home_url('/login/') );
            exit;
        }
    }
    return $template;
});

// ======================================================
// Font Awesome (global)
// ======================================================
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css' );
});

// ======================================================
require_once STR_PLUGIN_PATH . 'includes/cpt/cpt-competicion.php';
require_once STR_PLUGIN_PATH . 'includes/cpt/cpt-jugadores.php';
require_once STR_PLUGIN_PATH . 'includes/cpt/cpt-parejas.php';
require_once STR_PLUGIN_PATH . 'includes/cpt/cpt-partidos.php';
require_once STR_PLUGIN_PATH . 'includes/cpt/cpt-grupo.php';

require_once STR_PLUGIN_PATH . 'includes/functions/roles.php';
require_once STR_PLUGIN_PATH . 'includes/functions/shortcodes.php';
require_once STR_PLUGIN_PATH . 'includes/shortcodes/init.php';
require_once STR_PLUGIN_PATH . 'includes/functions/crear-competicion.php';
require_once STR_PLUGIN_PATH . 'includes/functions/login-redirect.php';

// Admin columns
require_once STR_PLUGIN_PATH . 'includes/admin-columns/columns-competicion.php';
require_once STR_PLUGIN_PATH . 'includes/admin-columns/columns-jugadores.php';
require_once STR_PLUGIN_PATH . 'includes/features/groups/ajax/manage.php';

// Matches (sistema de partidos) – Skeleton FASE A.2
require_once plugin_dir_path( __FILE__ ) . 'includes/features/matches/model.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/features/matches/rest.php';

// ======================================================
// AUTOLOAD de AJAX por módulos “features” (sin logs)
// ======================================================
$__STR_AJAX_MAP = (function () {
    return [

        // PLAYERS
        'saas_cargar_modal_invitacion' => [
            'includes/features/players/ajax/cargar-modal.php',
            'includes/ajax/ajax-cargar-modal-invitacion.php',
        ],
        'saas_invitacion_automatica' => [
            'includes/features/players/ajax/cargar-form-automatica.php',
            'includes/ajax/ajax-cargar-form-invitacion-automatica.php',
        ],
        'saas_invitacion_manual' => [
            'includes/features/players/ajax/cargar-form-manual.php',
            'includes/ajax/ajax-cargar-form-invitacion-manual.php',
        ],
        'saas_invitacion_enviar' => [
            'includes/features/players/ajax/invitar-jugador.php',
            'includes/ajax/ajax-invitar-jugador.php',
        ],
        'saas_invitacion_enviar_manual' => [
            'includes/features/players/ajax/invitar-jugador-manual.php',
            'includes/ajax/ajax-invitar-jugador-manual.php',
        ],
        'saas_registro_jugador' => [
            'includes/features/players/ajax/registro-jugador.php',
            'includes/ajax/ajax-registro-jugador.php',
        ],
        'saas_comprobar_jugador' => [
            'includes/features/players/ajax/comprobar-jugador.php',
            'includes/ajax/ajax-comprobar-jugador.php',
        ],
        'saas_cargar_estadisticas_jugador' => [
            'includes/features/players/ajax/cargar-estadisticas.php',
            'includes/ajax/ajax-cargar-estadisticas-jugador.php',
        ],
        'saas_cargar_competiciones_jugador' => [
            'includes/features/players/ajax/cargar-competiciones.php',
            'includes/ajax/ajax-cargar-competiciones-jugador.php',
        ],
        'saas_editar_jugador' => [
            'includes/features/players/ajax/editar-jugador.php',
            'includes/ajax/ajax-editar-jugador.php',
        ],

        // PAIRS
        'saas_buscar_jugadores' => [
            'includes/features/pairs/ajax/buscar-jugadores.php',
            'includes/ajax/ajax-buscar-jugadores.php',
        ],
        'saas_guardar_pareja_multiseleccion' => [
            'includes/features/pairs/ajax/guardar-pareja.php',
            'includes/ajax/ajax-guardar-pareja-multiseleccion.php',
        ],
        'saas_listar_parejas_multiseleccion' => [
            'includes/features/pairs/ajax/listar-parejas.php',
            'includes/ajax/ajax-listar-parejas-multiseleccion.php',
        ],

        // GROUPS
        // (las acciones concretas de crear/asignar/quitar se cargarán
        // desde los archivos si existen — el autoloader los incluye si file_exists)
        'saas_grupo_crear' => [
            'includes/features/groups/ajax/grupo-crear.php',
            'includes/ajax/ajax-grupo-crear.php',
        ],
        'str_grupo_crear' => [
            'includes/features/groups/ajax/grupo-crear.php',
            'includes/ajax/ajax-grupo-crear.php',
        ],

        'saas_grupos_cargar' => [
            'includes/features/groups/ajax/grupos-cargar.php',
            'includes/ajax/ajax-grupos-cargar.php',
        ],
        'saas_grupos_aleatorio' => [
            'includes/features/groups/ajax/grupos-aleatorio.php',
            'includes/ajax/ajax-grupos-aleatorio.php',
        ],
        'saas_grupo_asignar_pareja' => [
            'includes/features/groups/ajax/grupo-asignar-pareja.php',
            'includes/ajax/ajax-grupo-asignar-pareja.php',
        ],
        'saas_grupos_standings' => [
            'includes/features/groups/ajax/standings-grupo.php',
            'includes/ajax/ajax-standings-grupo.php',
        ],
        'saas_bracket_volcar' => [
            'includes/features/groups/ajax/bracket-volcar.php',
            'includes/ajax/ajax-bracket-volcar.php',
        ],

        // RESULTS — Pádel
        'str_guardar_resultado' => [
            'includes/features/results/padel/ajax/guardar-resultado.php',
            'includes/ajax/ajax-guardar-resultado.php',
        ],

        // TORNEO - edición básica (legacy)
        'saas_guardar_torneo' => [
            'includes/ajax/ajax-guardar-torneo.php',
        ],

        // SIMULADOR (legacy)
        'saas_simular_torneo' => [
            'includes/ajax/ajax-simular-torneo.php',
        ],
        'saas_guardar_simulacion' => [
            'includes/ajax/ajax-guardar-simulacion.php',
        ],

        // EDITAR TORNEO (guardar por AJAX)
        'str_guardar_torneo_editado' => [
            'includes/features/tournaments/ajax/guardar-torneo.php',
            'includes/ajax/ajax-guardar-torneo-editado.php',
        ],

    ];
})();

(function ($ajax_map) {
    foreach ( $ajax_map as $action => $paths ) {
        $found = false;
        foreach ( $paths as $idx => $rel ) {
            $abs = STR_PLUGIN_PATH . $rel;
            if ( file_exists( $abs ) ) {
                require_once $abs;
                $found = true;
                break;
            }
        }
        // Si no se encuentra, se omite silenciosamente (sin logs)
    }
})($__STR_AJAX_MAP);



// ======================================================
// AUDITORÍA F11 (temporal): /wp-admin/?str_audit=1 (sin logs)
// ======================================================
add_action('admin_init', function() use ($__STR_AJAX_MAP) {
    if ( ! current_user_can('administrator') ) return;
    if ( ! isset($_GET['str_audit']) || intval($_GET['str_audit']) !== 1 ) return;

    $legacy_dir = STR_PLUGIN_PATH . 'includes/ajax/';
    $legacy = [];
    if ( is_dir($legacy_dir) ) {
        foreach ( glob($legacy_dir . '*.php') as $file ) {
            $legacy[] = str_replace(STR_PLUGIN_PATH, '', $file);
        }
    }

    foreach ($legacy as $relPath) {
        $mapped_action = null;
        $features_path = null;

        foreach ($__STR_AJAX_MAP as $action => $paths) {
            if ( in_array($relPath, $paths, true) ) {
                $mapped_action = $action;
                $features_path = $paths[0] ?? null;
                break;
            }
        }
        // Sin salida visible; solo auditoría.
    }
});

// ======================================================
// Filtros de correo (remitente)
// ======================================================
add_filter('wp_mail_from', function($from_email) { return 'info@torneospadelxperience.es'; });
add_filter('wp_mail_from_name', function($from_name) { return 'Torneos Padel Xperience'; });

// ======================================================
// i18n
// ======================================================
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'saas-torneos', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
});

// ======================================================
// Enqueue: frontend base
// ======================================================
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'str-frontend-css',
        STR_PLUGIN_URL . 'assets/css/frontend.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'str-frontend-js',
        STR_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('str-frontend-js', 'str_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('str_nonce')
    ]);
});

// ======================================================
// Enqueue: dashboard cliente (condicional)
// ======================================================
add_action('wp_enqueue_scripts', function() {
    if ( is_page(['panel-del-cliente', 'mis-competiciones', 'jugadores', 'ajustes']) ) {
        wp_enqueue_style(
            'str-dashboard-cliente-css',
            STR_PLUGIN_URL . 'assets/css/dashboard-cliente.css',
            [],
            '1.1'
        );
    }
});

// ======================================================
// Enqueue: crear competición (shortcode/página)
// ======================================================
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'str-frontend-js',
        STR_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        '1.0',
        true
    );

    if ( (is_page('panel-del-cliente') && (isset($_GET['seccion']) && $_GET['seccion'] === 'nueva-competicion')) || is_page('crear-competicion') ) {
        wp_enqueue_script(
            'str-nueva-competicion-js',
            STR_PLUGIN_URL . 'assets/js/nueva-competicion.js',
            ['jquery'],
            '1.0',
            true
        );
    }
});

// ======================================================
// Enqueue: simulador (solo en /panel/simulador-torneo/)
// ======================================================
add_action('wp_enqueue_scripts', function() {
    global $wp_query;
    if ( isset($wp_query->query_vars['simulador_torneo']) && intval($wp_query->query_vars['simulador_torneo']) === 1 ) {
        wp_enqueue_script( 'jquery' );

        wp_enqueue_script(
            'str-simulador-torneo-js',
            STR_PLUGIN_URL . 'assets/js/simulador-torneo.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_enqueue_style(
            'str-simulador-torneo-css',
            STR_PLUGIN_URL . 'assets/css/simulador-torneo.css',
            [],
            '1.0'
        );

        wp_localize_script('str-simulador-torneo-js', 'str_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('str_nonce')
        ]);
    }
});

// ======================================================
// Routing de plantillas personalizadas
// ======================================================
add_filter('template_include', function($template) {
    if ( ! is_singular('competicion') ) return $template;

    global $post;
    if ( ! isset($post->ID) ) return $template;

    $deporte = function_exists('get_field') ? get_field('deporte', $post->ID) : '';
    $tipo    = function_exists('get_field') ? get_field('tipo_competicion', $post->ID) : '';

    if ( strtolower($deporte) === 'padel' && strtolower($tipo) === 'torneo' ) {
        $torneo_template = STR_PLUGIN_PATH . 'includes/sports/padel/torneo/index.php';
        if ( file_exists($torneo_template) ) return $torneo_template;
    }

    if ( strtolower($deporte) === 'padel' && strtolower($tipo) === 'ranking' ) {
        $ranking_template = STR_PLUGIN_PATH . 'templates/padel/ranking.php';
        if ( file_exists($ranking_template) ) return $ranking_template;
    }

    return $template;
}, 10);

// Perfil de jugador
add_filter('template_include', function($template){
    if ( ! is_singular('jugador_deportes') ) return $template;

    $perfil_template = STR_PLUGIN_PATH . 'templates/jugador/perfil-jugador.php';
    if ( file_exists($perfil_template) ) return $perfil_template;

    return $template;
}, 20);

// Enqueue específico de perfil jugador
add_action('wp_enqueue_scripts', function() {
    if ( is_singular('jugador_deportes') ) {
        wp_enqueue_script(
            'str-perfil-jugador-js',
            STR_PLUGIN_URL . 'assets/js/perfil-jugador.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('str-perfil-jugador-js', 'str_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('str_nonce')
        ]);
    }
});

// ======================================================
// Enqueue por módulo en la ficha de TORNEO (Pádel)
// ======================================================
add_action('wp_enqueue_scripts', function() {

    if ( ! is_singular('competicion') ) return;

    global $post;
    if ( empty($post) ) return;

    $deporte = function_exists('get_field') ? get_field('deporte', $post->ID) : '';
    $tipo    = function_exists('get_field') ? get_field('tipo_competicion', $post->ID) : '';
    if ( strtolower($deporte) !== 'padel' || strtolower($tipo) !== 'torneo' ) return;

    $post_id = (int) $post->ID;

    $reg = function( $handle, $rel_path, $deps = ['jquery'], $in_footer = true, $is_style = false ) {
        $abs = STR_PLUGIN_PATH . $rel_path;
        $ver = file_exists($abs) ? @filemtime($abs) : '1.0';
        $url = STR_PLUGIN_URL  . $rel_path;

        if ( $is_style ) {
            if ( file_exists($abs) ) {
                wp_register_style( $handle, $url, [], $ver );
                wp_enqueue_style( $handle );
                return true;
            }
        } else {
            if ( file_exists($abs) ) {
                wp_register_script( $handle, $url, $deps, $ver, $in_footer );
                wp_enqueue_script( $handle );
                return true;
            }
        }
        return false;
    };

    // ---------- VISTA TORNEO ----------
    $ok_js  = $reg('saas-torneo-view-js',  'includes/sports/padel/torneo/index.js');
    $ok_css = $reg('saas-torneo-view-css', 'includes/sports/padel/torneo/index.css', [], false, true);

    // ---------- PAIRS ----------
    $ok_js  = $reg('saas-pairs-js',  'includes/features/pairs/index.js');
    $ok_css = $reg('saas-pairs-css', 'includes/features/pairs/index.css', [], false, true);
    if ( ! $ok_js ) { $reg('saas-pairs-js',  'assets/js/form-parejas-multiseleccion.js'); }
    if ( ! $ok_css ) { $reg('saas-pairs-css', 'assets/css/form-parejas-multiseleccion.css', [], false, true); }
    wp_localize_script('saas-pairs-js', 'str_parejas_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('str_parejas_multiseleccion_nonce'),
        'post_id'  => $post_id,
        'actions'  => [
            'buscar'  => 'str_buscar_jugadores',
            'guardar' => 'str_guardar_pareja_multiseleccion',
            'listar'  => 'str_listar_parejas_multiseleccion',
        ],
    ]);

    // ---------- PLAYERS ----------
    $ok_js  = $reg('saas-players-js',  'includes/features/players/index.js');
    $ok_css = $reg('saas-players-css', 'includes/features/players/index.css', [], false, true);
    if ( ! $ok_js ) { $reg('saas-players-js',  'assets/js/form-invitacion-jugador.js'); }
    if ( ! $ok_css ) {
        $reg('saas-players-css', 'assets/css/form-invitacion-jugador.css', [], false, true);
        $reg('saas-players-css-manual', 'assets/css/form-invitacion-jugador-manual.css', [], false, true);
    }
    wp_localize_script('saas-players-js', 'str_players_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('str_nonce'),
        'post_id'  => $post_id,
        'actions'  => [
            'cargar_modal'   => 'str_cargar_modal_invitacion',
            'form_auto'      => 'str_cargar_form_invitacion_automatica',
            'form_manual'    => 'str_cargar_form_invitacion_manual',
            'enviar_auto'    => 'str_invitar_jugador',
            'enviar_manual'  => 'str_invitar_jugador_manual',
            'registro_token' => 'str_registro_jugador',
        ],
    ]);

    // ---------- GROUPS ----------
    $ok_js  = $reg('saas-groups-js',  'includes/features/groups/index.js');
    $ok_css = $reg('saas-groups-css', 'includes/features/groups/index.css', [], false, true);
    if ( ! $ok_js ) { $reg('saas-groups-js',  'assets/js/grupos-torneo.js'); }
    if ( ! $ok_css ) { $reg('saas-groups-css', 'assets/css/grupos-torneo.css', [], false, true); }

    // **ÚNICO CAMBIO MÍNIMO**: añadimos 'crear' => 'saas_grupo_crear'
    $groups_actions = [
        'crear'     => 'saas_grupo_crear',
        'cargar'    => 'saas_grupos_cargar',
        'aleatorio' => 'saas_grupos_aleatorio',
        'asignar'   => 'saas_grupo_asignar_pareja',
        'standings' => 'saas_grupos_standings',
        'bracket'   => 'saas_bracket_volcar',
    ];
    $groups_payload = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('str_nonce'),
        'post_id'  => $post_id,
        'actions'  => $groups_actions,
    ];
    wp_localize_script('saas-groups-js', 'str_groups_ajax_obj', $groups_payload);

    // ENQUEUE LOG (lo dejas igual si ya lo tenías)
    try {
        $log_data = [
            'post_id'   => $post_id,
            'has_nonce' => ! empty($groups_payload['nonce']),
            'actions'   => $groups_actions,
            'handle'    => 'saas-groups-js',
            'route'     => 'competicion',
        ];
        str_escribir_log('[GRUPOS:ENQUEUE] ' . wp_json_encode($log_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'GROUPS');
    } catch (\Throwable $e) {}

    // ---------- RESULTS (PÁDEL) ----------
    $ok_js  = $reg('saas-resultado-padel-js',  'includes/features/results/padel/index.js');
    $ok_css = $reg('saas-resultado-padel-css', 'includes/features/results/padel/index.css', [], false, true);
    if ( ! $ok_js ) { $reg('saas-resultado-padel-js',  'assets/js/form-resultado-partido.js'); }
    if ( ! $ok_css ) { $reg('saas-resultado-padel-css', 'assets/css/form-resultado-partido.css', [], false, true); }
    wp_localize_script('saas-resultado-padel-js', 'str_resultado_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('str_nonce'),
        'post_id'  => $post_id,
        'actions'  => [ 'guardar' => 'str_guardar_resultado' ],
    ]);

    if ( ! function_exists( 'str_enqueue_tournaments_css_only' ) ) {
        function str_enqueue_tournaments_css_only() {
            if ( ! is_singular( 'competicion' ) ) { return; }
            $rel = 'includes/features/tournaments/index.css';
            $abs = plugin_dir_path( __FILE__ ) . $rel;
            $url = plugin_dir_url( __FILE__ )  . $rel;
            if ( file_exists( $abs ) ) {
                $ver = filemtime( $abs );
                wp_enqueue_style( 'str_tournaments_css', $url, array(), $ver );
            }
        }
        add_action( 'wp_enqueue_scripts', 'str_enqueue_tournaments_css_only', 20 );
    }

}, 20);

if (!defined('ABSPATH')) { exit; }
define('STR_PLUGIN_VERSION', '0.1.1'); // súbelo un número
add_shortcode('str_version', fn() => 'SaaS Torneos versión: ' . esc_html(STR_PLUGIN_VERSION));
