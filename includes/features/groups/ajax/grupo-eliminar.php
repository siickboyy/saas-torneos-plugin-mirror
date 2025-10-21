<?php
/**
 * Handler AJAX: Eliminar grupo (envía a papelera)
 * Alias compatible: str_grupo_eliminar  → apunta a saas_grupo_eliminar
 *
 * Seguridad:
 *   - Nonce: check_ajax_referer('str_nonce', '_ajax_nonce')
 *   - Permisos: admin o rol 'cliente' (o bien delete_post sobre el grupo)
 *
 * Comportamiento:
 *   - Si el grupo tiene participantes, se vacía el ACF 'participantes_grupo'
 *     antes de mandarlo a la papelera.
 *   - Respuesta JSON con éxito o error detallado.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('saas_grupo_eliminar')) {
    function saas_grupo_eliminar() {

        // Helpers de logging (mismo formato que el resto del módulo)
        if (!function_exists('str_escribir_log')) {
            function str_escribir_log($m, $o='GROUPS:DELETE') {
                $ruta = defined('STR_PLUGIN_PATH') ? STR_PLUGIN_PATH . 'debug-saas-torneos.log' : __DIR__ . '/debug-saas-torneos.log';
                if (!is_string($m)) { $m = print_r($m, true); }
                @file_put_contents($ruta, "[".date('Y-m-d H:i:s')."] [$o] $m\n", FILE_APPEND);
            }
        }

        try {
            // === Nonce ===
            if (!isset($_POST['_ajax_nonce'])) {
                str_escribir_log('Nonce ausente');
                wp_send_json_error(['message' => 'Nonce ausente.'], 400);
            }
            check_ajax_referer('str_nonce', '_ajax_nonce');

            $comp_id  = isset($_POST['competicion_id']) ? absint($_POST['competicion_id']) : 0;
            $grupo_id = isset($_POST['grupo_id'])       ? absint($_POST['grupo_id'])       : 0;

            if (!$grupo_id) {
                wp_send_json_error(['message' => 'ID de grupo inválido.'], 400);
            }

            $grupo = get_post($grupo_id);
            if (!$grupo || $grupo->post_type !== 'grupo') {
                wp_send_json_error(['message' => 'El post indicado no es un grupo válido.'], 404);
            }

            // === Permisos ===
            $ok_caps = (is_user_logged_in() && (current_user_can('administrator') || current_user_can('cliente'))) || current_user_can('delete_post', $grupo_id);
            if (!$ok_caps) {
                wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
            }

            // === Si pasaron competicion_id, validamos pertenencia ===
            if ($comp_id) {
                // Reutilizamos helper si existe
                $belongs = true;
                if (function_exists('get_field')) {
                    $raw = get_field('torneo_asociado', $grupo_id);
                } else {
                    $raw = get_post_meta($grupo_id, 'torneo_asociado', true);
                }

                // Normaliza a array de IDs
                $ids = [];
                if (!empty($raw)) {
                    if (is_array($raw)) {
                        foreach ($raw as $it) {
                            if (is_numeric($it))        $ids[] = (int)$it;
                            elseif (is_array($it) && isset($it['ID'])) $ids[] = (int)$it['ID'];
                            elseif (is_object($it) && isset($it->ID))  $ids[] = (int)$it->ID;
                        }
                    } elseif (is_numeric($raw)) {
                        $ids[] = (int)$raw;
                    }
                }
                $belongs = in_array($comp_id, array_unique($ids), true);

                if (!$belongs) {
                    wp_send_json_error(['message' => 'El grupo no pertenece a esta competición.'], 400);
                }
            }

            // === Vaciamos participantes (si los hubiera) para dejar todo consistente ===
            $participantes = function_exists('get_field')
                ? get_field('participantes_grupo', $grupo_id, false)
                : get_post_meta($grupo_id, 'participantes_grupo', true);

            if (!empty($participantes)) {
                if (function_exists('update_field')) {
                    @update_field('participantes_grupo', [], $grupo_id);
                } else {
                    update_post_meta($grupo_id, 'participantes_grupo', []);
                }
            }

            // === Papelera (idempotente) ===
            $status = get_post_status($grupo_id);
            if ($status === 'trash') {
                str_escribir_log("Ya estaba en papelera grupo_id=$grupo_id");
            } else {
                $trashed = wp_trash_post($grupo_id);
                if (!$trashed || is_wp_error($trashed)) {
                    $msg = is_wp_error($trashed) ? $trashed->get_error_message() : 'Error desconocido en wp_trash_post.';
                    str_escribir_log("ERROR al enviar a papelera grupo_id=$grupo_id | $msg");
                    wp_send_json_error(['message' => 'No se pudo eliminar el grupo.'], 400);
                }
            }

            str_escribir_log("OK eliminado (papelera) grupo_id=$grupo_id comp_id=$comp_id actor=".get_current_user_id());

            wp_send_json_success([
                'message'  => 'Grupo eliminado correctamente.',
                'grupo_id' => $grupo_id,
                'comp_id'  => $comp_id,
            ]);

        } catch (\Throwable $e) {
            str_escribir_log('EXCEPTION '.$e->getMessage());
            wp_send_json_error(['message' => 'Excepción al eliminar el grupo.'], 500);
        }
    }
}
