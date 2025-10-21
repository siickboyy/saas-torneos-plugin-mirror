<?php
// /includes/features/groups/ajax/grupo-crear.php
defined('ABSPATH') || exit;

/**
 * Registrar la acción AJAX (solo usuarios logueados).
 * El JS llama a action: 'saas_grupo_crear'
 */
add_action('wp_ajax_saas_grupo_crear', 'saas_grupo_crear');

/**
 * Helpers mínimos (con guardas para no redeclarar si en el futuro
 * incluyes otros archivos que definan lo mismo).
 */
if (!function_exists('str_groups__json_ok')) {
    function str_groups__json_ok($data = []) {
        if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
        wp_send_json_success($data);
    }
}
if (!function_exists('str_groups__json_error')) {
    function str_groups__json_error($data = [], $code = 400) {
        if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
        wp_send_json_error($data, $code);
    }
}
if (!function_exists('str_groups__require_caps')) {
    function str_groups__require_caps() {
        if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
            str_groups__json_error(['message' => 'Permisos insuficientes.'], 403);
        }
    }
}
if (!function_exists('str_groups__check_nonce')) {
    function str_groups__check_nonce() {
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce'])
                 : (isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '');
        if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
            str_groups__json_error(['message' => 'Nonce inválido.'], 401);
        }
    }
}
if (!function_exists('str_groups__next_letter')) {
    /**
     * Devuelve el siguiente identificador disponible: A..Z, luego 1..99
     */
    function str_groups__next_letter($comp_id) {
        $args = [
            'post_type'      => 'grupo',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => 'torneo_asociado',
                'value'   => '"' . (int)$comp_id . '"',
                'compare' => 'LIKE',
            ]],
        ];
        $ids = get_posts($args);
        $usadas = [];
        foreach ($ids as $gid) {
            $nombre = function_exists('get_field') ? get_field('nombre_grupo', $gid) : get_post_meta($gid, 'nombre_grupo', true);
            if (!$nombre) {
                $t = get_the_title($gid);
                if (preg_match('~grupo\s+([A-Z0-9]+)~i', (string)$t, $m)) {
                    $nombre = strtoupper($m[1]);
                }
            }
            if ($nombre) { $usadas[] = strtoupper(trim($nombre)); }
        }
        $usadas = array_unique($usadas);
        foreach (range('A','Z') as $L) {
            if (!in_array($L, $usadas, true)) return $L;
        }
        for ($i=1; $i<=99; $i++) {
            if (!in_array((string)$i, $usadas, true)) return (string)$i;
        }
        return wp_generate_password(4, false);
    }
}

/**
 * Controlador: crear grupo
 * POST:
 *  - competicion_id (int)  ← obligatorio
 *  - nombre (string)       ← opcional (si viene "Grupo B", guardamos título "Grupo B" y ACF nombre="B")
 */
if (!function_exists('saas_grupo_crear')) {
    function saas_grupo_crear() {
        str_groups__check_nonce();
        str_groups__require_caps();

        $comp_id = isset($_POST['competicion_id']) ? (int) $_POST['competicion_id'] : 0;
        $nombre  = isset($_POST['nombre']) ? sanitize_text_field($_POST['nombre']) : '';

        if (!$comp_id || get_post_type($comp_id) !== 'competicion') {
            str_groups__json_error(['message' => 'Competición inválida.']);
        }

        // Autogenerar identificador si no llega nombre
        if ($nombre === '' || $nombre === null) {
            $nombre = str_groups__next_letter($comp_id); // A, B, C...
        }

        // Construir TÍTULO y valor para el ACF 'nombre_grupo' evitando "Grupo Grupo X"
        $nombre_field = $nombre;
        if (preg_match('~^\s*grupo\s+(.+)$~i', $nombre, $m)) {
            // Usuario pasó "Grupo B" → título "Grupo B", ACF nombre="B"
            $title        = 'Grupo ' . trim($m[1]);
            $nombre_field = trim($m[1]);
        } else {
            // Usuario pasó "B" → título "Grupo B", ACF nombre="B"
            $title        = 'Grupo ' . trim($nombre);
            $nombre_field = trim($nombre);
        }

        // Crear post grupo
        $post_id = wp_insert_post([
            'post_type'   => 'grupo',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            $msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Error desconocido';
            str_groups__json_error(['message' => 'No se pudo crear el grupo.', 'error' => $msg]);
        }

        // Guardar ACF/meta (compatibles con tu definición)
        if (function_exists('update_field')) {
            update_field('torneo_asociado', [$comp_id], $post_id); // relationship (return id)
            update_field('nombre_grupo', $nombre_field, $post_id);
            update_field('participantes_grupo', [], $post_id);     // vacío al crear
        } else {
            update_post_meta($post_id, 'torneo_asociado', [$comp_id]);
            update_post_meta($post_id, 'nombre_grupo', $nombre_field);
            update_post_meta($post_id, 'participantes_grupo', []);
        }

        str_groups__json_ok([
            'message' => 'Grupo creado.',
            'grupo'   => [
                'id'     => $post_id,
                'title'  => get_the_title($post_id),
                'nombre' => $nombre_field,
                'comp'   => $comp_id,
            ],
        ]);
    }
}
