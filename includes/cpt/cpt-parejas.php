<?php
// CPT: Parejas de jugadores para deportes dobles

function str_register_cpt_parejas() {
    $labels = [
        'name'               => __('Parejas', 'saas-torneos'),
        'singular_name'      => __('Pareja', 'saas-torneos'),
        'add_new'            => __('Añadir nueva', 'saas-torneos'),
        'add_new_item'       => __('Añadir nueva pareja', 'saas-torneos'),
        'edit_item'          => __('Editar pareja', 'saas-torneos'),
        'new_item'           => __('Nueva pareja', 'saas-torneos'),
        'all_items'          => __('Todas las parejas', 'saas-torneos'),
        'view_item'          => __('Ver pareja', 'saas-torneos'),
        'search_items'       => __('Buscar parejas', 'saas-torneos'),
        'not_found'          => __('No se encontraron parejas', 'saas-torneos'),
        'not_found_in_trash' => __('No hay parejas en la papelera', 'saas-torneos'),
        'menu_name'          => __('Parejas', 'saas-torneos')
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title'],
        'menu_position'      => 22,
        'menu_icon'          => 'dashicons-heart', // Icono de corazón para parejas ♥
        'has_archive'        => false,
        'rewrite'            => false,
        'show_in_rest'       => false
    ];

    register_post_type( 'pareja', $args );
}
add_action( 'init', 'str_register_cpt_parejas' );
