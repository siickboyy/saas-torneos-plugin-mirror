<?php
// antiguo includes/ajax/ajax-buscar-jugadores.php

add_action('wp_ajax_str_buscar_jugadores', 'str_ajax_buscar_jugadores');
function str_ajax_buscar_jugadores() {
    // Seguridad
    check_ajax_referer('str_parejas_multiseleccion_nonce', 'nonce');

    $torneo_id = intval($_POST['torneo_id']);
    $nombre    = sanitize_text_field($_POST['nombre'] ?? '');
    $apellido  = sanitize_text_field($_POST['apellido'] ?? '');
    $email     = sanitize_text_field($_POST['email'] ?? '');

    // --- LOG DATOS RECIBIDOS ---
    if (function_exists('str_escribir_log')) str_escribir_log("[AJAX buscar_jugadores] INICIO", 'AJAX');
    if (function_exists('str_escribir_log')) str_escribir_log("POST: " . print_r($_POST, true), 'AJAX');

    // 1. Construcción de la query principal de jugadores del torneo
    $args = array(
        'post_type'      => 'jugador_deportes',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'torneos_asociados',
                'value'   => '"' . $torneo_id . '"',
                'compare' => 'LIKE'
            )
        ),
    );
    if ($nombre) {
        $args['meta_query'][] = array(
            'key'     => 'nombre',
            'value'   => $nombre,
            'compare' => 'LIKE'
        );
    }
    if ($apellido) {
        $args['meta_query'][] = array(
            'key'     => 'apellido',
            'value'   => $apellido,
            'compare' => 'LIKE'
        );
    }
    if ($email) {
        $args['meta_query'][] = array(
            'key'     => 'email',
            'value'   => $email,
            'compare' => 'LIKE'
        );
    }
    if (function_exists('str_escribir_log')) str_escribir_log("ARGS JUGADORES: " . print_r($args, true), 'AJAX');

    // 2. Recopilar IDs de jugadores ya emparejados en este torneo
    $jugadores_ocupados = array();
    $parejas_args = array(
        'post_type'      => 'pareja',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'     => 'torneo_asociado',
                'value'   => '"' . $torneo_id . '"',
                'compare' => 'LIKE'
            )
        ),
    );
    $parejas = get_posts($parejas_args);

    if (function_exists('str_escribir_log')) str_escribir_log("[AJAX buscar_jugadores] Parejas encontradas: " . count($parejas), 'AJAX');

    foreach ($parejas as $pareja) {
        $titulo_pareja = $pareja->post_title;
        $pareja_id = $pareja->ID;

        // --- LOG valores raw ACF de la pareja
        $j1_raw = get_field('jugador_1', $pareja_id);
        $j2_raw = get_field('jugador_2', $pareja_id);
        $torneo_raw = get_field('torneo_asociado', $pareja_id);

        if (function_exists('str_escribir_log')) {
            str_escribir_log("PAREJA: ID=$pareja_id, TITULO=$titulo_pareja", 'AJAX');
            str_escribir_log("jugador_1 (raw): " . print_r($j1_raw, true), 'AJAX');
            str_escribir_log("jugador_2 (raw): " . print_r($j2_raw, true), 'AJAX');
            str_escribir_log("torneo_asociado (raw): " . print_r($torneo_raw, true), 'AJAX');
        }

        // Normalizar jugador_1 y jugador_2 (soportar ID simple, array de IDs, array de objetos, objeto WP_Post, array de WP_Post)
        foreach (['jugador_1' => $j1_raw, 'jugador_2' => $j2_raw] as $campo => $val) {
            if (empty($val)) continue;
            if (is_array($val)) {
                foreach ($val as $item) {
                    if (is_numeric($item)) {
                        $jugadores_ocupados[] = intval($item);
                    } elseif (is_object($item) && isset($item->ID)) {
                        $jugadores_ocupados[] = intval($item->ID);
                    } elseif (is_array($item) && isset($item['ID'])) {
                        $jugadores_ocupados[] = intval($item['ID']);
                    }
                }
            } elseif (is_numeric($val)) {
                $jugadores_ocupados[] = intval($val);
            } elseif (is_object($val) && isset($val->ID)) {
                $jugadores_ocupados[] = intval($val->ID);
            }
        }
    }

    // Limpiar duplicados y valores vacíos
    $jugadores_ocupados = array_unique(array_filter($jugadores_ocupados));
    if (function_exists('str_escribir_log')) str_escribir_log("JUGADORES OCUPADOS FINAL (IDs): " . print_r($jugadores_ocupados, true), 'AJAX');

    // 3. Query principal de jugadores
    $jugadores_query = new WP_Query($args);
    $jugadores = array();

    if (function_exists('str_escribir_log')) str_escribir_log("Nº jugadores encontrados: " . $jugadores_query->found_posts, 'AJAX');

    if ($jugadores_query->have_posts()) {
        while ($jugadores_query->have_posts()) {
            $jugadores_query->the_post();
            $id = get_the_ID();
            $nombre = get_field('nombre');
            $apellido = get_field('apellido');
            $email = get_field('email');

            if (in_array($id, $jugadores_ocupados)) {
                if (function_exists('str_escribir_log')) {
                    str_escribir_log("EXCLUIDO por estar ocupado: $id - $nombre $apellido", 'AJAX');
                }
                continue;
            }
            $jugadores[] = array(
                'ID'      => $id,
                'nombre'  => $nombre,
                'apellido'=> $apellido,
                'email'   => $email
            );
        }
        wp_reset_postdata();
    }

    if (function_exists('str_escribir_log')) {
        str_escribir_log("JUGADORES DISPONIBLES PARA FRONTEND: " . print_r($jugadores, true), 'AJAX');
    }

    wp_send_json_success(array(
        'jugadores' => $jugadores
    ));
}
