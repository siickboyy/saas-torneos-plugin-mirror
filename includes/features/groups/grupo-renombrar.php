<?php
/**
 * Handler AJAX: Renombrar grupo
 * Acción alias: str_grupo_renombrar  → apunta a saas_grupo_renombrar
 * Seguridad: check_ajax_referer('str_nonce', '_ajax_nonce')
 * Permisos: usuario con capacidad para editar el post del grupo
 * Logs: [GROUPS:RENAME]
 */

if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists('saas_grupo_renombrar') ) {
    function saas_grupo_renombrar() {
        try {
            // Valida nonce (ajustar la clave si tu front usa otra)
            if ( ! isset($_POST['_ajax_nonce']) ) {
                wp_send_json_error(['msg' => 'Nonce ausente.'], 400);
            }
            check_ajax_referer('str_nonce', '_ajax_nonce');

            $grupo_id        = isset($_POST['grupo_id']) ? absint($_POST['grupo_id']) : 0;
            $nuevo_nombre    = isset($_POST['nombre']) ? trim(wp_strip_all_tags($_POST['nombre'])) : '';
            $competicion_id  = isset($_POST['competicion_id']) ? absint($_POST['competicion_id']) : 0;

            if ( ! $grupo_id ) {
                wp_send_json_error(['msg' => 'ID de grupo inválido.'], 400);
            }

            $grupo = get_post($grupo_id);
            if ( ! $grupo || $grupo->post_type !== 'grupo' ) {
                wp_send_json_error(['msg' => 'El post indicado no es un grupo válido.'], 404);
            }

            // Permisos mínimos: poder editar ese post
            if ( ! current_user_can('edit_post', $grupo_id) ) {
                wp_send_json_error(['msg' => 'Permisos insuficientes.'], 403);
            }

            // Normaliza nombre
            $nuevo_nombre = $nuevo_nombre !== '' ? $nuevo_nombre : $grupo->post_title;

            // (Opcional) Evitar duplicados por competición si tenemos su ID
            if ( $competicion_id ) {
                $siblings = get_posts([
                    'post_type'      => 'grupo',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'post_parent'    => $competicion_id,
                    'fields'         => 'ids',
                    'exclude'        => [$grupo_id],
                ]);

                if ( ! is_wp_error($siblings) && $siblings ) {
                    foreach ($siblings as $sib_id) {
                        $sib = get_post($sib_id);
                        if ( $sib && strcasecmp(trim($sib->post_title), trim($nuevo_nombre)) === 0 ) {
                            wp_send_json_error(['msg' => 'Ya existe otro grupo con ese nombre en esta competición.'], 409);
                        }
                    }
                }
            }

            // Actualiza título del post
            $update = wp_update_post([
                'ID'         => $grupo_id,
                'post_title' => $nuevo_nombre,
            ], true);

            if ( is_wp_error($update) ) {
                error_log('[GROUPS:RENAME] Error wp_update_post grupo_id='.$grupo_id.' → '.$update->get_error_message());
                wp_send_json_error(['msg' => 'No se pudo actualizar el nombre del grupo.'], 500);
            }

            // Si usas ACF "nombre_grupo", mantenlo sincronizado (no es obligatorio)
            if ( function_exists('update_field') && get_field('nombre_grupo', $grupo_id, false) !== null ) {
                @update_field('nombre_grupo', $nuevo_nombre, $grupo_id);
            }

            // Log OK
            $actor = get_current_user_id();
            error_log('[GROUPS:RENAME] OK grupo_id='.$grupo_id.' nuevo_nombre="'.$nuevo_nombre.'" actor='.$actor.' comp_id='.$competicion_id);

            wp_send_json_success([
                'grupo_id'   => $grupo_id,
                'nombre'     => $nuevo_nombre,
                'comp_id'    => $competicion_id,
                'message'    => 'Grupo renombrado correctamente.',
            ]);
        } catch (\Throwable $e) {
            error_log('[GROUPS:RENAME] EXCEPTION '.$e->getMessage());
            wp_send_json_error(['msg' => 'Excepción no controlada al renombrar el grupo.'], 500);
        }
    }
}
