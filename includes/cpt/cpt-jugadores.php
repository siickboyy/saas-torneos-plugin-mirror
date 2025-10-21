<?php
// CPT: Jugadores de deportes (individuales, asociados a competiciones)

function str_register_cpt_jugadores() {
    $labels = [
        'name'               => __('Jugadores', 'saas-torneos'),
        'singular_name'      => __('Jugador', 'saas-torneos'),
        'add_new'            => __('Añadir nuevo', 'saas-torneos'),
        'add_new_item'       => __('Añadir nuevo jugador', 'saas-torneos'),
        'edit_item'          => __('Editar jugador', 'saas-torneos'),
        'new_item'           => __('Nuevo jugador', 'saas-torneos'),
        'all_items'          => __('Todos los jugadores', 'saas-torneos'),
        'view_item'          => __('Ver jugador', 'saas-torneos'),
        'search_items'       => __('Buscar jugadores', 'saas-torneos'),
        'not_found'          => __('No se encontraron jugadores', 'saas-torneos'),
        'not_found_in_trash' => __('No hay jugadores en la papelera', 'saas-torneos'),
        'menu_name'          => __('Jugadores', 'saas-torneos')
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true, // útil en el admin para verlos fácilmente
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title'],
        'menu_position'      => 21,
        'menu_icon'          => 'dashicons-groups', // Icono de grupo
        'has_archive'        => true,
        'rewrite'            => array('slug' => 'jugador', 'with_front' => false),
        'show_in_rest'       => false
    ];

    register_post_type( 'jugador_deportes', $args );
}
add_action( 'init', 'str_register_cpt_jugadores' );
