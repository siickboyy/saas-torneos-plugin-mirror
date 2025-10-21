<?php
// /includes/functions/login-redirect.php

add_filter('login_redirect', 'str_login_redirect_jugador_individual', 20, 3);

function str_login_redirect_jugador_individual($redirect_to, $request, $user) {
    if (!isset($user->roles) || !is_array($user->roles)) {
        return $redirect_to;
    }

    if (in_array('jugador', $user->roles)) {
        // Buscar el CPT "jugador_deportes" con el campo user_id = $user->ID
        $jugador_query = new WP_Query([
            'post_type'      => 'jugador_deportes',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'user_id',
                    'value' => $user->ID,
                    'compare' => '='
                ]
            ]
        ]);

        if ($jugador_query->have_posts()) {
            $jugador_post = $jugador_query->posts[0];
            $url_jugador = get_permalink($jugador_post->ID);
            if ($url_jugador) {
                return $url_jugador;
            }
        }

        // Si no tiene CPT, fallback a página de jugadores general
        return 'https://www.torneospadelxperience.es/jugador/';
    }

    // Otros roles: redirección por defecto
    return $redirect_to;
}
