<?php
/**
 * Registro de roles personalizados para el plugin SaaS Torneos de Raqueta
 */

// Crear roles personalizados si no existen
function str_add_custom_roles() {

    // Rol: Cliente
    if (!get_role('cliente')) {
        add_role(
            'cliente',
            __('Cliente', 'saas-torneos'),
            [
                'read' => true,        // Puede ver contenido
                'edit_posts' => false, // No puede editar entradas
            ]
        );
    }

    // Rol: Jugador
    if (!get_role('jugador')) {
        add_role(
            'jugador',
            __('Jugador', 'saas-torneos'),
            [
                'read' => true,        // Puede ver contenido
                'edit_posts' => false,
            ]
        );
    }
}
add_action('init', 'str_add_custom_roles');
