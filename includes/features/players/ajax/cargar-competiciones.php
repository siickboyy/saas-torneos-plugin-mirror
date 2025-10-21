<?php
// AJAX: Cargar competiciones jugadas por el jugador
// Antiguo: ajax-cargar-competiciones-jugador.php
// Este endpoint servirá para devolver las competiciones jugadas (filtradas por deporte y año).
// Por ahora es un esqueleto base, listo para conectar cuando esté implementada la lógica real.

add_action('wp_ajax_str_cargar_competiciones_jugador', 'str_cargar_competiciones_jugador');
add_action('wp_ajax_nopriv_str_cargar_competiciones_jugador', 'str_cargar_competiciones_jugador');

function str_cargar_competiciones_jugador() {
    // Validar parámetros recibidos
    $jugador_id = isset($_POST['jugador_id']) ? intval($_POST['jugador_id']) : 0;
    $deporte    = isset($_POST['deporte']) ? sanitize_text_field($_POST['deporte']) : '';
    $ano        = isset($_POST['ano']) ? sanitize_text_field($_POST['ano']) : '';

    // TODO: Aquí irá la consulta real a la BD para extraer las competiciones jugadas
    // según el $jugador_id, $deporte y $ano.

    // Por ahora, devolvemos datos simulados:
    $response = [
        [
            'competicion' => 'Torneo Madrid',
            'posicion'    => '3º grupo',
            'fecha'       => '27/09/25',
        ],
        [
            'competicion' => 'Torneo Barcelona',
            'posicion'    => 'Ganador',
            'fecha'       => '13/10/25',
        ],
    ];

    wp_send_json_success($response);
    exit;
}

// NOTA: Cuando la lógica real esté lista, aquí irá la consulta a los posts de tipo competición/torneo.
