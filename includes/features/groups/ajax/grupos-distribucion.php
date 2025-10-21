<?php
/**
 * AJAX Distribución de parejas en grupos (preview + aplicar)
 * Ruta: includes/features/groups/ajax/grupos-distribucion.php
 *
 * Acciones:
 *  - saas_grupos_distribucion_preview   (alias: str_grupos_distribucion_preview)
 *  - saas_grupos_distribucion_aplicar   (alias: str_grupos_distribucion_aplicar)
 *
 * Requisitos:
 *  - CPT: grupo, pareja
 *  - ACF en grupo: torneo_asociado (relationship id/array), participantes_grupo (array ids), nombre_grupo (opcional)
 *  - ACF en pareja: torneo_asociado (relationship con return object)  ← confirmado por el usuario
 *
 * Seguridad:
 *  - check_ajax_referer('str_nonce', '_ajax_nonce')
 *  - current_user_can('administrator') || current_user_can('cliente')
 */

if (!defined('ABSPATH')) { exit; }

/* ------------------------ Utilidades compartidas ------------------------ */

if (!function_exists('str_escribir_log')) {
    function str_escribir_log($mensaje, $origen = 'GROUPS:DIST'){
        $ruta = defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH . 'debug-saas-torneos.log' : __DIR__ . '/../../../../debug-saas-torneos.log';
        if (!is_string($mensaje)) { $mensaje = print_r($mensaje, true); }
        @file_put_contents($ruta, sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $origen, $mensaje), FILE_APPEND | LOCK_EX);
    }
}

/** Normaliza un field ACF (object/ids/array serializado) a array de IDs int */
function str_dist_normalize_ids($raw) {
    if (empty($raw)) return [];
    $out = [];
    if (is_array($raw)) {
        foreach ($raw as $item) {
            if (is_numeric($item)) {
                $out[] = (int)$item;
            } elseif (is_object($item) && isset($item->ID)) {
                $out[] = (int)$item->ID;
            } elseif (is_array($item) && isset($item['ID'])) {
                $out[] = (int)$item['ID'];
            }
        }
    } elseif (is_numeric($raw)) {
        $out[] = (int)$raw;
    } elseif (is_object($raw) && isset($raw->ID)) {
        $out[] = (int)$raw->ID;
    }
    return array_values(array_unique(array_filter($out)));
}

/** Valida nonce+permisos y devuelve array con comp_id y req_id */
function str_dist_guard_common() {
    if (!isset($_POST['_ajax_nonce'])) {
        wp_send_json_error(['message' => 'Nonce ausente.'], 401);
    }
    check_ajax_referer('str_nonce', '_ajax_nonce');

    if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('cliente'))) {
        wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
    }

    $comp_id = isset($_POST['competicion_id']) ? absint($_POST['competicion_id']) : 0;
    if (!$comp_id || get_post_type($comp_id) !== 'competicion') {
        wp_send_json_error(['message' => 'Competición inválida.'], 400);
    }

    $req_id = isset($_POST['req_id']) ? sanitize_text_field($_POST['req_id']) : wp_generate_uuid4();
    return [$comp_id, $req_id];
}

/** Carga contexto: grupos del torneo (con miembros) + parejas del torneo */
function str_dist_fetch_context($comp_id) {
    // Grupos del torneo (por ACF relationship LIKE para compat)
    $grupos = get_posts([
        'post_type'      => 'grupo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . (int)$comp_id . '"',
            'compare' => 'LIKE',
        ]],
        'orderby' => ['date' => 'ASC', 'ID' => 'ASC'],
    ]);

    $ctx_grupos = [];
    foreach ($grupos as $g) {
        $miembros_raw = function_exists('get_field')
            ? get_field('participantes_grupo', $g->ID, false)
            : get_post_meta($g->ID, 'participantes_grupo', true);
        $miembros = str_dist_normalize_ids($miembros_raw);

        $nombre = function_exists('get_field') ? get_field('nombre_grupo', $g->ID) : '';
        if (!$nombre) $nombre = $g->post_title ?: ('Grupo '.$g->ID);

        $ctx_grupos[] = [
            'id'       => (int)$g->ID,
            'nombre'   => (string)$nombre,
            'miembros' => $miembros, // ids de pareja o jugador
        ];
    }

    // Parejas del torneo (ACF pareja.torneo_asociado = OBJECT → normalizamos)
    $parejas_like = get_posts([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . (int)$comp_id . '"',
            'compare' => 'LIKE',
        ]],
        'fields'  => 'ids',
    ]);

    // Filtro de seguridad: de esas parejas, confirmar pertenencia leyendo el field (objeto)
    $parejas_comp = [];
    foreach ($parejas_like as $pid) {
        $raw = function_exists('get_field') ? get_field('torneo_asociado', $pid, false) : get_post_meta($pid, 'torneo_asociado', true);
        $ids = str_dist_normalize_ids($raw); // si return_format=object, caerá aquí (obj→id)
        if (in_array((int)$comp_id, $ids, true)) {
            $parejas_comp[] = (int)$pid;
        }
    }
    $parejas_comp = array_values(array_unique($parejas_comp));

    // Mapa miembros actuales por pareja_id → grupo_id (para recolocar controlado)
    $pair_to_group = [];
    foreach ($ctx_grupos as $g) {
        foreach ($g['miembros'] as $pid) {
            $pair_to_group[$pid] = (int)$g['id'];
        }
    }

    return [
        'grupos'         => $ctx_grupos,
        'parejas'        => $parejas_comp,
        'pair_to_group'  => $pair_to_group,
    ];
}

