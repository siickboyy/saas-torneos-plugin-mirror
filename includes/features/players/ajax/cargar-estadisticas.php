<?php
// AJAX: Cargar estadísticas del jugador para su perfil
// Antiguo: ajax-cargar-estadisticas-jugador.php
// Este endpoint servirá para devolver estadísticas de partidos según jugador y deporte. 
// Por ahora es un esqueleto base, listo para conectar cuando esté implementada la lógica de partidos.

add_action('wp_ajax_str_cargar_estadisticas_jugador', 'str_cargar_estadisticas_jugador');
add_action('wp_ajax_nopriv_str_cargar_estadisticas_jugador', 'str_cargar_estadisticas_jugador');

function str_cargar_estadisticas_jugador() {
    // Validar parámetros recibidos (ID de jugador y deporte)
    $jugador_id = isset($_POST['jugador_id']) ? intval($_POST['jugador_id']) : 0;
    $deporte    = isset($_POST['deporte']) ? sanitize_text_field($_POST['deporte']) : '';

    // TODO: Añadir aquí la lógica real para consultar partidos y calcular estadísticas:
    // partidos jugados, ganados, perdidos, racha, %...
    // Usar el $jugador_id y el $deporte para filtrar los partidos.

    // Devolver un ejemplo de datos simulados (por ahora):
    $response = [
        'jugados'         => 0,
        'ganados'         => 0,
        'ganados_pct'     => 0,
        'perdidos'        => 0,
        'perdidos_pct'    => 0,
        'racha_ganados'   => 0,
        'racha_ganados_pct' => 0,
        'racha_perdidos'  => 0,
        'racha_perdidos_pct' => 0,
    ];

    wp_send_json_success($response);
    exit;
}

// NOTA: Cuando la sección de partidos esté lista, aquí irá toda la consulta real.
