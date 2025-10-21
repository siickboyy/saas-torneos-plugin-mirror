<?php
/**
 * Loader / Helpers comunes
 * Ruta: includes/core/loader.php
 */
if ( ! defined('ABSPATH') ) exit;

/** ==========================================================
 * Helpers de rutas/urls
 * ========================================================== */
if ( ! function_exists('str_path') ) {
    function str_path( $rel ) {
        $rel = ltrim($rel, '/\\');
        return trailingslashit( STR_PLUGIN_PATH . $rel );
    }
}
if ( ! function_exists('str_url') ) {
    function str_url( $rel ) {
        $rel = ltrim($rel, '/\\');
        return trailingslashit( STR_PLUGIN_URL . $rel );
    }
}

/**
 * Cargar seguro si existe (para migraciones progresivas)
 */
if ( ! function_exists('str_require_if_exists') ) {
    function str_require_if_exists( $abs_path ) {
        if ( file_exists( $abs_path ) ) {
            require_once $abs_path;
            return true;
        }
        return false;
    }
}

/** ==========================================================
 * Auto-enqueue de assets co-localizados con una plantilla
 * (lo usaremos en Fase 2 cuando movamos plantillas a /includes/sports/…)
 * ========================================================== */
if ( ! function_exists('str_enqueue_template_assets') ) {
    /**
     * @param string $template_abs  Ruta absoluta de la plantilla (ej. …/index.php)
     * @param string $handle_base   Prefijo de handles (ej. 'saas-padel-torneo')
     */
    function str_enqueue_template_assets( $template_abs, $handle_base ) {
        $dir = dirname( $template_abs );
        $basename = preg_replace('/\.php$/', '', basename( $template_abs )); // normalmente 'index'
        $css = $dir . '/' . $basename . '.css';
        $js  = $dir . '/' . $basename . '.js';

        // Encolar solo si existen (esto no hace nada todavía mientras no movamos plantillas)
        if ( file_exists( $css ) ) {
            $rel = str_replace( STR_PLUGIN_PATH, '', $css );
            wp_enqueue_style( $handle_base . '-css', str_url($rel), [], '1.0' );
        }
        if ( file_exists( $js ) ) {
            $rel = str_replace( STR_PLUGIN_PATH, '', $js );
            wp_enqueue_script( $handle_base . '-js', str_url($rel), ['jquery'], '1.0', true );
        }
    }
}

/** ==========================================================
 * Encolado de módulos compartidos (players/pairs/groups…)
 * (disponible para Fase 2+; ahora no altera nada)
 * ========================================================== */
if ( ! function_exists('str_enqueue_shared_modules') ) {
    function str_enqueue_shared_modules( $args = [] ) {
        $defaults = [
            'players'   => false,
            'pairs'     => false,
            'groups'    => false,
            'resultado' => false,
        ];
        $a = wp_parse_args( $args, $defaults );

        // PLAYERS
        if ( $a['players'] ) {
            $js  = str_path('includes/features/players/index.js');
            $css = str_path('includes/features/players/index.css');
            if ( file_exists($js) )  wp_enqueue_script('saas-players-js', str_url('includes/features/players/index.js'), ['jquery'], '1.0', true);
            if ( file_exists($css) ) wp_enqueue_style('saas-players-css',  str_url('includes/features/players/index.css'), [], '1.0');
        }

        // PAIRS
        if ( $a['pairs'] ) {
            $js  = str_path('includes/features/pairs/index.js');
            $css = str_path('includes/features/pairs/index.css');
            if ( file_exists($js) )  wp_enqueue_script('saas-pairs-js', str_url('includes/features/pairs/index.js'), ['jquery'], '1.0', true);
            if ( file_exists($css) ) wp_enqueue_style('saas-pairs-css',  str_url('includes/features/pairs/index.css'), [], '1.0');
        }

        // GROUPS
        if ( $a['groups'] ) {
            $js  = str_path('includes/features/groups/index.js');
            $css = str_path('includes/features/groups/index.css');
            if ( file_exists($js) )  wp_enqueue_script('saas-groups-js', str_url('includes/features/groups/index.js'), ['jquery'], '1.0', true);
            if ( file_exists($css) ) wp_enqueue_style('saas-groups-css',  str_url('includes/features/groups/index.css'), [], '1.0');
        }

        // RESULTADOS (ej. pádel)
        if ( $a['resultado'] ) {
            $js  = str_path('includes/sports/padel/torneo/form-resultado-partido.js');
            $css = str_path('includes/sports/padel/torneo/form-resultado-partido.css');
            if ( file_exists($js) )  wp_enqueue_script('saas-resultados-js', str_url('includes/sports/padel/torneo/form-resultado-partido.js'), ['jquery'], '1.0', true);
            if ( file_exists($css) ) wp_enqueue_style('saas-resultados-css',  str_url('includes/sports/padel/torneo/form-resultado-partido.css'), [], '1.0');
        }
    }
}
