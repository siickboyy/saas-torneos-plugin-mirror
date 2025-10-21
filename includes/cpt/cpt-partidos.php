<?php
// CPT: Partidos jugados dentro de competiciones

function str_register_cpt_partidos() {
    $labels = [
        'name'               => __('Partidos', 'saas-torneos'),
        'singular_name'      => __('Partido', 'saas-torneos'),
        'add_new'            => __('Añadir nuevo', 'saas-torneos'),
        'add_new_item'       => __('Añadir nuevo partido', 'saas-torneos'),
        'edit_item'          => __('Editar partido', 'saas-torneos'),
        'new_item'           => __('Nuevo partido', 'saas-torneos'),
        'all_items'          => __('Todos los partidos', 'saas-torneos'),
        'view_item'          => __('Ver partido', 'saas-torneos'),
        'search_items'       => __('Buscar partidos', 'saas-torneos'),
        'not_found'          => __('No se encontraron partidos', 'saas-torneos'),
        'not_found_in_trash' => __('No hay partidos en la papelera', 'saas-torneos'),
        'menu_name'          => __('Partidos', 'saas-torneos')
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title'],
        'menu_position'      => 23,
        'menu_icon'          => 'dashicons-controls-play', // Icono tipo "play"
        'has_archive'        => false,
        'rewrite'            => false,
        'show_in_rest'       => false
    ];

    register_post_type( 'partido', $args );
}
add_action( 'init', 'str_register_cpt_partidos' );