/** Algoritmo de reparto random estable con semilla */
function str_dist_shuffle_with_seed(array $arr, $seed = '') {
    if ($seed === '' || $seed === null) {
        shuffle($arr);
        return $arr;
    }
    // PRNG determinista simple (no criptográfico)
    $seed_str = (string)$seed;
    $hash = md5($seed_str, true);
    $state = unpack('N', substr($hash, 0, 4))[1];

    $result = $arr;
    for ($i = count($result) - 1; $i > 0; $i--) {
        // LCG
        $state = (1103515245 * $state + 12345) & 0x7fffffff;
        $j = $state % ($i + 1);
        $tmp = $result[$i];
        $result[$i] = $result[$j];
        $result[$j] = $tmp;
    }
    return $result;
}

/** Construye plan de distribución (no escribe BD) */
function str_dist_build_plan(array $ctx, array $params) {
    $policy       = isset($params['policy']) ? $params['policy'] : 'random';      // random | by_size
    $seed         = isset($params['seed']) ? (string)$params['seed'] : '';
    $target_size  = isset($params['target_size']) ? max(2, min(64, (int)$params['target_size'])) : 4;
    $recolocar    = !empty($params['recolocar']) ? true : false;

    $grupos  = $ctx['grupos'];
    $parejas = $ctx['parejas'];
    $p2g     = $ctx['pair_to_group'];

    $summary = [
        'groups'         => count($grupos),
        'pairs_total'    => count($parejas),
        'policy'         => $policy,
        'target_size'    => $target_size,
        'recolocar'      => $recolocar,
        'notes'          => [],
    ];

    if (empty($grupos)) {
        return ['plan' => [], 'summary' => $summary, 'error' => 'No hay grupos en esta competición. Crea grupos antes de distribuir.'];
    }
    if (empty($parejas)) {
        $summary['notes'][] = 'No hay parejas asociadas a esta competición.';
        // Plan vacío (no cambios)
        $plan = [];
        foreach ($grupos as $g) {
            $plan[] = [
                'grupo_id' => $g['id'],
                'before'   => $g['miembros'],
                'after'    => $g['miembros'],
            ];
        }
        return ['plan' => $plan, 'summary' => $summary];
    }

    // Punto de partida: o bien solo libres, o todas (si recolocar)
    $asignadas = array_keys($p2g);
    $libres    = array_values(array_diff($parejas, $asignadas));
    $pool      = $recolocar ? $parejas : $libres;

    // Orden del pool
    if ($policy === 'random') {
        $pool = str_dist_shuffle_with_seed($pool, $seed);
    } elseif ($policy === 'by_size') {
        // Podemos mantener el orden actual pero la visita de grupos intentará aproximar a target_size
        // (si quieres, aquí podrías ordenar por algún “seed” fijo también)
    }

    // Clonar estado actual si recolocar o no
    $after = [];
    foreach ($grupos as $g) {
        $after[$g['id']] = $recolocar ? [] : $g['miembros'];
    }

    if ($policy === 'random') {
        // Round-robin simple, repartiendo el pool sobre el array de grupos
        $gids = array_map(fn($g)=>$g['id'], $grupos);
        $gi = 0;
        foreach ($pool as $pid) {
            $target_gid = $gids[$gi % count($gids)];
            $after[$target_gid][] = $pid;
            $gi++;
        }

    } elseif ($policy === 'by_size') {
        // Intentar aproximar tamaños a target_size
        // 1) Si no recolocamos, calculamos capacidad “faltante” por grupo
        $needs = [];
        foreach ($grupos as $g) {
            $curr = $recolocar ? 0 : count($g['miembros']);
            $needs[$g['id']] = max(0, $target_size - $curr);
        }

        // 2) Asignar siguiendo “needs” (más hueco primero)
        foreach ($pool as $pid) {
            // escoger grupo con más necesidad actual
            arsort($needs); // mayor necesidad primero
            $chosen_gid = key($needs);
            $after[$chosen_gid][] = $pid;
            // actualizar necesidad
            $needs[$chosen_gid] = max(0, $needs[$chosen_gid] - 1);
        }
    }

    // Construir plan before/after
    $plan = [];
    foreach ($grupos as $g) {
        $gid = $g['id'];
        $plan[] = [
            'grupo_id' => $gid,
            'before'   => $g['miembros'],
            'after'    => array_values(array_unique($after[$gid] ?? [])),
        ];
    }

    return ['plan' => $plan, 'summary' => $summary];
}

