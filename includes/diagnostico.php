<?php
/**
 * Archivo: /includes/diagnostico.php
 * Propósito: Forzar la creación del archivo de log y dejar trazas mínimas al recargar la web.
 */
if ( ! defined('ABSPATH') ) { exit; }

// --- Utilidad local de log (fallback) si aún no existe str_escribir_log ---
if ( ! function_exists('str_escribir_log') ) {
    function str_escribir_log($mensaje, $origen = '') {
        $ruta_log  = plugin_dir_path( dirname(__FILE__) ) . 'debug-saas-torneos.log'; // raíz del plugin
        $timestamp = date('[Y-m-d H:i:s]');
        $origen    = $origen ? "[$origen]" : '';
        if (is_array($mensaje) || is_object($mensaje)) {
            $mensaje = print_r($mensaje, true);
        }
        @file_put_contents($ruta_log, "$timestamp $origen $mensaje" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// 1) Forzar creación del archivo de log e indicar que el diagnóstico cargó
str_escribir_log('Diagnóstico cargado OK', 'DIAGNOSTICO');

// 2) Dejar una traza de “arranque de petición” (BOOT)
$uri        = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$is_admin   = function_exists('is_admin') ? (is_admin() ? 1 : 0) : 0;
$doing_ajax = (defined('DOING_AJAX') && DOING_AJAX) ? 1 : 0;
$user_id    = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
$roles      = [];
if (function_exists('wp_get_current_user')) {
    $u = wp_get_current_user();
    $roles = is_object($u) ? (array) $u->roles : [];
}
str_escribir_log("BOOT | URI={$uri} | admin={$is_admin} | ajax={$doing_ajax} | user={$user_id} | roles=" . json_encode($roles), 'DIAGNOSTICO');

// 3) Registrar captura de FATAL al finalizar la petición
if ( ! function_exists('str_diag_shutdown') ) {
    function str_diag_shutdown() {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $msg = sprintf('FATAL: %s in %s:%d | URI=%s',
                $err['message'], $err['file'], $err['line'], $uri
            );
            str_escribir_log($msg, 'DIAGNOSTICO');
        }
    }
}
register_shutdown_function('str_diag_shutdown');
