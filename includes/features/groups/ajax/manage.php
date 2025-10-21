<?php
// /includes/features/groups/ajax/manage.php
defined('ABSPATH') || exit;

/**
 * Utilidades pequeñas
 */
if (!function_exists('str_escribir_log')) {
    function str_escribir_log($mensaje, $origen = 'GRUPOS') {
        $ruta = defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH . 'debug-saas-torneos.log' : __DIR__ . '/debug-saas-torneos.log';
        if (!is_string($mensaje)) { $mensaje = print_r($mensaje, true); }
        @file_put_contents($ruta, sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $origen, $mensaje), FILE_APPEND | LOCK_EX);
    }
}

function str_groups_json_ok($data = []) {
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    wp_send_json_success($data);
}
function str_groups_json_error($data = [], $code = 400) {
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    wp_send_json_error($data, $code);
}

function str_groups_require_caps() {
    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        str_groups_json_error(['message' => 'Permisos insuficientes.'], 403);
    }
}

function str_groups_check_nonce() {
    $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce'])
            : (isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'str_nonce')) {
        str_groups_json_error(['message' => 'Nonce inválido.'], 401);
    }
}

/** Normaliza un campo relación (id/s, objetos) a array de IDs int */
function str_groups_normalize_ids($raw) {
    if (empty($raw)) return [];
    $out = [];
    if (is_array($raw)) {
        foreach ($raw as $item) {
            if (is_numeric($item)) $out[] = (int)$item;
            elseif (is_object($item) && isset($item->ID)) $out[] = (int)$item->ID;
            elseif (is_array($item) && isset($item['ID'])) $out[] = (int)$item['ID'];
        }
    } elseif (is_numeric($raw)) {
        $out[] = (int)$raw;
    } elseif (is_object($raw) && isset($raw->ID)) {
        $out[] = (int)$raw->ID;
    }
    return array_values(array_unique(array_filter($out)));
}

/** Devuelve letras libres A..Z para autogenerar nombre de grupo */
function str_groups_next_letter($comp_id) {
    $ids = get_posts([
        'post_type'      => 'grupo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . (int)$comp_id . '"',
            'compare' => 'LIKE',
        ]],
        'fields' => 'ids',
    ]);
    $usadas = [];
    foreach ($ids as $gid) {
        $n = function_exists('get_field') ? get_field('nombre_grupo', $gid) : get_post_meta($gid, 'nombre_grupo', true);
        if (!$n) {
            $t = get_the_title($gid);
            if (preg_match('~grupo\s+([A-Z0-9]+)~i', (string)$t, $m)) $n = strtoupper($m[1]);
        }
        if ($n) $usadas[] = strtoupper(trim($n));
    }
    $usadas = array_unique($usadas);

    foreach (range('A','Z') as $L) if (!in_array($L, $usadas, true)) return $L;
    for ($i=1; $i<=99; $i++) if (!in_array((string)$i, $usadas, true)) return (string)$i;
    return wp_generate_password(4, false);
}

/** Valida que la pareja pertenece al torneo (campo ACF torneo_asociado) */
function str_groups_pair_belongs_to_comp($pareja_id, $comp_id) {
    $raw = function_exists('get_field') ? get_field('torneo_asociado', $pareja_id) : get_post_meta($pareja_id, 'torneo_asociado', true);
    $ids = str_groups_normalize_ids($raw);
    return in_array((int)$comp_id, $ids, true);
}

/** Comprueba que el grupo pertenece al torneo */
function str_groups_group_belongs_to_comp($grupo_id, $comp_id) {
    $raw = function_exists('get_field') ? get_field('torneo_asociado', $grupo_id) : get_post_meta($grupo_id, 'torneo_asociado', true);
    $ids = str_groups_normalize_ids($raw);
    return in_array((int)$comp_id, $ids, true);
}

/**
 * ==============  ACCIONES AJAX
 */
