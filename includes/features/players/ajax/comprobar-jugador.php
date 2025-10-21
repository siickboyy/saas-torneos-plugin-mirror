<?php
/** Antiguo /includes/ajax/ajax-comprobar-jugador.php
 * AJAX: Comprobar si un jugador existe por email o por nombre+apellidos (sin distinguir mayúsculas/minúsculas)
 * Utilizado por el JS del formulario para mostrar aviso en tiempo real
 */

// Hook AJAX para usuarios logueados y visitantes
add_action('wp_ajax_str_comprobar_jugador', 'str_comprobar_jugador');
add_action('wp_ajax_nopriv_str_comprobar_jugador', 'str_comprobar_jugador');

function str_comprobar_jugador() {
    // Recoge y sanitiza los parámetros recibidos
    $nombre    = isset($_POST['nombre'])    ? sanitize_text_field($_POST['nombre']) : '';
    $apellidos = isset($_POST['apellidos']) ? sanitize_text_field($_POST['apellidos']) : '';
    $email     = isset($_POST['email'])     ? sanitize_email($_POST['email']) : '';

    $existe = false;

    // Si hay email, busca por ese campo (ignora mayúsculas/minúsculas)
    if ($email) {
        $args = [
            'post_type' => 'jugador_deportes',
            'meta_query' => [[
                'key' => 'email',
                'value' => $email,
                'compare' => 'LIKE'
            ]],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ];
        $res = get_posts($args);
        if ($res && count($res) > 0) {
            $existe = true;
        }
    }
    // Si no hay email pero sí nombre y apellidos, busca por combinación
    if (!$existe && $nombre && $apellidos) {
        // Normaliza a minúsculas y quita espacios para comparar
        $nombre_buscar    = strtolower(str_replace(' ', '', $nombre));
        $apellidos_buscar = strtolower(str_replace(' ', '', $apellidos));

        $args = [
            'post_type' => 'jugador_deportes',
            'posts_per_page' => 50, // Por si hay muchos
            'post_status' => 'publish',
            'fields' => 'ids',
        ];
        $posts = get_posts($args);
        foreach ($posts as $pid) {
            $nom = get_field('nombre', $pid);
            $ape = get_field('apellido', $pid);
            $nom = strtolower(str_replace(' ', '', $nom));
            $ape = strtolower(str_replace(' ', '', $ape));
            if ($nom === $nombre_buscar && $ape === $apellidos_buscar) {
                $existe = true;
                break;
            }
        }
    }
    // Devuelve el resultado al JS del formulario
    wp_send_json(['existe' => $existe]);
}
