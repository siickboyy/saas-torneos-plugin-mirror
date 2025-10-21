<?php
// Antiguo /includes/ajax/ajax-guardar-resultado.php

add_action('wp_ajax_str_guardar_resultado', 'str_guardar_resultado_callback');
add_action('wp_ajax_nopriv_str_guardar_resultado', 'str_guardar_resultado_callback'); // Por si quieres dejarlo abierto a no logueados (NO RECOMENDADO)

// Función de logs global
if (!function_exists('str_escribir_log')) {
    function str_escribir_log($mensaje, $origen = '') {
        $ruta_log = WP_PLUGIN_DIR . '/saas-torneos-de-raqueta/debug-saas-torneos.log';
        $timestamp = date('[Y-m-d H:i:s]');
        $origen = $origen ? "[$origen]" : '';
        if (is_array($mensaje) || is_object($mensaje)) {
            $mensaje = print_r($mensaje, true);
        }
        $linea = "$timestamp $origen $mensaje" . PHP_EOL;
        file_put_contents($ruta_log, $linea, FILE_APPEND);
    }
}

function str_guardar_resultado_callback() {
    str_escribir_log($_POST, 'AJAX [Recibido POST]');

    $partido_id = isset($_POST['partido_id']) ? intval($_POST['partido_id']) : 0;
    $sets = isset($_POST['sets']) ? json_decode(stripslashes($_POST['sets']), true) : [];
    $current_user_id = get_current_user_id();

    str_escribir_log([
        'partido_id' => $partido_id,
        'sets' => $sets,
        'current_user_id' => $current_user_id
    ], 'AJAX [Parsed POST]');

    // Validaciones básicas
    if (!$partido_id || !$sets || !is_array($sets)) {
        str_escribir_log([
            'msg' => "Guardado resultado fallido: datos no válidos.",
            'partido_id' => $partido_id,
            'sets' => $sets
        ], 'AJAX [Validación datos]');
        wp_send_json_error('Datos no válidos');
    }

    // Comprobar que el partido existe
    $partido = get_post($partido_id);
    if (!$partido || $partido->post_type !== 'partido') {
        str_escribir_log("Guardado resultado fallido: partido inexistente. partido_id=$partido_id", 'AJAX [Validación partido]');
        wp_send_json_error('Partido no encontrado');
    }

    // === DEBUG: MOSTRAR CAMPOS PRINCIPALES DEL PARTIDO ===
    $estado = get_field('estado', $partido_id);
    $tipo_partido = get_field('tipo_partido', $partido_id);
    $competicion_padel = get_field('competicion_padel', $partido_id);
    str_escribir_log([
        'estado' => $estado,
        'tipo_partido' => $tipo_partido,
        'competicion_padel' => $competicion_padel
    ], 'AJAX [Campos principales partido]');

    // Comprobar permisos: solo jugadores implicados, organizador o admin pueden guardar resultado
    $puede_editar = false;
    if ($current_user_id) {
        if ($tipo_partido === 'parejas') {
            $p1 = get_field('pareja_1', $partido_id);
            $p2 = get_field('pareja_2', $partido_id);
            // Aquí podrías comprobar si el usuario pertenece a alguna pareja (a mejorar)
            $puede_editar = true; // Simplificado para test
        } else {
            $j1 = get_field('jugador_1', $partido_id);
            $j2 = get_field('jugador_2', $partido_id);
            if ($current_user_id == $j1 || $current_user_id == $j2) $puede_editar = true;
        }
        if (current_user_can('administrator') || current_user_can('manage_options')) $puede_editar = true;
    }
    // También permitir al organizador (cliente), añade tu lógica aquí

    str_escribir_log([
        'partido_id' => $partido_id,
        'puede_editar' => $puede_editar,
        'user_id' => $current_user_id
    ], 'AJAX [Permisos edición]');

    if (!$puede_editar) {
        str_escribir_log("Guardado resultado fallido: usuario sin permiso. partido_id=$partido_id, user_id=$current_user_id", 'AJAX [Permiso]');
        wp_send_json_error('No tienes permiso para editar este partido');
    }

    // Validar estado del partido (bajar todo a minúsculas para robustez)
    if (!in_array(strtolower($estado), ['pendiente', 'resultado propuesto', 'pendiente de validación'])) {
        str_escribir_log("Guardado resultado fallido: estado no editable ($estado). partido_id=$partido_id", 'AJAX [Estado no editable]');
        wp_send_json_error('Este partido no se puede editar');
    }

    // ------------------------
    // VALIDACIÓN DE SETS (LÍMITES)
    // ------------------------
    $min_juegos = 0;
    $max_juegos = 7; // Cambia este valor si necesitas otro límite

    foreach ($sets as $idx => $set) {
        // Cambios en los nombres de los subcampos para pádel
        $j1 = isset($set['juegos_equipo_1']) ? intval($set['juegos_equipo_1']) : 0;
        $j2 = isset($set['juegos_equipo_2']) ? intval($set['juegos_equipo_2']) : 0;

        if ($j1 < $min_juegos || $j1 > $max_juegos || $j2 < $min_juegos || $j2 > $max_juegos) {
            str_escribir_log([
                'msg' => "Guardado resultado fallido: juegos fuera de rango en set $idx",
                'j1' => $j1,
                'j2' => $j2,
                'partido_id' => $partido_id
            ], 'AJAX [Límites juegos]');
            wp_send_json_error('El número de juegos por set debe estar entre 0 y 7.');
        }
    }

    // Log antes de guardar ACF
    str_escribir_log([
        'partido_id' => $partido_id,
        'sets_guardar' => $sets
    ], 'AJAX [Antes de update_field resultado_padel]');

    // Guardar resultado en ACF (CAMBIO: resultado_padel)
    update_field('resultado_padel', $sets, $partido_id);

    // Cambiar estado
    $nuevo_estado = 'Resultado propuesto';
    update_field('estado', $nuevo_estado, $partido_id);

    // Guardar usuario que propone
    update_field('propuesto_por', $current_user_id, $partido_id);

    // Log final después de guardar
    str_escribir_log([
        'msg' => "Resultado propuesto guardado",
        'partido_id' => $partido_id,
        'user_id' => $current_user_id,
        'sets' => $sets
    ], 'AJAX [Resultado guardado OK]');

    wp_send_json_success('Resultado guardado. Ahora debe validarlo el rival o el organizador.');
}