add_action('wp_ajax_saas_grupo_crear',    'saas_grupo_crear');
add_action('wp_ajax_saas_grupo_asignar',  'saas_grupo_asignar');
add_action('wp_ajax_saas_grupo_quitar',   'saas_grupo_quitar');
// Alias compat
add_action('wp_ajax_str_grupo_crear',           'saas_grupo_crear');
add_action('wp_ajax_str_grupo_asignar',         'saas_grupo_asignar');
add_action('wp_ajax_str_grupo_quitar',          'saas_grupo_quitar');
add_action('wp_ajax_str_grupo_asignar_pareja',  'saas_grupo_asignar');
add_action('wp_ajax_str_grupo_quitar_pareja',   'saas_grupo_quitar');
// Eliminar grupo (core + alias)
add_action('wp_ajax_saas_grupo_eliminar', 'saas_grupo_eliminar');
add_action('wp_ajax_str_grupo_eliminar',  'saas_grupo_eliminar');
// Distribución (core + alias)
add_action('wp_ajax_saas_grupos_distribucion_preview', 'saas_grupos_distribucion_preview');
add_action('wp_ajax_saas_grupos_distribucion_aplicar', 'saas_grupos_distribucion_aplicar');
add_action('wp_ajax_str_grupos_distribucion_preview', 'saas_grupos_distribucion_preview');
add_action('wp_ajax_str_grupos_distribucion_aplicar', 'saas_grupos_distribucion_aplicar');