/** Aplica plan a BD con rollback en caso de fallo */
function str_dist_apply_plan(array $plan) {
    $backup = [];
    foreach ($plan as $item) {
        $gid = (int)$item['grupo_id'];
        // lee estado actual por si hay que restaurar
        $raw = function_exists('get_field')
            ? get_field('participantes_grupo', $gid, false)
            : get_post_meta($gid, 'participantes_grupo', true);
        $backup[$gid] = str_dist_normalize_ids($raw);
    }

    try {
        foreach ($plan as $item) {
            $gid   = (int)$item['grupo_id'];
            $after = array_map('intval', (array)$item['after']);
            if (function_exists('update_field')) {
                if (false === update_field('participantes_grupo', $after, $gid)) {
                    throw new Exception('Fallo update_field participantes_grupo en grupo '.$gid);
                }
            } else {
                if (false === update_post_meta($gid, 'participantes_grupo', $after)) {
                    throw new Exception('Fallo update_post_meta participantes_grupo en grupo '.$gid);
                }
            }
        }
        return true;
    } catch (\Throwable $e) {
        // rollback
        foreach ($backup as $gid => $before) {
            if (function_exists('update_field')) {
                @update_field('participantes_grupo', $before, $gid);
            } else {
                @update_post_meta($gid, 'participantes_grupo', $before);
            }
        }
        str_escribir_log('[APPLY][ERROR] '.$e->getMessage());
        return false;
    }
}

/* --------------------------- Handlers AJAX --------------------------- */

/** PREVIEW */
function saas_grupos_distribucion_preview() {
    list($comp_id, $req_id) = str_dist_guard_common();

    $policy      = isset($_POST['policy']) ? sanitize_text_field($_POST['policy']) : 'random';
    $recolocar   = !empty($_POST['recolocar']) ? true : false;
    $target_size = isset($_POST['target_size']) ? (int)$_POST['target_size'] : 4;
    $seed        = isset($_POST['seed']) ? sanitize_text_field($_POST['seed']) : '';

    $ctx   = str_dist_fetch_context($comp_id);
    $built = str_dist_build_plan($ctx, [
        'policy'      => $policy,
        'recolocar'   => $recolocar,
        'target_size' => $target_size,
        'seed'        => $seed,
    ]);

    if (!empty($built['error'])) {
        wp_send_json_error([
            'message' => $built['error'],
            'summary' => $built['summary'],
            'req_id'  => $req_id,
        ], 400);
    }

    str_escribir_log('[PREVIEW] OK comp='.$comp_id.' policy='.$policy.' recolocar='.(int)$recolocar.' target='.$target_size.' seed='.$seed.' plan_items='.count($built['plan']).' | req_id='.$req_id);

    wp_send_json_success([
        'plan'    => $built['plan'],
        'summary' => $built['summary'],
        'req_id'  => $req_id,
    ]);
}

/** APLICAR */
function saas_grupos_distribucion_aplicar() {
    list($comp_id, $req_id) = str_dist_guard_common();

    $policy      = isset($_POST['policy']) ? sanitize_text_field($_POST['policy']) : 'random';
    $recolocar   = !empty($_POST['recolocar']) ? true : false;
    $target_size = isset($_POST['target_size']) ? (int)$_POST['target_size'] : 4;
    $seed        = isset($_POST['seed']) ? sanitize_text_field($_POST['seed']) : '';

    $ctx   = str_dist_fetch_context($comp_id);
    $built = str_dist_build_plan($ctx, [
        'policy'      => $policy,
        'recolocar'   => $recolocar,
        'target_size' => $target_size,
        'seed'        => $seed,
    ]);

    if (!empty($built['error'])) {
        wp_send_json_error([
            'message' => $built['error'],
            'summary' => $built['summary'],
            'req_id'  => $req_id,
        ], 400);
    }

    $ok = str_dist_apply_plan($built['plan']);
    if (!$ok) {
        wp_send_json_error([
            'message' => 'No se pudo aplicar la distribución (se revirtió el estado).',
            'req_id'  => $req_id,
        ], 500);
    }

    str_escribir_log('[APLICAR] OK comp='.$comp_id.' policy='.$policy.' recolocar='.(int)$recolocar.' target='.$target_size.' seed='.$seed.' plan_items='.count($built['plan']).' | req_id='.$req_id);

    wp_send_json_success([
        'message' => 'Distribución aplicada correctamente.',
        'req_id'  => $req_id,
    ]);
}