/** Crear un grupo */
function saas_grupo_crear() {
    str_groups_check_nonce();
    str_groups_require_caps();

    $comp_id = isset($_POST['competicion_id']) ? (int)$_POST['competicion_id'] : 0;
    $nombre  = isset($_POST['nombre']) ? sanitize_text_field($_POST['nombre']) : '';

    if (!$comp_id || get_post_type($comp_id) !== 'competicion') {
        str_groups_json_error(['message' => 'Competición inválida.']);
    }

    if ($nombre === '' || $nombre === null) $nombre = str_groups_next_letter($comp_id);

    $nombre_field = $nombre;
    if (preg_match('~^\s*grupo\s+~i', $nombre)) {
        $title = trim($nombre);
        if (preg_match('~^\s*grupo\s+(.+)$~i', $nombre, $m)) $nombre_field = trim($m[1]);
    } else {
        $title = 'Grupo ' . trim($nombre);
    }

    $post_id = wp_insert_post([
        'post_type'   => 'grupo',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($post_id) || !$post_id) {
        str_escribir_log('[GRUPOS:CREAR][ERROR] ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'ID=0'));
        str_groups_json_error(['message' => 'No se pudo crear el grupo.']);
    }

    if (function_exists('update_field')) {
        update_field('torneo_asociado', array($comp_id), $post_id);
        update_field('nombre_grupo', $nombre_field, $post_id);
        update_field('participantes_grupo', [], $post_id);
    } else {
        update_post_meta($post_id, 'torneo_asociado', array($comp_id));
        update_post_meta($post_id, 'nombre_grupo', $nombre_field);
        update_post_meta($post_id, 'participantes_grupo', []);
    }

    str_groups_json_ok([
        'message' => 'Grupo creado.',
        'grupo'   => [
            'id'      => $post_id,
            'title'   => get_the_title($post_id),
            'nombre'  => $nombre_field,
            'comp'    => $comp_id,
            'miembros'=> [],
        ]
    ]);
}

/** Asignar pareja */
function saas_grupo_asignar() {
    str_groups_check_nonce();
    str_groups_require_caps();

    $comp_id   = isset($_POST['competicion_id']) ? (int)$_POST['competicion_id'] : 0;
    $grupo_id  = isset($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 0;
    $pareja_id = isset($_POST['pareja_id']) ? (int)$_POST['pareja_id'] : 0;

    if (!$comp_id || !$grupo_id || !$pareja_id) {
        str_groups_json_error(['message' => 'Datos incompletos.']);
    }
    if (get_post_type($grupo_id) !== 'grupo' || get_post_status($grupo_id) !== 'publish') {
        str_groups_json_error(['message' => 'Grupo inválido.']);
    }
    if (get_post_type($pareja_id) !== 'pareja' || get_post_status($pareja_id) !== 'publish') {
        if (get_post_type($pareja_id) !== 'jugador_deportes') {
            str_groups_json_error(['message' => 'Pareja/Jugador inválido.']);
        }
    }
    if (!str_groups_group_belongs_to_comp($grupo_id, $comp_id)) {
        str_groups_json_error(['message' => 'El grupo no pertenece a esta competición.']);
    }
    if (get_post_type($pareja_id) === 'pareja' && !str_groups_pair_belongs_to_comp($pareja_id, $comp_id)) {
        str_groups_json_error(['message' => 'La pareja no está asociada a este torneo.']);
    }

    $raw = function_exists('get_field') ? get_field('participantes_grupo', $grupo_id)
                                        : get_post_meta($grupo_id, 'participantes_grupo', true);
    $ids = str_groups_normalize_ids($raw);
    if (!in_array($pareja_id, $ids, true)) $ids[] = $pareja_id;

    if (function_exists('update_field')) update_field('participantes_grupo', $ids, $grupo_id);
    else update_post_meta($grupo_id, 'participantes_grupo', $ids);

    str_groups_json_ok([
        'message' => 'Asignado.',
        'grupo_id'=> $grupo_id,
        'miembros'=> $ids,
    ]);
}

/** Quitar pareja */
function saas_grupo_quitar() {
    str_groups_check_nonce();
    str_groups_require_caps();

    $comp_id   = isset($_POST['competicion_id']) ? (int)$_POST['competicion_id'] : 0;
    $grupo_id  = isset($_POST['grupo_id'])       ? (int)$_POST['grupo_id']       : 0;
    $pareja_id = isset($_POST['pareja_id'])      ? (int)$_POST['pareja_id']      : 0;

    if (!$grupo_id || !$pareja_id) {
        str_groups_json_error(['message' => 'Datos incompletos (grupo/pareja).']);
    }

    if (!$comp_id && $grupo_id) {
        $raw = function_exists('get_field')
            ? get_field('torneo_asociado', $grupo_id)
            : get_post_meta($grupo_id, 'torneo_asociado', true);
        $ids = str_groups_normalize_ids($raw);
        if (count($ids) === 1) $comp_id = (int)$ids[0];
    }
    if (!$comp_id) str_groups_json_error(['message' => 'ID de competición no determinado.']);

    if (get_post_type($grupo_id) !== 'grupo' || get_post_status($grupo_id) !== 'publish') {
        str_groups_json_error(['message' => 'Grupo inválido.']);
    }
    if (!str_groups_group_belongs_to_comp($grupo_id, $comp_id)) {
        str_groups_json_error(['message' => 'El grupo no pertenece a esta competición.']);
    }

    $raw = function_exists('get_field')
        ? get_field('participantes_grupo', $grupo_id)
        : get_post_meta($grupo_id, 'participantes_grupo', true);

    $ids = str_groups_normalize_ids($raw);
    $ids = array_values(array_diff($ids, [(int)$pareja_id]));

    if (function_exists('update_field')) update_field('participantes_grupo', $ids, $grupo_id);
    else update_post_meta($grupo_id, 'participantes_grupo', $ids);

    str_groups_json_ok([
        'message'  => 'Quitado.',
        'grupo_id' => $grupo_id,
        'miembros' => $ids,
    ]);
}

/**
 * ===== Bootstrap RENOMBRAR GRUPO (Fase 1) =====
 */
(function () {
    $candidatos = [
        __DIR__ . '/grupo-renombrar.php',
        dirname(__DIR__) . '/grupo-renombrar.php',
        dirname(__DIR__, 2) . '/groups/grupo-renombrar.php',
    ];
    $cargado = false;
    foreach ($candidatos as $path) {
        if (file_exists($path)) {
            require_once $path;
            str_escribir_log('BOOT-AJAX-RENAME loaded: ' . $path, 'GROUPS:RENAME');
            $cargado = true;
            break;
        }
    }
    if (!$cargado) {
        str_escribir_log('BOOT-AJAX-MISSING grupo-renombrar.php | tried: ' . implode(' | ', $candidatos), 'GROUPS:RENAME');
    }

    if (function_exists('saas_grupo_renombrar')) {
        add_action('wp_ajax_saas_grupo_renombrar', 'saas_grupo_renombrar');
        add_action('wp_ajax_str_grupo_renombrar',  'saas_grupo_renombrar');
    } else {
        add_action('wp_ajax_saas_grupo_renombrar', function(){ str_groups_json_error(['message'=>'Handler renombrar no cargado.'], 500); });
        add_action('wp_ajax_str_grupo_renombrar',  function(){ str_groups_json_error(['message'=>'Handler renombrar no cargado.'], 500); });
    }
})();

// === Bootstrap DISTRIBUCIÓN (preview + aplicar) ===============================
$__str_grupos_dist_file = __DIR__ . '/grupos-distribucion.php';
if (file_exists($__str_grupos_dist_file)) {
    require_once $__str_grupos_dist_file;
} else {
    if (!function_exists('str_escribir_log')) {
        function str_escribir_log($m,$o='GROUPS:DIST'){ @file_put_contents(__DIR__.'/../../../../debug-saas-torneos.log', "[".date('Y-m-d H:i:s')."] [$o] $m\n", FILE_APPEND); }
    }
    str_escribir_log('BOOT-AJAX-MISSING grupos-distribucion.php');
}

// === Bootstrap ELIMINAR GRUPO ===============================================
$__str_grupo_eliminar_file = __DIR__ . '/grupo-eliminar.php';
if (file_exists($__str_grupo_eliminar_file)) {
    if (!function_exists('saas_grupo_eliminar')) {
        require_once $__str_grupo_eliminar_file;
        if (function_exists('str_escribir_log')) {
            str_escribir_log('BOOT-AJAX-DELETE loaded: ' . $__str_grupo_eliminar_file, 'GROUPS:DELETE');
        }
    }
} else {
    if (!function_exists('str_escribir_log')) {
        function str_escribir_log($m, $o='GRUPOS'){ @file_put_contents(__DIR__.'/../../../../debug-saas-torneos.log', "[".date('Y-m-d H:i:s')."] [$o] $m\n", FILE_APPEND); }
    }
    str_escribir_log('BOOT-AJAX-MISSING grupo-eliminar.php', 'GROUPS:DELETE');
}
add_action('wp_ajax_saas_grupo_eliminar', 'saas_grupo_eliminar');
add_action('wp_ajax_str_grupo_eliminar',  'saas_grupo_eliminar');


/* ────────────────────────────────────────────────────────────────
   MOUNT POINT FRONTEND PARA GRUPOS
   - Coloca <div id="str-gestion-grupos"></div> en el frontend de
     single "competicion" SI NO EXISTE ya en el HTML de la plantilla.
   - Con prioridad 5 (antes de la mayoría de scripts de footer).
   ──────────────────────────────────────────────────────────────── */
add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!is_singular('competicion')) return;

    // No podemos "comprobar" el DOM desde PHP, así que lo imprimimos siempre.
    echo '<div id="str-gestion-grupos"></div>';
    if (function_exists('str_escribir_log')) {
        str_escribir_log('Mount point impreso en wp_footer para single competicion.', 'GROUPS:MOUNT');
    }
}, 5);

/* (Opcional) Fallback: añadir también al final del contenido si el tema sí usa the_content */
add_filter('the_content', function ($content) {
    if (!is_singular('competicion')) return $content;
    // Si ya hay un contenedor similar en el contenido, no duplicamos
    if (strpos($content, 'id="str-gestion-grupos"') !== false) return $content;
    return $content . "\n\n" . '<div id="str-gestion-grupos"></div>';
}, 99);
